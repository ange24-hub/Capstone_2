<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('inhabitants', fn (Blueprint $table) => $table->string('family_code', 30)->nullable());
    }

    public function down(): void
    {
        Schema::table('inhabitants', fn (Blueprint $table) => $table->dropColumn('family_code'));
    }
};
