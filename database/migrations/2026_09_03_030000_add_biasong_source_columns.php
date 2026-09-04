<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inhabitants', function (Blueprint $table) {
            $table->string('family_number', 30)->nullable()->after('registry_sequence');
            $table->string('individual_number', 30)->nullable()->after('family_number');
        });
        Schema::table('deceased_inhabitants', function (Blueprint $table) {
            $table->string('family_number', 30)->nullable()->after('household_number');
            $table->string('individual_number', 30)->nullable()->after('family_number');
        });
    }

    public function down(): void
    {
        Schema::table('inhabitants', fn (Blueprint $table) => $table->dropColumn(['family_number', 'individual_number']));
        Schema::table('deceased_inhabitants', fn (Blueprint $table) => $table->dropColumn(['family_number', 'individual_number']));
    }
};
