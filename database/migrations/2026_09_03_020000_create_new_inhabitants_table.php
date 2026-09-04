<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('new_inhabitants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('barangay_id')->constrained()->cascadeOnDelete();
            $table->string('household_number', 100);
            $table->string('last_name');
            $table->string('first_name');
            $table->string('middle_name')->nullable();
            $table->string('relationship_to_head')->nullable();
            $table->string('purok')->nullable();
            $table->string('birth_place')->nullable();
            $table->date('birth_date')->nullable();
            $table->unsignedSmallInteger('recorded_age')->nullable();
            $table->string('sex', 20)->nullable();
            $table->string('civil_status', 60)->nullable();
            $table->string('education_level')->nullable();
            $table->string('religion')->nullable();
            $table->string('month_submitted')->nullable();
            $table->unsignedInteger('source_position')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('new_inhabitants');
    }
};
