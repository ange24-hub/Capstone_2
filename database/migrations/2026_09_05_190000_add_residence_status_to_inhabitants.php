<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('inhabitants', function (Blueprint $table) {
            $table->string('residence_status', 30)->default('unconfirmed');
            $table->string('residence_source')->nullable();
            $table->index(['barangay_id', 'status', 'residence_status']);
        });
    }

    public function down(): void
    {
        Schema::table('inhabitants', function (Blueprint $table) {
            $table->dropIndex(['barangay_id', 'status', 'residence_status']);
            $table->dropColumn(['residence_status', 'residence_source']);
        });
    }
};
