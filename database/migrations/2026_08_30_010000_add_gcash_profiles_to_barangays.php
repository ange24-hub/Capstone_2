<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('barangays', function (Blueprint $table) {
            $table->boolean('gcash_enabled')->default(false)->after('logo_path');
            $table->string('gcash_merchant_name')->nullable()->after('gcash_enabled');
            $table->string('gcash_account_identifier', 100)->nullable()->after('gcash_merchant_name');
            $table->string('gcash_qr_path')->nullable()->after('gcash_account_identifier');
            $table->foreignId('gcash_approved_by')->nullable()->after('gcash_qr_path')->constrained('users')->nullOnDelete();
            $table->timestamp('gcash_approved_at')->nullable()->after('gcash_approved_by');
        });
    }

    public function down(): void
    {
        Schema::table('barangays', function (Blueprint $table) {
            $table->dropForeign(['gcash_approved_by']);
            $table->dropColumn([
                'gcash_enabled',
                'gcash_merchant_name',
                'gcash_account_identifier',
                'gcash_qr_path',
                'gcash_approved_by',
                'gcash_approved_at',
            ]);
        });
    }
};
