<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BarangayRbiFamily extends Model
{
    protected $fillable = ['barangay_rbi_update_id', 'household_id', 'household_head', 'position'];

    public function report(): BelongsTo
    {
        return $this->belongsTo(BarangayRbiUpdate::class, 'barangay_rbi_update_id');
    }

    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class);
    }

    public function members(): HasMany
    {
        return $this->hasMany(BarangayRbiMember::class)->orderBy('position');
    }

    public function deceasedRecords(): HasMany
    {
        return $this->hasMany(BarangayRbiDeceasedRecord::class)->orderBy('position');
    }
}
