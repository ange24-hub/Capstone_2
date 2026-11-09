<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PopulationReportSubmission extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['snapshot' => 'array', 'submitted_at' => 'datetime', 'report_year' => 'integer',
        'semester' => 'integer', 'total_records' => 'integer'];

    protected static function booted(): void
    {
        // Corrections kinahanglan bag-ong submission; dili ilisan ang nangaging report.
        static::updating(fn () => throw new \LogicException('Submitted population reports are immutable.'));
        static::deleting(fn () => throw new \LogicException('Submitted population reports cannot be deleted through the application.'));
    }

    public function getReferenceAttribute(): string { return 'POP-'.str_pad((string) $this->id, 6, '0', STR_PAD_LEFT); }

    public function getIsHistoricalArchiveAttribute(): bool
    {
        return ($this->snapshot['kind'] ?? null) === 'historical_population_photo';
    }
}
