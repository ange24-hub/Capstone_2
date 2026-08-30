<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentRequest extends Model
{
    use HasFactory;

    public const TYPE_INDIGENCY = 'barangay_indigency';

    public const TYPE_CLEARANCE = 'barangay_clearance';

    public const TYPE_RESIDENCY = 'certificate_of_residency';

    public const TYPE_BUSINESS_CLEARANCE = 'business_clearance';

    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_READY = 'ready';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_REJECTED = 'rejected';

    public const PAYMENT_METHOD_GCASH = 'gcash';

    public const PAYMENT_NOT_REQUIRED = 'not_required';

    public const PAYMENT_UNPAID = 'unpaid';

    public const PAYMENT_PENDING = 'pending_verification';

    public const PAYMENT_PAID = 'paid';

    public const PAYMENT_REJECTED = 'rejected';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'resident_id',
        'barangay_id',
        'inhabitant_id',
        'reference_number',
        'document_type',
        'purpose',
        'requested_for_date',
        'status',
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
        'remarks',
        'reviewed_by',
        'processed_at',
    ];

    protected $casts = [
        'requested_for_date' => 'date',
        'amount_due' => 'decimal:2',
        'payment_transaction_at' => 'datetime',
        'payment_submitted_at' => 'datetime',
        'paid_at' => 'datetime',
        'payment_reviewed_at' => 'datetime',
        'processed_at' => 'datetime',
    ];

    /**
     * @return array<string, string>
     */
    public static function typeLabels(): array
    {
        return [
            self::TYPE_INDIGENCY => 'Barangay Indigency',
            self::TYPE_CLEARANCE => 'Barangay Clearance',
            self::TYPE_RESIDENCY => 'Certificate of Residency',
            self::TYPE_BUSINESS_CLEARANCE => 'Business Clearance',
        ];
    }

    public static function feeFor(string $documentType): float
    {
        return max(0, (float) config("rbim.document_fees.{$documentType}", 0));
    }

    /** @return array<string, string> */
    public static function paymentStatusLabels(): array
    {
        return [
            self::PAYMENT_NOT_REQUIRED => 'No payment required',
            self::PAYMENT_UNPAID => 'Awaiting GCash payment',
            self::PAYMENT_PENDING => 'For payment verification',
            self::PAYMENT_PAID => 'Payment verified',
            self::PAYMENT_REJECTED => 'Payment needs correction',
        ];
    }

    public function paymentStatusLabel(): string
    {
        return self::paymentStatusLabels()[$this->payment_status] ?? ucfirst(str_replace('_', ' ', $this->payment_status));
    }

    public function requiresPayment(): bool
    {
        return (float) $this->amount_due > 0;
    }

    public function isPaid(): bool
    {
        return ! $this->requiresPayment() || $this->payment_status === self::PAYMENT_PAID;
    }

    public function typeLabel(): string
    {
        return self::typeLabels()[$this->document_type] ?? 'Unknown document';
    }

    public function statusLabel(): string
    {
        return self::statusLabels()[$this->status] ?? ucfirst($this->status);
    }

    public static function statusLabels(): array
    {
        return [
            self::STATUS_PENDING => 'Pending review',
            self::STATUS_PROCESSING => 'Processing',
            self::STATUS_READY => 'Ready for release',
            self::STATUS_COMPLETED => 'Completed',
            self::STATUS_REJECTED => 'Rejected',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resident_id');
    }

    public function barangay(): BelongsTo
    {
        return $this->belongsTo(Barangay::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
