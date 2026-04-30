<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BarangayRbiUpdate extends Model
{
    use HasFactory;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_SUBMITTED = 'submitted';

    protected $fillable = [
        'barangay_user_id',
        'barangay_name',
        'reporting_month',
        'as_of_date',
        'prepared_by',
        'attested_by',
        'status',
        'rows',
        'source_file_path',
        'source_file_name',
        'submitted_at',
    ];

    protected $casts = [
        'reporting_month' => 'date',
        'as_of_date' => 'date',
        'rows' => 'array',
        'submitted_at' => 'datetime',
    ];

    public static function rowFields(): array
    {
        return [
            'hih_no' => 'HIH.No',
            'household_head' => 'Name of Household Head',
            'relationship' => 'Relationship to Household Head',
            'inhabitant_name' => 'Newly Registered Barangay Inhabitant',
            'sex' => 'Sex',
            'birth_date' => 'Date of Birth',
            'birth_place' => 'Place of Birth',
            'civil_status' => 'Civil Status',
            'religion' => 'Religion',
            'occupation' => 'Occupation',
            'year_completed' => 'Year Completed',
            'remarks' => 'Remarks',
        ];
    }

    public function barangayUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'barangay_user_id');
    }

    public function statusLabel(): string
    {
        return ucfirst($this->status);
    }
}
