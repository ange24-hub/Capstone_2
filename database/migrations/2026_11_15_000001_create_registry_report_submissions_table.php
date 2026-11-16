<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('registry_report_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('barangay_id')->constrained()->cascadeOnDelete();
            $table->foreignId('submitted_by')->constrained('users')->restrictOnDelete();
            $table->string('scope', 32);
            $table->string('barangay_name');
            $table->dateTime('as_of');
            $table->unsignedInteger('record_count');
            $table->json('snapshot');
            $table->char('snapshot_hash', 64);
            $table->timestamp('submitted_at');
            $table->timestamps();

            $table->index(['barangay_id', 'submitted_at']);
            $table->index(['scope', 'submitted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registry_report_submissions');
    }
};
