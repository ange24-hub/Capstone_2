<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('barangay_rbi_updates', function (Blueprint $table) {
            $table->string('prepared_signature_path')->nullable()->after('prepared_by');
            $table->string('attested_signature_path')->nullable()->after('attested_by');
        });
    }

    public function down(): void
    {
        Schema::table('barangay_rbi_updates', function (Blueprint $table) {
            $table->dropColumn(['prepared_signature_path', 'attested_signature_path']);
        });
    }
};
