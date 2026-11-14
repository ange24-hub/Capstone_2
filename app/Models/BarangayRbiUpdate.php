<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BarangayRbiUpdate extends Model
{
    use HasFactory;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_SUBMITTED = 'submitted';

    protected $casts = [
        'reporting_month' => 'date',
        'as_of_date' => 'date',
        'families' => 'array',
        'rows' => 'array',
        'deceased_rows' => 'array',
        'submitted_at' => 'datetime',
        'consolidated_at' => 'datetime',
    ];

    protected $fillable = [
        'barangay_user_id',
        'barangay_name',
        'household_head',
        'reporting_month',
        'as_of_date',
        'prepared_by',
        'prepared_role',
        'prepared_signature_path',
        'certified_by',
        'certified_signature_path',
        'attested_by',
        'attested_signature_path',
        'status',
        'families',
        'rows',
        'deceased_rows',
        'source_file_path',
        'source_file_name',
        'submitted_at',
        'consolidated_at',
    ];


    public function preparedRoleLabel(): string
    {
        return $this->prepared_role ?? 'BHW / Encoder';
    }

    public static function rowFields(): array
    {
        return ['household_head' => 'Name of Household Head'] + \App\Support\HouseholdRbi::fields();
    }

    public static function deceasedRowFields(): array
    {
        return [
            'deceased_name' => 'B. Name of Deceased Registered Brgy. Inhabitant',
            'death_date' => 'Date of Death',
        ];
    }

    public function barangayUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'barangay_user_id');
    }

    public function rbiFamilies(): HasMany
    {
        return $this->hasMany(BarangayRbiFamily::class)->orderBy('position');
    }

    public function deceasedRecords(): HasMany
    {
        return $this->hasMany(BarangayRbiDeceasedRecord::class)->orderBy('position');
    }

    /** Use the report's recorded completion date, including older forms without an as-of date. */
    public function accomplishedDate(): \Carbon\Carbon
    {
        if ($this->as_of_date) {
            return $this->as_of_date->copy();
        }

        return ($this->submitted_at ?? $this->created_at ?? now())->copy()->timezone('Asia/Manila');
    }

    public function statusLabel(): string
    {
        return ucfirst($this->status);
    }
}
