<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MigrationLog extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['transaction_date' => 'date'];

    public function resident() { return $this->belongsTo(Inhabitant::class, 'resident_id'); }
    public function sourceRecord() { return $this->belongsTo(MigrationRecord::class, 'source_record_id'); }

    public function scopeActualRecords($query)
    {
        // Demo profiles ug demo source events dili gamiton bisan naa sila sa old database.
        return $query->whereHas('resident', fn ($q) => $q->withoutMigrationDemo()
            ->whereColumn('inhabitants.barangay_id', 'migration_logs.barangay_id'))
            ->where(fn ($q) => $q->whereNull('source_record_id')
                ->orWhereHas('sourceRecord', fn ($source) => $source->withoutDemo()
                    ->whereColumn('migration_records.barangay_id', 'migration_logs.barangay_id')
                    ->whereColumn('migration_records.inhabitant_id', 'migration_logs.resident_id')
                    ->whereColumn('migration_records.type', 'migration_logs.migration_type')
                    ->whereColumn('migration_records.movement_date', 'migration_logs.transaction_date')));
    }
}
