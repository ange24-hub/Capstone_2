<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('new_inhabitants', function (Blueprint $table) {
            $table->string('suffix', 30)->nullable()->after('middle_name');
            $table->string('complete_address')->nullable()->after('purok');
            $table->string('occupation')->nullable()->after('religion');
            $table->text('remarks')->nullable()->after('occupation');
        });
    }

    public function down(): void
    {
        Schema::table('new_inhabitants', fn (Blueprint $table) => $table->dropColumn(['suffix', 'complete_address', 'occupation', 'remarks']));
    }
};
