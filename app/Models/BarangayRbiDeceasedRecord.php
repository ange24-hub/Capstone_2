<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BarangayRbiDeceasedRecord extends Model
{
    protected $fillable = [
        'barangay_rbi_update_id', 'barangay_rbi_family_id', 'inhabitant_id',
        'deceased_name', 'death_date', 'position',
    ];

    protected $casts = ['death_date' => 'date'];

    public function report(): BelongsTo
    {
        return $this->belongsTo(BarangayRbiUpdate::class, 'barangay_rbi_update_id');
    }

    public function family(): BelongsTo
    {
        return $this->belongsTo(BarangayRbiFamily::class, 'barangay_rbi_family_id');
    }

    public function inhabitant(): BelongsTo
    {
        return $this->belongsTo(Inhabitant::class);
    }
}
