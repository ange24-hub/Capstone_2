<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('migration_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('barangay_id')->constrained()->cascadeOnDelete();
            // Sa RBIM, inhabitants ang residents table. Dili maghimo og duplicate registry.
            $table->foreignId('resident_id')->constrained('inhabitants')->cascadeOnDelete();
            $table->enum('migration_type', ['in', 'out']);
            // Petsa sa movement, dili petsa sa pag-encode sa RBI.
            $table->date('transaction_date');
            $table->foreignId('source_record_id')->nullable()->unique()->constrained('migration_records')->cascadeOnDelete();
            $table->timestamps();
            $table->index(['barangay_id', 'migration_type', 'transaction_date'], 'migration_logs_month_lookup');
        });
    }

    public function down(): void { Schema::dropIfExists('migration_logs'); }
};
