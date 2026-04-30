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

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'resident_id',
        'reference_number',
        'document_type',
        'purpose',
        'requested_for_date',
        'status',
        'remarks',
        'reviewed_by',
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

    public function typeLabel(): string
    {
        return self::typeLabels()[$this->document_type] ?? 'Unknown document';
    }

    public function statusLabel(): string
    {
        return ucfirst($this->status);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resident_id');
    }
}
