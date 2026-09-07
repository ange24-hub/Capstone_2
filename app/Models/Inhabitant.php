<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Inhabitant extends Model
{
    use \App\Models\Concerns\PreservesRegistryRemarks;

    use HasFactory;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_MIGRATED_OUT = 'migrated_out';
    public const STATUS_INACTIVE = 'inactive';
    public const RESIDENCE_HERE = 'living_here';
    public const RESIDENCE_ELSEWHERE = 'living_elsewhere';
    public const RESIDENCE_UNCONFIRMED = 'unconfirmed';

    protected $fillable = [
        'barangay_id',
        'household_id',
        'resident_user_id',
        'registry_sequence',
        'family_number',
        'family_code',
        'individual_number',
        'first_name',
        'middle_name',
        'last_name',
        'suffix',
        'relationship_to_head',
        'sex',
        'birth_date',
        'recorded_age',
        'birth_place',
        'complete_address',
        'civil_status',
        'religion',
        'occupation',
        'education_level',
        'contact_number',
        'remarks',
        'ethnicity',
        'status',
        'residence_status',
        'residence_source',
    ];

    protected $casts = [
        'birth_date' => 'date',
        'recorded_age' => 'integer',
    ];

    public static function statusLabels(): array
    {
        return [
            self::STATUS_ACTIVE => 'Active resident',
            self::STATUS_MIGRATED_OUT => 'Migrated out',
            self::STATUS_INACTIVE => 'Inactive',
        ];
    }

    public static function residenceLabels(): array
    {
        return [
            self::RESIDENCE_HERE => 'Living in barangay',
            self::RESIDENCE_ELSEWHERE => 'Registered, living elsewhere',
            self::RESIDENCE_UNCONFIRMED => 'Residence needs confirmation',
        ];
    }

    public function residenceLabel(): string
    {
        return self::residenceLabels()[$this->residence_status] ?? self::residenceLabels()[self::RESIDENCE_UNCONFIRMED];
    }

    public function scopeLivingHere($query)
    {
        return $query->where('status', self::STATUS_ACTIVE)->where('residence_status', self::RESIDENCE_HERE);
    }

    public function barangay(): BelongsTo
    {
        return $this->belongsTo(Barangay::class);
    }

    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class);
    }

    public function residentUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resident_user_id');
    }

    public function migrationRecords(): HasMany
    {
        return $this->hasMany(MigrationRecord::class);
    }

    public function sourceTransferDestination(): ?string
    {
        if ($this->status !== self::STATUS_MIGRATED_OUT
            || ! preg_match('/\bTRANSFER(?:RED)?\s+(?:TO\s+)?([^\[\r\n]+)/i', (string) $this->remarks, $match)) {
            return null;
        }

        // Import provenance and missing-field notes start with square brackets.
        $destination = trim($match[1], " \t\n\r\0\x0B.;");

        return $destination !== '' ? $destination : null;
    }

    public function fullName(): string
    {
        return collect([$this->first_name, $this->middle_name, $this->last_name, $this->suffix])
            ->filter()
            ->implode(' ');
    }

    public function statusLabel(): string
    {
        return self::statusLabels()[$this->status] ?? ucfirst($this->status);
    }
}
