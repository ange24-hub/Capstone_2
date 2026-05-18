<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('barangays', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('municipality')->nullable();
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('barangay_id')->nullable()->after('role')->constrained()->nullOnDelete();
        });

        Schema::create('households', function (Blueprint $table) {
            $table->id();
            $table->foreignId('barangay_id')->constrained()->cascadeOnDelete();
            $table->string('household_number');
            $table->string('purok')->nullable();
            $table->string('address')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->timestamps();

            $table->unique(['barangay_id', 'household_number']);
        });

        Schema::create('inhabitants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('barangay_id')->constrained()->cascadeOnDelete();
            $table->foreignId('household_id')->constrained()->cascadeOnDelete();
            $table->foreignId('resident_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('first_name');
            $table->string('middle_name')->nullable();
            $table->string('last_name');
            $table->string('suffix')->nullable();
            $table->string('relationship_to_head')->nullable();
            $table->string('sex', 20);
            $table->date('birth_date')->nullable();
            $table->string('birth_place')->nullable();
            $table->string('civil_status', 60)->nullable();
            $table->string('religion')->nullable();
            $table->string('occupation')->nullable();
            $table->string('education_level')->nullable();
            $table->string('contact_number')->nullable();
            $table->string('status', 30)->default('active');
            $table->timestamps();

            $table->index(['barangay_id', 'last_name']);
        });

        Schema::create('migration_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inhabitant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('barangay_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20);
            $table->date('movement_date');
            $table->string('origin')->nullable();
            $table->string('destination')->nullable();
            $table->text('reason')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['barangay_id', 'type', 'movement_date']);
        });

        Schema::table('document_requests', function (Blueprint $table) {
            $table->foreignId('inhabitant_id')->nullable()->after('resident_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('document_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('inhabitant_id');
        });

        Schema::dropIfExists('migration_records');
        Schema::dropIfExists('inhabitants');
        Schema::dropIfExists('households');

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('barangay_id');
        });

        Schema::dropIfExists('barangays');
    }
};
