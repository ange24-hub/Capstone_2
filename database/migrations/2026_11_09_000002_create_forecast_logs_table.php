<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('forecast_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('barangay_id')->constrained()->cascadeOnDelete();
            $table->char('log_month', 7);
            $table->enum('migration_type', ['in', 'out']);
            // Nullable para sa actual-only history. Dili mag-imbento og past prediction.
            $table->decimal('predicted_value', 14, 4)->nullable();
            $table->unsignedInteger('actual_value')->nullable();
            // Mahimong negative kung labaw sa 100% ang error. Dili kini probability.
            $table->decimal('accuracy_rate', 14, 4)->nullable();
            $table->string('method', 40)->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->json('history_snapshot')->nullable();
            $table->timestamp('actual_verified_at')->nullable();
            $table->char('actual_source_hash', 64)->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['barangay_id', 'log_month', 'migration_type'], 'forecast_logs_month_unique');
        });
    }

    public function down(): void { Schema::dropIfExists('forecast_logs'); }
};
