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
        'household_name',
        'purok',
        'address',
        'latitude',
        'longitude',
        'offline_uuid',
        'gps_accuracy',
        'location_captured_at',
    ];

    protected $casts = [
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'gps_accuracy' => 'decimal:2',
        'location_captured_at' => 'datetime',
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

        return $this->latitude . ', ' . $this->longitude;
    }
}