<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('new_inhabitants', function (Blueprint $table) {
            $table->foreignId('submitted_rbi_update_id')->nullable()->after('added_to_active_at')->constrained('barangay_rbi_updates')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('new_inhabitants', fn (Blueprint $table) => $table->dropConstrainedForeignId('submitted_rbi_update_id'));
    }
};
