<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('barangay_rbi_updates', function (Blueprint $table) {
            $table->json('deceased_rows')->nullable()->after('rows');
        });
    }

    public function down(): void
    {
        Schema::table('barangay_rbi_updates', function (Blueprint $table) {
            $table->dropColumn('deceased_rows');
        });
    }
};
