<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Household extends Model
{
    use HasFactory;

    protected $fillable = [
        'barangay_id',
        'household_number',
        'purok',
        'address',
        'latitude',
        'longitude',
    ];

    public function barangay(): BelongsTo
    {
        return $this->belongsTo(Barangay::class);
    }

    public function inhabitants(): HasMany
    {
        return $this->hasMany(Inhabitant::class);
    }

    public function coordinate(): string
    {
        if ($this->latitude === null || $this->longitude === null) {
            return 'Not set';
        }

        return $this->latitude.', '.$this->longitude;
    }
}
