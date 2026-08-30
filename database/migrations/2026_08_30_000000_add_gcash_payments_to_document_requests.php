<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_requests', function (Blueprint $table) {
            $table->decimal('amount_due', 10, 2)->default(0)->after('status');
            $table->string('payment_method', 30)->nullable()->after('amount_due');
            $table->string('payment_status', 30)->default('not_required')->after('payment_method');
            $table->string('payment_reference', 50)->nullable()->unique()->after('payment_status');
            $table->timestamp('payment_transaction_at')->nullable()->after('payment_reference');
            $table->string('payer_name')->nullable()->after('payment_transaction_at');
            $table->string('payer_mobile', 20)->nullable()->after('payer_name');
            $table->string('payment_proof_path')->nullable()->after('payer_mobile');
            $table->timestamp('payment_submitted_at')->nullable()->after('payment_proof_path');
            $table->timestamp('paid_at')->nullable()->after('payment_submitted_at');
            $table->foreignId('payment_reviewed_by')->nullable()->after('paid_at')->constrained('users')->nullOnDelete();
            $table->timestamp('payment_reviewed_at')->nullable()->after('payment_reviewed_by');
            $table->text('payment_remarks')->nullable()->after('payment_reviewed_at');
            $table->index(['barangay_id', 'payment_status']);
        });
    }

    public function down(): void
    {
        Schema::table('document_requests', function (Blueprint $table) {
            $table->dropIndex(['barangay_id', 'payment_status']);
            $table->dropForeign(['payment_reviewed_by']);
            $table->dropUnique(['payment_reference']);
            $table->dropColumn([
                'amount_due',
                'payment_method',
                'payment_status',
                'payment_reference',
                'payment_transaction_at',
                'payer_name',
                'payer_mobile',
                'payment_proof_path',
                'payment_submitted_at',
                'paid_at',
                'payment_reviewed_by',
                'payment_reviewed_at',
                'payment_remarks',
            ]);
        });
    }
};
