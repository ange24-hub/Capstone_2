<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ForecastLog extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['predicted_value' => 'float', 'actual_value' => 'integer', 'accuracy_rate' => 'float',
        'issued_at' => 'datetime', 'actual_verified_at' => 'datetime', 'history_snapshot' => 'array'];
}
