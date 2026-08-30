<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('barangay_rbi_updates')
            ->select(['id', 'families', 'prepared_by', 'prepared_signature_path', 'attested_by', 'attested_signature_path'])
            ->orderBy('id')
            ->eachById(function (object $report): void {
                $families = is_string($report->families) ? json_decode($report->families, true) : $report->families;

                if (! is_array($families)) {
                    return;
                }

                $families = collect($families)->map(function (array $family) use ($report): array {
                    $family['prepared_by'] ??= $report->prepared_by;
                    $family['prepared_signature_path'] ??= $report->prepared_signature_path;
                    $family['attested_by'] ??= $report->attested_by;
                    $family['attested_signature_path'] ??= $report->attested_signature_path;

                    return $family;
                })->values()->all();

                DB::table('barangay_rbi_updates')
                    ->where('id', $report->id)
                    ->update(['families' => json_encode($families)]);
            });
    }

    public function down(): void
    {
        // Family-level signature metadata is intentionally retained.
    }
};
