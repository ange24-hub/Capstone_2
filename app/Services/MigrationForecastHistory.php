<?php

namespace App\Services;

use App\Models\Barangay;
use App\Models\MigrationRecord;
use App\Models\MigrationReportingPeriod;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

class MigrationForecastHistory
{
    public const DEMO_MARKER = 'RBIM migration demo v1';

    /** Read only aggregate counts and source fingerprints; no identities enter the model. */
    public function build(?int $barangayId, Carbon $end, int $months = 36, string $type = MigrationRecord::TYPE_OUT): array
    {
        if (! in_array($type, [MigrationRecord::TYPE_IN, MigrationRecord::TYPE_OUT], true)) throw new \InvalidArgumentException('Use arrival or departure events.');
        $arrival = $type === MigrationRecord::TYPE_IN;
        $countField = $arrival ? 'in_count' : 'out_count';
        $fingerprintField = $arrival ? 'in_source_fingerprint' : 'source_fingerprint';
        $verifiedField = $arrival ? 'in_verified_at' : 'verified_at';
        $notesField = $arrival ? 'in_notes' : 'notes';
        $verifier = $arrival ? 'arrivalVerifier' : 'verifier';
        $start = $end->copy()->startOfMonth()->subMonths($months - 1);
        $areas = Barangay::when($barangayId, fn ($q) => $q->whereKey($barangayId))->orderBy('name')->get(['id', 'name']);
        $events = MigrationRecord::query()->leftJoin('inhabitants', 'inhabitants.id', '=', 'migration_records.inhabitant_id')
            ->leftJoin('households', 'households.id', '=', 'inhabitants.household_id')
            ->whereIn('migration_records.barangay_id', $areas->pluck('id'))
            ->where('migration_records.type', $type)
            ->whereBetween('movement_date', [$start->toDateString(), $end->copy()->endOfMonth()->toDateString()])
            ->orderBy('migration_records.id')->get(['migration_records.id', 'migration_records.barangay_id',
                'migration_records.inhabitant_id', 'movement_date', 'migration_records.updated_at',
                'migration_records.reason', 'inhabitants.remarks', 'households.household_number'])
            ->groupBy(fn ($event) => $event->barangay_id.'|'.substr((string) $event->getRawOriginal('movement_date'), 0, 7));
        $storageReady = Schema::hasTable('migration_reporting_periods') && (! $arrival
            || Schema::hasColumns('migration_reporting_periods', ['in_count', 'in_source_fingerprint', 'in_verified_by', 'in_verified_at', 'in_notes']));
        $checks = $storageReady ? MigrationReportingPeriod::with($verifier.':id,name')->whereIn('barangay_id', $areas->pluck('id'))
            ->whereBetween('month', [$start->format('Y-m'), $end->format('Y-m')])->get()->keyBy(fn ($row) => $row->barangay_id.'|'.$row->month) : collect();
        $rows = [];
        $byArea = [];
        $demoTotal = 0;
        for ($index = 0; $index < $months; $index++) {
            $month = $start->copy()->addMonths($index)->format('Y-m');
            $row = ['month' => $month, 'label' => Carbon::createFromFormat('!Y-m', $month)->format('M Y'),
                'count' => 0, 'verified' => $areas->isNotEmpty(), 'verified_areas' => 0, 'area_count' => $areas->count(),
                'demo_count' => 0, 'duplicates' => 0, 'status' => 'Not verified'];
            foreach ($areas as $area) {
                $key = $area->id.'|'.$month;
                $items = $events->get($key, collect());
                $real = $items->reject(fn ($event) => $event->reason === self::DEMO_MARKER
                    || $event->remarks === self::DEMO_MARKER || $event->household_number === 'DEMO-MIGRATION-V1');
                $duplicates = $real->groupBy(fn ($event) => $event->inhabitant_id.'|'.substr((string) $event->getRawOriginal('movement_date'), 0, 10))
                    ->filter(fn ($group) => $group->count() > 1)->sum(fn ($group) => $group->count() - 1);
                $fingerprint = hash('sha256', json_encode($real->map(fn ($event) => [
                    (int) $event->id, (int) $event->inhabitant_id, (string) $event->getRawOriginal('movement_date'),
                    (string) $event->getRawOriginal('updated_at'),
                ])->values()->all(), JSON_THROW_ON_ERROR));
                $check = $checks->get($key);
                $verified = $storageReady && $check?->{$verifiedField} && ! $duplicates
                    && hash_equals((string) $check->{$fingerprintField}, $fingerprint) && $check->{$countField} === $real->count();
                $status = $duplicates ? 'Review duplicate events' : ($verified ? 'Verified complete'
                    : ($check?->{$verifiedField} ? 'Records changed — recheck' : 'Not verified'));
                $areaRow = ['month' => $month, 'label' => $row['label'], 'count' => $real->count(), 'verified' => (bool) $verified,
                    'fingerprint' => $fingerprint, 'demo_count' => $items->count() - $real->count(), 'duplicates' => $duplicates,
                    'status' => $status, 'verified_at' => $check?->{$verifiedField}?->format('M d, Y H:i'),
                    'verified_by' => $check?->{$verifier}?->name, 'notes' => $check?->{$notesField}];
                $byArea[$area->id][] = $areaRow;
                $row['count'] += $real->count();
                $row['demo_count'] += $areaRow['demo_count'];
                $row['duplicates'] += $duplicates;
                $row['verified_areas'] += (int) $verified;
                $row['verified'] = $row['verified'] && $verified;
                if ($barangayId) $row = array_merge($row, $areaRow);
            }
            if (! $barangayId) $row['status'] = $row['verified'] ? 'Verified complete' : $row['verified_areas'].'/'.$row['area_count'].' barangays verified';
            $demoTotal += $row['demo_count'];
            $rows[] = $row;
        }
        $consecutive = [];
        foreach (array_reverse($rows) as $row) {
            if (! $row['verified']) break;
            array_unshift($consecutive, $row);
        }
        $coverage = $areas->map(function ($area) use ($byArea) {
            $series = $byArea[$area->id] ?? [];
            $streak = 0;
            foreach (array_reverse($series) as $row) { if (! $row['verified']) break; $streak++; }
            return ['id' => $area->id, 'name' => $area->name, 'events' => array_sum(array_column($series, 'count')),
                'verified_months' => count(array_filter($series, fn ($row) => $row['verified'])), 'consecutive_months' => $streak];
        })->all();
        return ['rows' => $rows, 'consecutive' => $consecutive, 'coverage' => $coverage,
            'storage_ready' => $storageReady, 'excluded_demo_events' => $demoTotal, 'movement_type' => $type];
    }
}
