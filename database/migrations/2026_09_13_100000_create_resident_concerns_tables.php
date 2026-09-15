<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('resident_concerns', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 40)->unique();
            $table->foreignId('resident_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('barangay_id')->constrained()->restrictOnDelete();
            $table->string('title', 160);
            $table->text('description');
            $table->string('location', 255)->nullable();
            $table->string('category', 40);
            $table->string('status', 30)->default('submitted');
            $table->string('attachment_path')->nullable();
            $table->string('attachment_name')->nullable();
            $table->string('ai_category', 40)->nullable();
            $table->string('ai_model', 120)->nullable();
            $table->timestamp('ai_suggested_at')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
            $table->index(['barangay_id', 'status', 'created_at']);
            $table->index(['resident_id', 'created_at']);
        });
        Schema::create('concern_updates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('resident_concern_id')->constrained()->cascadeOnDelete();
            $table->foreignId('author_id')->constrained('users')->restrictOnDelete();
            $table->string('status', 30)->nullable();
            $table->string('category', 40)->nullable();
            $table->text('message');
            $table->text('internal_note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('concern_updates');
        Schema::dropIfExists('resident_concerns');
    }
};
