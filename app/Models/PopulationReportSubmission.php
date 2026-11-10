<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class PopulationReportSubmission extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['snapshot' => 'array', 'submitted_at' => 'datetime', 'report_year' => 'integer',
        'semester' => 'integer', 'total_records' => 'integer'];

    protected static function booted(): void
    {
        // Submitted snapshots stay immutable when the live registry changes.
        static::updating(fn () => throw new \LogicException('Submitted population reports are immutable.'));
        static::deleting(fn () => throw new \LogicException('Submitted population reports cannot be deleted through the application.'));
    }

    public function getReferenceAttribute(): string { return 'POP-'.str_pad((string) $this->id, 6, '0', STR_PAD_LEFT); }

    public function getIsHistoricalArchiveAttribute(): bool
    {
        return ($this->snapshot['kind'] ?? null) === 'historical_population_photo';
    }

    public function scopeReceivedByMunicipal(Builder $query): Builder
    {
        // Only snapshots created by the barangay submission workflow. Imported
        // historical photographs are private archives, not municipal submissions.
        return $query->whereNotNull('submitted_at')->whereNull('snapshot->kind')->whereNotNull('snapshot->monitoring');
    }

    public function getIsMunicipalSubmissionAttribute(): bool
    {
        return $this->submitted_at !== null && !isset($this->snapshot['kind']) && isset($this->snapshot['monitoring']);
    }
}
