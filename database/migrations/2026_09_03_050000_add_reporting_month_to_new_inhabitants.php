<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('new_inhabitants', function (Blueprint $table) {
            $table->date('reporting_month')->nullable()->after('barangay_id');
            $table->index(['barangay_id', 'reporting_month']);
        });
    }

    public function down(): void
    {
        Schema::table('new_inhabitants', function (Blueprint $table) {
            $table->dropIndex(['barangay_id', 'reporting_month']);
            $table->dropColumn('reporting_month');
        });
    }
};
