<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RegistryReportSubmission extends Model
{
    protected $fillable = [
        'barangay_id', 'submitted_by', 'scope', 'barangay_name', 'as_of',
        'record_count', 'snapshot', 'snapshot_hash', 'submitted_at',
    ];

    protected $casts = [
        'as_of' => 'datetime',
        'submitted_at' => 'datetime',
        'snapshot' => 'array',
    ];

    public static function scopes(): array
    {
        return [
            'registered' => 'Consolidated / All Registered',
            Inhabitant::RESIDENCE_HERE => 'Living in Barangay',
            Inhabitant::RESIDENCE_ELSEWHERE => 'Registered, Living Elsewhere',
            Inhabitant::RESIDENCE_UNCONFIRMED => 'Residence Needs Confirmation',
        ];
    }

    public function scopeLabel(): string
    {
        return self::scopes()[$this->scope] ?? 'Registry Records';
    }

    public function reference(): string
    {
        return 'REG-'.str_pad((string) $this->id, 6, '0', STR_PAD_LEFT);
    }

    public function barangay(): BelongsTo
    {
        return $this->belongsTo(Barangay::class);
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }
}
