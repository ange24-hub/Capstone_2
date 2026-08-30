<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('barangay_rbi_updates', function (Blueprint $table) {
            $table->json('families')->nullable()->after('status');
        });

        DB::table('barangay_rbi_updates')
            ->select(['id', 'household_head', 'rows', 'prepared_by', 'prepared_signature_path', 'attested_by', 'attested_signature_path'])
            ->orderBy('id')
            ->eachById(function (object $report): void {
                $members = is_string($report->rows) ? json_decode($report->rows, true) : $report->rows;
                $members = is_array($members) ? $members : [];
                $householdHead = trim((string) ($report->household_head ?? ($members[0]['household_head'] ?? '')));

                if ($householdHead === '' && $members === []) {
                    return;
                }

                $members = collect($members)
                    ->map(function (array $member): array {
                        unset($member['household_head']);

                        return $member;
                    })
                    ->values()
                    ->all();

                DB::table('barangay_rbi_updates')
                    ->where('id', $report->id)
                    ->update(['families' => json_encode([[
                        'household_head' => $householdHead ?: 'Unnamed household',
                        'members' => $members,
                        'prepared_by' => $report->prepared_by,
                        'prepared_signature_path' => $report->prepared_signature_path,
                        'attested_by' => $report->attested_by,
                        'attested_signature_path' => $report->attested_signature_path,
                    ]])]);
            });
    }

    public function down(): void
    {
        Schema::table('barangay_rbi_updates', function (Blueprint $table) {
            $table->dropColumn('families');
        });
    }
};
