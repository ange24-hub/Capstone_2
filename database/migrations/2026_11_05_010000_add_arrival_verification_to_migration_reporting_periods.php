<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('migration_reporting_periods', function (Blueprint $table) {
            // Existing departure confirmations never attest to arrival completeness.
            $table->unsignedInteger('in_count')->nullable();
            $table->string('in_source_fingerprint', 64)->nullable();
            $table->foreignId('in_verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('in_verified_at')->nullable();
            $table->string('in_notes', 500)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('migration_reporting_periods', function (Blueprint $table) {
            $table->dropForeign(['in_verified_by']);
            $table->dropColumn(['in_count', 'in_source_fingerprint', 'in_verified_by', 'in_verified_at', 'in_notes']);
        });
    }
};
