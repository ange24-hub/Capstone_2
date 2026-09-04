<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeceasedInhabitant extends Model
{
    protected $fillable = [
        'barangay_id', 'household_number', 'family_number', 'individual_number', 'last_name', 'first_name', 'middle_name', 'suffix',
        'relationship_to_head', 'purok', 'birth_place', 'birth_date', 'recorded_age', 'sex',
        'civil_status', 'education_level', 'religion', 'occupation', 'remarks', 'death_date',
        'source_position',
    ];

    protected $casts = [
        'birth_date' => 'date',
        'death_date' => 'date',
        'recorded_age' => 'integer',
    ];

    public function barangay(): BelongsTo
    {
        return $this->belongsTo(Barangay::class);
    }
}
