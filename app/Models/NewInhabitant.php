<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NewInhabitant extends Model
{
    protected $fillable = [
        'barangay_id', 'reporting_month', 'household_number', 'last_name', 'first_name', 'middle_name', 'suffix',
        'relationship_to_head', 'purok', 'complete_address', 'birth_place', 'birth_date', 'recorded_age',
        'sex', 'civil_status', 'education_level', 'religion', 'occupation', 'remarks', 'month_submitted', 'source_position',
        'active_inhabitant_id', 'added_to_active_at',
        'submitted_rbi_update_id',
    ];

    protected $casts = [
        'birth_date' => 'date',
        'reporting_month' => 'date',
        'recorded_age' => 'integer',
        'added_to_active_at' => 'datetime',
    ];

    public function barangay(): BelongsTo
    {
        return $this->belongsTo(Barangay::class);
    }
}
