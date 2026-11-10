<?php

namespace App\Services;

use App\Models\{Barangay, ForecastLog, MigrationLog, MigrationRecord};
use Illuminate\Support\Facades\Schema;

/** Read-only movement history. Encoding, import and RBI save dates are never event dates. */
class RecordedMigrationHistory
{
    public function build(?int $barangayId, int $year): array
    {
        $calendar = now(config('migration_forecast.timezone', 'Asia/Manila'));
        $areas = Barangay::when($barangayId, fn ($query) => $query->whereKey($barangayId))->orderBy('name')->get(['id','name']);
        $records = MigrationRecord::withoutDemo()->with(['inhabitant','barangay'])
            ->whereIn('barangay_id', $areas->modelKeys())->whereIn('type', ['in','out'])
            ->whereHas('inhabitant', fn ($query) => $query->whereColumn('inhabitants.barangay_id','migration_records.barangay_id'))
            ->whereYear('movement_date', $year)->whereDate('movement_date', '<=', $calendar->toDateString())
            ->orderBy('id')->get();
        $ledgerReady = Schema::hasTable('migration_logs');
        $ledger = $ledgerReady
            ? MigrationLog::actualRecords()->with('resident')->whereIn('barangay_id', $areas->modelKeys())
                ->whereIn('migration_type', ['in','out'])->whereYear('transaction_date', $year)
                ->whereDate('transaction_date', '<=', $calendar->toDateString())->orderBy('id')->get()
            : collect();
        $checks = Schema::hasTable('forecast_logs') ? ForecastLog::whereIn('barangay_id', $areas->modelKeys())
            ->whereBetween('log_month', [sprintf('%04d-01',$year),sprintf('%04d-12',$year)])
            ->whereNotNull('actual_verified_at')->get()->keyBy(fn ($log) => $log->barangay_id.'|'.$log->log_month.'|'.$log->migration_type) : collect();

        $events = []; $duplicates = 0;
        foreach ($records as $record) {
            $key = $record->barangay_id.'|'.$record->inhabitant_id.'|'.$record->type.'|'.$record->movement_date->toDateString();
            if (isset($events[$key])) { $duplicates++; continue; }
            $events[$key] = ['barangay_id'=>$record->barangay_id, 'resident_id'=>$record->inhabitant_id,
                'resident'=>$record->inhabitant->fullName(), 'barangay'=>$record->barangay->name,
                'type'=>$record->type, 'date'=>$record->movement_date, 'origin'=>$record->origin, 'destination'=>$record->destination];
        }
        // Linked ledger rows mirror the originals. Only unlinked, genuinely dated events add observations.
        foreach ($ledger->whereNull('source_record_id') as $log) {
            $key = $log->barangay_id.'|'.$log->resident_id.'|'.$log->migration_type.'|'.$log->transaction_date->toDateString();
            if (isset($events[$key])) { $duplicates++; continue; }
            $events[$key] = ['barangay_id'=>$log->barangay_id, 'resident_id'=>$log->resident_id,
                'resident'=>$log->resident->fullName(), 'barangay'=>$areas->firstWhere('id',$log->barangay_id)->name,
                'type'=>$log->migration_type, 'date'=>$log->transaction_date, 'origin'=>null, 'destination'=>null];
        }
        $events = collect(array_values($events));
        $group = fn ($row, $date, $type) => $row->barangay_id.'|'.$row->{$date}->format('Y-m').'|'.$row->{$type};
        $sourceGroups = $records->groupBy(fn ($row) => $group($row,'movement_date','type'));
        $ledgerGroups = $ledger->groupBy(fn ($row) => $group($row,'transaction_date','migration_type'));
        $eventGroups = $events->groupBy(fn ($row) => $row['barangay_id'].'|'.$row['date']->format('Y-m').'|'.$row['type']);
        $byArea = []; $months = [];
        for ($number = 1; $number <= 12; $number++) {
            $month = sprintf('%04d-%02d',$year,$number);
            $row = ['month'=>$month,'label'=>\DateTimeImmutable::createFromFormat('!Y-m',$month)->format('F'),
                'in'=>0,'out'=>0,'in_complete'=>$areas->isNotEmpty(),'out_complete'=>$areas->isNotEmpty()];
            foreach ($areas as $area) {
                $areaRow = ['in'=>0,'out'=>0];
                foreach (['in','out'] as $type) {
                    $key = $area->id.'|'.$month.'|'.$type;
                    $items = $ledgerGroups->get($key, collect());
                    $count = $eventGroups->get($key, collect())->count();
                    $sourceIds = $sourceGroups->get($key, collect())->pluck('id')->map(fn ($id) => (int)$id)->sort()->values()->all();
                    $linkedIds = $items->whereNotNull('source_record_id')->pluck('source_record_id')->map(fn ($id) => (int)$id)->sort()->values()->all();
                    $hash = hash('sha256', json_encode($items->map(fn ($item) => [$item->id,$item->resident_id,
                        $item->transaction_date->format('Y-m-d'),(string)$item->getRawOriginal('updated_at')])->values()->all(), JSON_THROW_ON_ERROR));
                    $check = $checks->get($key);
                    $complete = $ledgerReady && $month < $calendar->format('Y-m') && $check && $check->actual_value === $count
                        && $items->count() === $count && $sourceIds === $linkedIds
                        && hash_equals((string)$check->actual_source_hash,$hash);
                    $areaRow[$type] = $count;
                    $areaRow[$type.'_complete'] = (bool)$complete;
                    $row[$type] += $count;
                    $row[$type.'_complete'] = $row[$type.'_complete'] && $complete;
                }
                $byArea[$area->id][] = $areaRow;
            }
            foreach (['in','out'] as $type) $row[$type.'_known'] = $row[$type] > 0 || $row[$type.'_complete'];
            $row['net'] = $row['in'] - $row['out'];
            $row['total'] = $row['in'] + $row['out'];
            $row['net_known'] = $row['in_known'] && $row['out_known'];
            $row['status'] = $month > $calendar->format('Y-m') ? 'Future month'
                : ($month === $calendar->format('Y-m') ? 'Month in progress'
                    : ($row['in_complete'] && $row['out_complete'] ? 'Confirmed complete'
                        : ($row['total'] ? 'Recorded events; coverage unconfirmed' : 'No confirmed data')));
            $months[] = $row;
        }
        $months = collect($months);
        $coverage = $areas->map(function ($area) use ($byArea) {
            $rows = collect($byArea[$area->id] ?? []);
            $in = $rows->sum('in'); $out = $rows->sum('out');
            $completeIn = $rows->every(fn ($row) => $row['in_complete']);
            $completeOut = $rows->every(fn ($row) => $row['out_complete']);
            return ['id'=>$area->id,'name'=>$area->name,'barangay'=>$area,'in'=>$in,'out'=>$out,
                'in_known'=>$in > 0 || $completeIn, 'out_known'=>$out > 0 || $completeOut,
                'status'=>$completeIn && $completeOut ? 'Confirmed complete' : ($in+$out ? 'Recorded events; coverage unconfirmed' : 'No confirmed data'),
                'net'=>$in-$out,'total'=>$in+$out];
        });
        return ['months'=>$months,'coverage'=>$coverage,'events'=>$events->sortByDesc('date')->values(),'duplicates'=>$duplicates,
            'in'=>$months->sum('in'),'out'=>$months->sum('out'),
            'in_known'=>$months->sum('in') > 0 || $months->every(fn ($row) => $row['in_complete']),
            'out_known'=>$months->sum('out') > 0 || $months->every(fn ($row) => $row['out_complete'])];
    }
}
