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

    protected $fillable = [
        'barangay_user_id',
        'barangay_name',
        'household_head',
        'reporting_month',
        'as_of_date',
        'prepared_by',
        'prepared_signature_path',
        'attested_by',
        'attested_signature_path',
        'status',
        'families',
        'rows',
        'deceased_rows',
        'source_file_path',
        'source_file_name',
        'submitted_at',
    ];

    protected $casts = [
        'reporting_month' => 'date',
        'as_of_date' => 'date',
        'families' => 'array',
        'rows' => 'array',
        'deceased_rows' => 'array',
        'submitted_at' => 'datetime',
    ];

    public static function rowFields(): array
    {
        return [
            'household_head' => 'Name of Household Head',
            'inhabitant_name' => 'A. Name of Newly Registered Barangay Inhabitant (Family Name, First Name, Middle Name)',
            'sex' => 'Sex',
            'birth_date' => 'Date of Birth (mm/dd/yy)',
            'birth_place' => 'Place of Birth',
            'civil_status' => 'Civil Status',
            'occupation' => 'Occupation',
            'relationship' => 'Relationship to Household Head',
        ];
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

    public function statusLabel(): string
    {
        return ucfirst($this->status);
    }
}
