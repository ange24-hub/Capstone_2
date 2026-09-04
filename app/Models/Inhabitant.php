<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Inhabitant extends Model
{
    use HasFactory;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_MIGRATED_OUT = 'migrated_out';
    public const STATUS_INACTIVE = 'inactive';

    protected $fillable = [
        'barangay_id',
        'household_id',
        'resident_user_id',
        'registry_sequence',
        'family_number',
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
        'civil_status',
        'religion',
        'occupation',
        'education_level',
        'contact_number',
        'remarks',
        'ethnicity',
        'status',
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
