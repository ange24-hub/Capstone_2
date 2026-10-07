<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentPaymentHistory extends Model
{
    protected $table = 'document_payment_histories';

    protected $guarded = ['id'];

    protected $casts = ['snapshot' => 'array', 'occurred_at' => 'datetime'];

    public function documentRequest(): BelongsTo
    {
        return $this->belongsTo(DocumentRequest::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public static function record(DocumentRequest $document, string $event, ?int $actorId): self
    {
        return static::create([
            'document_request_id' => $document->id,
            'actor_id' => $actorId,
            'event' => $event,
            'occurred_at' => now(),
            'snapshot' => $document->only([
                'reference_number', 'document_type', 'amount_due', 'payment_method',
                'payment_status', 'payment_reference', 'payment_transaction_at',
                'payment_proof_path', 'payment_remarks', 'paid_at',
            ]),
        ]);
    }

    public function eventLabel(): string
    {
        return match ($this->event) {
            'submitted' => 'GCash submitted for verification',
            'verified' => 'GCash payment verified',
            'rejected' => 'GCash payment returned for correction',
            'received' => 'In-person payment received',
            default => 'Existing payment record',
        };
    }
}
