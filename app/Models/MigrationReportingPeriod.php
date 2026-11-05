<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MigrationReportingPeriod extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['verified_at' => 'datetime', 'out_count' => 'integer', 'in_verified_at' => 'datetime', 'in_count' => 'integer'];

    public function verifier()
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function arrivalVerifier()
    {
        return $this->belongsTo(User::class, 'in_verified_by');
    }
}
