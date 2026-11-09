<?php

namespace App\Services;

use App\Models\{MigrationLog, MigrationRecord};
use Illuminate\Support\Facades\Schema;

class StatisticalMigrationLogSync
{
    public function syncRecord(MigrationRecord $record): void
    {
        if (! Schema::hasTable('migration_logs')) return;
        // Original movement_date ra. Dili gamiton ang RBI created_at isip migration date.
        $eligible = MigrationRecord::withoutDemo()->whereKey($record->id)
            ->whereHas('inhabitant', fn ($q) => $q->where('barangay_id', $record->barangay_id))->exists();
        if (! $eligible || ! $record->movement_date || ! in_array($record->type, ['in', 'out'], true)) {
            MigrationLog::where('source_record_id', $record->id)->delete();
            return;
        }
        $log = MigrationLog::firstOrNew(['source_record_id' => $record->id]);
        $log->fill(['barangay_id' => $record->barangay_id, 'resident_id' => $record->inhabitant_id,
            'migration_type' => $record->type, 'transaction_date' => $record->movement_date->format('Y-m-d')]);
        if (! $log->exists || $log->isDirty()) $log->save();
    }

    public function syncBarangay(int $barangayId): int
    {
        $count = 0;
        MigrationRecord::where('barangay_id', $barangayId)->orderBy('id')->chunkById(250, function ($records) use (&$count) {
            foreach ($records as $record) { $this->syncRecord($record); $count++; }
        });
        return $count;
    }
}
