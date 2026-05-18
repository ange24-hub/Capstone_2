<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MigrationRecord extends Model
{
    use HasFactory;

    public const TYPE_IN = 'in';
    public const TYPE_OUT = 'out';

    protected $fillable = [
        'inhabitant_id',
        'barangay_id',
        'type',
        'movement_date',
        'origin',
        'destination',
        'reason',
        'recorded_by',
    ];

    protected $casts = [
        'movement_date' => 'date',
    ];

    public static function typeLabels(): array
    {
        return [
            self::TYPE_IN => 'In-migration',
            self::TYPE_OUT => 'Out-migration',
        ];
    }

    public function inhabitant(): BelongsTo
    {
        return $this->belongsTo(Inhabitant::class);
    }

    public function barangay(): BelongsTo
    {
        return $this->belongsTo(Barangay::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function typeLabel(): string
    {
        return self::typeLabels()[$this->type] ?? ucfirst($this->type);
    }
}
