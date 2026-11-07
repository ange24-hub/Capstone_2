<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rbi_arrival_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('barangay_id')->constrained()->cascadeOnDelete();
            $table->string('start_month', 7);
            $table->string('end_month', 7);
            $table->json('monthly_counts');
            $table->string('source_fingerprint', 64);
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['barangay_id', 'start_month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rbi_arrival_periods');
    }
};
