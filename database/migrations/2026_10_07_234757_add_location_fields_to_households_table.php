<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('households', function (Blueprint $table) {

            if (!Schema::hasColumn('households', 'offline_uuid')) {
                $table->string('offline_uuid')->nullable()->unique();
            }

            if (!Schema::hasColumn('households', 'latitude')) {
                $table->decimal('latitude', 10, 7)->nullable();
            }

            if (!Schema::hasColumn('households', 'longitude')) {
                $table->decimal('longitude', 10, 7)->nullable();
            }

            if (!Schema::hasColumn('households', 'gps_accuracy')) {
                $table->decimal('gps_accuracy', 8, 2)->nullable();
            }

            if (!Schema::hasColumn('households', 'location_captured_at')) {
                $table->timestamp('location_captured_at')->nullable();
            }

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('households', function (Blueprint $table) {

            if (Schema::hasColumn('households', 'offline_uuid')) {
                $table->dropColumn('offline_uuid');
            }

            if (Schema::hasColumn('households', 'gps_accuracy')) {
                $table->dropColumn('gps_accuracy');
            }

            if (Schema::hasColumn('households', 'location_captured_at')) {
                $table->dropColumn('location_captured_at');
            }

            // Intentionally do not drop latitude and longitude
            // because they already existed before this migration.
        });
    }
};