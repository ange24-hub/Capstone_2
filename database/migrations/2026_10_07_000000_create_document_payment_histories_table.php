<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_payment_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_request_id')->constrained()->restrictOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event', 30);
            $table->timestamp('occurred_at');
            $table->json('snapshot');
            $table->timestamps();
            $table->index(['document_request_id', 'occurred_at']);
        });

        DB::table('document_requests')->whereIn('payment_status', ['paid', 'pending_verification', 'rejected'])
            ->where('amount_due', '>', 0)->orderBy('id')->chunkById(100, function ($documents) {
                foreach ($documents as $document) {
                    $snapshot = array_intersect_key((array) $document, array_flip([
                        'reference_number', 'document_type', 'amount_due', 'payment_method',
                        'payment_status', 'payment_reference', 'payment_transaction_at',
                        'payment_proof_path', 'payment_remarks', 'paid_at',
                    ]));
                    DB::table('document_payment_histories')->insert([
                        'document_request_id' => $document->id,
                        'actor_id' => $document->payment_reviewed_by,
                        'event' => 'imported',
                        'occurred_at' => $document->payment_reviewed_at ?? $document->payment_submitted_at ?? $document->paid_at ?? $document->updated_at,
                        'snapshot' => json_encode($snapshot),
                        'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_payment_histories');
    }
};
