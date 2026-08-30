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
            $table->string('household_head')->nullable()->after('barangay_name');
        });

        DB::table('barangay_rbi_updates')
            ->select(['id', 'rows'])
            ->orderBy('id')
            ->eachById(function (object $update): void {
                $rows = is_string($update->rows) ? json_decode($update->rows, true) : $update->rows;
                $householdHead = trim((string) ($rows[0]['household_head'] ?? ''));

                if ($householdHead !== '') {
                    DB::table('barangay_rbi_updates')
                        ->where('id', $update->id)
                        ->update(['household_head' => $householdHead]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('barangay_rbi_updates', function (Blueprint $table) {
            $table->dropColumn('household_head');
        });
    }
};
