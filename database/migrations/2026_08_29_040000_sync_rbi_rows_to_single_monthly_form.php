<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('barangay_rbi_updates')
            ->select(['id', 'families'])
            ->whereNotNull('families')
            ->orderBy('id')
            ->each(function (object $report): void {
                $families = json_decode((string) $report->families, true);

                if (! is_array($families) || $families === []) {
                    return;
                }

                $rows = [];

                foreach ($families as $family) {
                    $householdHead = trim((string) ($family['household_head'] ?? ''));

                    foreach (($family['members'] ?? []) as $member) {
                        if (! is_array($member)) {
                            continue;
                        }

                        $rows[] = ['household_head' => $householdHead] + $member;
                    }
                }

                DB::table('barangay_rbi_updates')
                    ->where('id', $report->id)
                    ->update([
                        'household_head' => $rows[0]['household_head'] ?? null,
                        'rows' => json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    ]);
            });
    }

    public function down(): void
    {
        // The previous family JSON remains available for legacy rollback.
    }
};
