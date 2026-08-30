<?php

use App\Models\BarangayRbiUpdate;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('barangay_rbi_update_merge_backups', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('keeper_id');
            $table->unsignedBigInteger('original_id')->unique();
            $table->longText('payload');
        });

        $duplicateMonths = DB::table('barangay_rbi_updates')
            ->select(['barangay_user_id', 'reporting_month'])
            ->whereNotNull('reporting_month')
            ->groupBy(['barangay_user_id', 'reporting_month'])
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicateMonths as $duplicateMonth) {
            $reports = DB::table('barangay_rbi_updates')
                ->where('barangay_user_id', $duplicateMonth->barangay_user_id)
                ->whereDate('reporting_month', $duplicateMonth->reporting_month)
                ->orderBy('id')
                ->get();
            $keeper = $reports->first();
            $latest = $reports->last();

            foreach ($reports as $report) {
                DB::table('barangay_rbi_update_merge_backups')->insert([
                    'keeper_id' => $keeper->id,
                    'original_id' => $report->id,
                    'payload' => json_encode((array) $report),
                ]);
            }

            $families = $reports->flatMap(fn (object $report): array => $this->decodeArray($report->families))->values()->all();
            $rows = $reports->flatMap(fn (object $report): array => $this->decodeArray($report->rows))->values()->all();
            $deceasedRows = $reports->flatMap(fn (object $report): array => $this->decodeArray($report->deceased_rows))->values()->all();
            $submittedAt = $reports->pluck('submitted_at')->filter()->max();

            DB::table('barangay_rbi_updates')->where('id', $keeper->id)->update([
                'household_head' => $families[0]['household_head'] ?? $keeper->household_head,
                'prepared_by' => $latest->prepared_by,
                'prepared_signature_path' => $latest->prepared_signature_path,
                'attested_by' => $latest->attested_by,
                'attested_signature_path' => $latest->attested_signature_path,
                'status' => $reports->contains(fn (object $report): bool => $report->status === BarangayRbiUpdate::STATUS_SUBMITTED)
                    ? BarangayRbiUpdate::STATUS_SUBMITTED
                    : BarangayRbiUpdate::STATUS_DRAFT,
                'families' => json_encode($families),
                'rows' => json_encode($rows),
                'deceased_rows' => json_encode($deceasedRows),
                'submitted_at' => $submittedAt,
                'updated_at' => $reports->pluck('updated_at')->filter()->max(),
            ]);

            DB::table('barangay_rbi_updates')
                ->whereIn('id', $reports->pluck('id')->reject(fn (int $id): bool => $id === $keeper->id))
                ->delete();
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('barangay_rbi_update_merge_backups')) {
            return;
        }

        DB::table('barangay_rbi_update_merge_backups')
            ->orderBy('original_id')
            ->get()
            ->groupBy('keeper_id')
            ->each(function ($backups, int|string $keeperId): void {
                DB::table('barangay_rbi_updates')->where('id', $keeperId)->delete();

                foreach ($backups as $backup) {
                    DB::table('barangay_rbi_updates')->insert(json_decode($backup->payload, true));
                }
            });

        Schema::dropIfExists('barangay_rbi_update_merge_backups');
    }

    private function decodeArray(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (! is_string($value) || $value === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }
};
