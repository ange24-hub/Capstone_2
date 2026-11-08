<?php

namespace App\Services;

use App\Models\Barangay;
use App\Models\BarangayRbiUpdate;
use App\Support\RbiEntryClassification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

/** Monthly classified arrivals by the date each RBI resident row was first saved. */
class RbiSavedArrivalHistory
{
    public function __construct(private RbiDuplicateEntries $duplicates) {}

    public function build(?int $barangayId, Carbon $end, int $months): array
    {
        $areas = Barangay::when($barangayId, fn ($q) => $q->whereKey($barangayId))->orderBy('name')->get(['id', 'name']);
        $byName = $areas->keyBy(fn ($area) => mb_strtolower(trim($area->name)));
        $reports = BarangayRbiUpdate::whereIn('barangay_name', $areas->pluck('name'))->orderBy('created_at')->orderBy('id')->get();
        $sources = [];
        $entries = [];
        $excludedDemo = 0;
        foreach ($reports as $report) {
            $area = $byName->get(mb_strtolower(trim((string) $report->barangay_name)));
            if (! $area) continue;
            $reportRows = $report->rows ?? [];
            $realRows = array_filter($reportRows, fn ($row) => ($row['remarks'] ?? '') !== MigrationForecastHistory::DEMO_MARKER
                && ($row['household_number'] ?? '') !== 'DEMO-MIGRATION-V1');
            if ($reportRows && ! $realRows) {
                $excludedDemo += count($reportRows);
                continue;
            }
            $firstSave = $report->created_at ?? $report->submitted_at;
            if (! $firstSave || $firstSave->gt(now())) continue;
            $savedMonth = $firstSave->copy()->timezone('Asia/Manila')->format('Y-m');
            $meta = ['id' => $report->id, 'first_saved_at' => $firstSave->copy()->timezone('Asia/Manila')->format('M d, Y h:i A'),
                'submitted_at' => $report->submitted_at?->copy()->timezone('Asia/Manila')->format('M d, Y h:i A'),
                'status' => $report->status, 'current_entries' => count($report->rows ?? []),
                'rows_first_saved_this_month' => 0, 'arrivals_this_month' => 0, 'save_batches' => []];
            $sources[$area->id][$savedMonth]['reports'][$report->id] = $meta;
            $reportMonth = $report->reporting_month?->format('Y-m');
            foreach ($report->rows ?? [] as $position => $row) {
                if (($row['remarks'] ?? '') === MigrationForecastHistory::DEMO_MARKER
                    || ($row['household_number'] ?? '') === 'DEMO-MIGRATION-V1') {
                    $excludedDemo++;
                    continue;
                }
                $saved = $this->savedAt($row['first_saved_at'] ?? null, $firstSave);
                if (! $saved || $saved->gt(now())) {
                    $sources[$area->id][$savedMonth]['unclassified'] = ($sources[$area->id][$savedMonth]['unclassified'] ?? 0) + 1;
                    $sources[$area->id][$savedMonth]['reports'][$report->id]['rows_first_saved_this_month']++;
                    continue;
                }
                $month = $saved->timezone('Asia/Manila')->format('Y-m');
                $sources[$area->id][$month]['reports'][$report->id] ??= $meta;
                $sources[$area->id][$month]['reports'][$report->id]['rows_first_saved_this_month']++;
                $batchKey = $saved->toIso8601String();
                $sources[$area->id][$month]['reports'][$report->id]['save_batches'][$batchKey] ??= [
                    'saved_at' => $saved->format('M d, Y h:i A'), 'entries' => 0, 'arrivals' => 0,
                ];
                $sources[$area->id][$month]['reports'][$report->id]['save_batches'][$batchKey]['entries']++;
                $keys = $this->duplicates->keys($row, 'rows');
                $classified = $reportMonth && $keys && filled($row['first_name'] ?? null)
                    && filled($row['last_name'] ?? null) && RbiEntryClassification::isClassified($row, $reportMonth);
                $entries[$area->id][] = ['month' => $month, 'saved_at' => $saved->getTimestamp(), 'batch_key' => $batchKey,
                    'report_id' => $report->id, 'position' => $position, 'keys' => $keys,
                    'classified' => $classified, 'arrival' => $classified && RbiEntryClassification::isArrival($row, $reportMonth),
                    'fingerprint' => hash('sha256', json_encode([$report->id, $position, $row, $report->getRawOriginal('updated_at')], JSON_THROW_ON_ERROR))];
            }
        }

        foreach ($entries as $areaId => &$areaEntries) {
            usort($areaEntries, fn ($a, $b) => [$a['saved_at'], $a['report_id'], $a['position']]
                <=> [$b['saved_at'], $b['report_id'], $b['position']]);
            $seen = [];
            foreach ($areaEntries as $entry) {
                $month = $entry['month'];
                if (! $entry['classified']) {
                    $sources[$areaId][$month]['unclassified'] = ($sources[$areaId][$month]['unclassified'] ?? 0) + 1;
                    continue;
                }
                if (! $entry['arrival']) continue;
                if (array_intersect($entry['keys'], array_keys($seen))) {
                    $sources[$areaId][$month]['duplicates'] = ($sources[$areaId][$month]['duplicates'] ?? 0) + 1;
                    continue;
                }
                foreach ($entry['keys'] as $key) $seen[$key] = true;
                $sources[$areaId][$month]['count'] = ($sources[$areaId][$month]['count'] ?? 0) + 1;
                $sources[$areaId][$month]['reports'][$entry['report_id']]['arrivals_this_month']++;
                $sources[$areaId][$month]['reports'][$entry['report_id']]['save_batches'][$entry['batch_key']]['arrivals']++;
            }
        }
        unset($areaEntries);

        $start = $end->copy()->startOfMonth()->subMonths($months - 1);
        $rows = [];
        $byArea = [];
        $current = now()->timezone('Asia/Manila')->startOfMonth()->format('Y-m');
        for ($i = 0; $i < $months; $i++) {
            $date = $start->copy()->addMonths($i);
            $month = $date->format('Y-m');
            $row = ['month' => $month, 'label' => $date->format('M Y'), 'count' => 0,
                'verified' => $areas->isNotEmpty(), 'verified_areas' => 0, 'area_count' => $areas->count(),
                'demo_count' => 0, 'duplicates' => 0, 'status' => 'No saved RBI source',
                'fingerprint' => hash('sha256', 'no-rbi-save|'.$month), 'verified_at' => null,
                'verified_by' => null, 'notes' => null];
            foreach ($areas as $area) {
                $areaSources = $sources[$area->id] ?? [];
                $first = $areaSources ? min(array_keys($areaSources)) : null;
                $last = $areaSources ? max(array_keys($areaSources)) : null;
                $source = $areaSources[$month] ?? [];
                $gap = $first && $last && $month > $first && $month < $last && ! $source;
                $known = (bool) $source || $gap;
                $unclassified = $source['unclassified'] ?? 0;
                $duplicates = $source['duplicates'] ?? 0;
                $verified = $known && $month < $current && ! $unclassified && ! $duplicates;
                $count = $source['count'] ?? 0;
                $status = $gap ? 'No saved report — zero entries'
                    : ($unclassified ? $unclassified.' RBI entries need classification'
                        : ($duplicates ? 'Review duplicate RBI entries'
                            : ($verified ? 'Classified RBI save count' : 'No complete saved RBI month')));
                $areaRow = array_merge($row, ['count' => $count, 'verified' => $verified,
                    'status' => $status, 'duplicates' => $duplicates,
                    'source_reports' => array_map(function ($report) {
                        $report['save_batches'] = array_values($report['save_batches']);
                        return $report;
                    }, array_values($source['reports'] ?? [])),
                    'fingerprint' => hash('sha256', json_encode([$area->id, $month, $source, $entries[$area->id] ?? []], JSON_THROW_ON_ERROR))]);
                $byArea[$area->id][] = $areaRow;
                $row['count'] += $count;
                $row['verified'] = $row['verified'] && $verified;
                $row['verified_areas'] += (int) $verified;
                $row['duplicates'] += $duplicates;
                if ($barangayId) $row = $areaRow;
            }
            if (! $barangayId) {
                $row['status'] = $row['verified'] ? 'Classified RBI save counts' : $row['verified_areas'].'/'.$areas->count().' barangays complete';
            }
            $rows[] = $row;
        }
        $coverage = $areas->map(function ($area) use ($byArea) {
            $series = $byArea[$area->id] ?? [];
            $streak = 0;
            foreach (array_reverse($series) as $row) {
                if (! $row['verified']) break;
                $streak++;
            }
            return ['id' => $area->id, 'name' => $area->name,
                'events' => array_sum(array_column($series, 'count')),
                'verified_months' => count(array_filter($series, fn ($row) => $row['verified'])),
                'consecutive_months' => $streak];
        })->all();
        $consecutive = [];
        foreach (array_reverse($rows) as $row) {
            if (! $row['verified']) break;
            array_unshift($consecutive, $row);
        }
        return ['rows' => $rows, 'consecutive' => $consecutive, 'coverage' => $coverage,
            'storage_ready' => Schema::hasTable('barangay_rbi_updates'), 'excluded_demo_events' => $excludedDemo,
            'movement_type' => 'in', 'source' => 'classified_rbi_first_save_dates'];
    }

    private function savedAt(?string $value, Carbon $fallback): ?Carbon
    {
        if (! filled($value)) return $fallback->copy();
        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }
}
