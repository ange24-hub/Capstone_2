<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('population_report_submissions', function (Blueprint $table) {
            $table->id();
            // Ang snapshot magpabilin bisan tangtangon ang account o barangay configuration.
            $table->foreignId('barangay_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('barangay_name');
            $table->string('submitted_by_name');
            $table->unsignedSmallInteger('report_year');
            $table->unsignedTinyInteger('semester');
            $table->unsignedInteger('total_records');
            $table->uuid('submission_key')->unique();
            $table->json('snapshot');
            $table->char('snapshot_hash', 64);
            $table->char('content_hash', 64);
            $table->timestamp('submitted_at');
            $table->timestamps();
            $table->unique(['barangay_id', 'report_year', 'semester', 'content_hash'], 'population_submission_content_unique');
            $table->index(['barangay_id', 'submitted_at']);
        });
    }

    public function down(): void { Schema::dropIfExists('population_report_submissions'); }
};
