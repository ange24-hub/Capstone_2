<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('new_inhabitants', function (Blueprint $table) {
            $table->foreignId('active_inhabitant_id')->nullable()->after('source_position')->constrained('inhabitants')->nullOnDelete();
            $table->timestamp('added_to_active_at')->nullable()->after('active_inhabitant_id');
        });
    }

    public function down(): void
    {
        Schema::table('new_inhabitants', function (Blueprint $table) {
            $table->dropConstrainedForeignId('active_inhabitant_id');
            $table->dropColumn('added_to_active_at');
        });
    }
};
