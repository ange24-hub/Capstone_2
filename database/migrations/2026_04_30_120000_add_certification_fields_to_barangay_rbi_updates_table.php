<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('barangay_rbi_updates', function (Blueprint $table) {
            $table->string('prepared_by')->nullable()->after('as_of_date');
            $table->string('attested_by')->nullable()->after('prepared_by');
        });
    }

    public function down(): void
    {
        Schema::table('barangay_rbi_updates', function (Blueprint $table) {
            $table->dropColumn(['prepared_by', 'attested_by']);
        });
    }
};
