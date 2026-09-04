<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inhabitants', function (Blueprint $table) {
            $table->string('registry_sequence', 30)->nullable()->after('resident_user_id');
            $table->unsignedSmallInteger('recorded_age')->nullable()->after('birth_date');
            $table->text('remarks')->nullable()->after('contact_number');
            $table->string('ethnicity')->nullable()->after('remarks');
        });
    }

    public function down(): void
    {
        Schema::table('inhabitants', function (Blueprint $table) {
            $table->dropColumn(['registry_sequence', 'recorded_age', 'remarks', 'ethnicity']);
        });
    }
};
