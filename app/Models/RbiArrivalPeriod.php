<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RbiArrivalPeriod extends Model
{
    protected $fillable = ['barangay_id', 'start_month', 'end_month', 'monthly_counts', 'source_fingerprint', 'reviewed_by', 'reviewed_at', 'notes'];

    protected $casts = ['monthly_counts' => 'array', 'reviewed_at' => 'datetime'];

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
