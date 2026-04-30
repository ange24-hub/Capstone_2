<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('barangay_rbi_updates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('barangay_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('barangay_name')->nullable();
            $table->date('reporting_month')->nullable();
            $table->date('as_of_date')->nullable();
            $table->string('status')->default('draft');
            $table->json('rows')->nullable();
            $table->string('source_file_path')->nullable();
            $table->string('source_file_name')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('barangay_rbi_updates');
    }
};
