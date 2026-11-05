<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('migration_reporting_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('barangay_id')->constrained()->cascadeOnDelete();
            $table->string('month', 7);
            $table->unsignedInteger('out_count');
            $table->string('source_fingerprint', 64);
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->string('notes', 500)->nullable();
            $table->timestamps();
            $table->unique(['barangay_id', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('migration_reporting_periods');
    }
};
