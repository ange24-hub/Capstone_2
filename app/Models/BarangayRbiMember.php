<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BarangayRbiMember extends Model
{
    protected $fillable = [
        'barangay_rbi_family_id', 'inhabitant_id', 'inhabitant_name', 'sex', 'birth_date',
        'birth_place', 'civil_status', 'occupation', 'relationship', 'position',
    ];

    protected $casts = ['birth_date' => 'date'];

    public function family(): BelongsTo
    {
        return $this->belongsTo(BarangayRbiFamily::class, 'barangay_rbi_family_id');
    }

    public function inhabitant(): BelongsTo
    {
        return $this->belongsTo(Inhabitant::class);
    }
}
