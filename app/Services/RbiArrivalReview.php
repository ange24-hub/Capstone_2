<?php

namespace App\Services;

use App\Models\Barangay;
use App\Models\BarangayRbiUpdate;
use App\Models\NewInhabitant;
use App\Models\RbiArrivalPeriod;
use App\Support\RbiEntryClassification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

class RbiArrivalReview
{
    public function periods(Barangay $barangay): array
    {
        $allReports = BarangayRbiUpdate::where('barangay_name', $barangay->name)
            ->whereNotNull('reporting_month')->orderBy('reporting_month')->orderBy('id')->get();
        $reports = $allReports->filter(fn ($report) => $report->status === BarangayRbiUpdate::STATUS_SUBMITTED && $report->submitted_at);
        $history = NewInhabitant::where('barangay_id', $barangay->id)->whereNotNull('reporting_month')->orderBy('id')->get();
        $recordedMonths = $reports->pluck('reporting_month')->merge($history->pluck('reporting_month'))
            ->filter(fn ($month) => $month->lte(now()->startOfMonth()))->sort();
        if ($recordedMonths->isEmpty()) {
            return [];
        }
        $checks = Schema::hasTable('rbi_arrival_periods') ? RbiArrivalPeriod::with('reviewer')->where('barangay_id', $barangay->id)->get()->keyBy('start_month') : collect();
        $first = $recordedMonths->first()->copy()->startOfMonth();
        $last = $recordedMonths->last()->copy()->startOfMonth();
        $periods = [];
        for ($start = $first->copy(); $start->lte($last); $start->addMonths(3)) {
            $end = $start->copy()->addMonths(2);
            $months = [];
            $fingerprints = [];
            $seen = [];
            for ($offset = 0; $offset < 3; $offset++) {
                $date = $start->copy()->addMonths($offset);
                $month = $date->format('Y-m');
                $monthly = $reports->filter(fn ($report) => $report->reporting_month->format('Y-m') === $month);
                $allMonthly = $allReports->filter(fn ($report) => $report->reporting_month->format('Y-m') === $month);
                $monthlyHistory = $history->filter(fn ($row) => $row->reporting_month->format('Y-m') === $month);
                // Report History records only months with new entries. A gap bounded by
                // recorded months means zero entries; a draft or unclassified history is not a gap.
                $inferredZero = $allMonthly->isEmpty() && $monthlyHistory->isEmpty()
                    && $date->gt($first) && $date->lt($last) && $date->lt(now()->startOfMonth());
                $pending = $allMonthly->count() - $monthly->count();
                $coveredIds = [];
                $coveredKeys = [];
                foreach ($monthly as $report) {
                    foreach ($report->rows ?? [] as $row) {
                        if (! empty($row['history_record_id'])) {
                            $coveredIds[(string) $row['history_record_id']] = true;
                        }
                        unset($row['household_head']);
                        foreach (app(RbiDuplicateEntries::class)->keys($row, 'rows') as $key) {
                            $coveredKeys[$key] = true;
                        }
                    }
                }
                $unrepresentedHistory = $monthlyHistory->filter(function ($record) use ($coveredIds, $coveredKeys) {
                    if (isset($coveredIds[(string) $record->id])) {
                        return false;
                    }

                    return ! array_intersect(app(RbiDuplicateEntries::class)->keys($record->getAttributes(), 'rows'), array_keys($coveredKeys));
                })->count();
                $available = $inferredZero || ($monthly->isNotEmpty() && $pending === 0 && $unrepresentedHistory === 0);
                $fingerprints[] = [$month, $inferredZero, $allMonthly->map(fn ($report) => [$report->id, $report->getRawOriginal('updated_at'), $report->status, $report->submitted_at, $report->rows])->values()->all(),
                    $monthlyHistory->map(fn ($row) => $row->getAttributes())->values()->all()];
                $eligible = 0;
                $excluded = 0;
                $unclassified = $unrepresentedHistory;
                foreach ($monthly as $report) {
                    foreach ($report->rows ?? [] as $row) {
                        $keys = app(RbiDuplicateEntries::class)->keys($row, 'rows');
                        if (! $keys || empty($row['first_name']) || empty($row['last_name'])) {
                            continue;
                        }
                        if (($row['remarks'] ?? '') === MigrationForecastHistory::DEMO_MARKER || ($row['household_number'] ?? '') === 'DEMO-MIGRATION-V1') {
                            $excluded++;

                            continue;
                        }
                        if (! RbiEntryClassification::isClassified($row, $month)) {
                            $unclassified++;

                            continue;
                        }
                        if (! RbiEntryClassification::isArrival($row, $month)) {
                            $excluded++;

                            continue;
                        }
                        $repeated = (bool) array_intersect($keys, array_keys($seen));
                        foreach ($keys as $key) {
                            $seen[$key] = true;
                        }
                        if ($repeated) {
                            $excluded++;

                            continue;
                        }
                        $eligible++;
                    }
                }
                $sourceStatus = $inferredZero ? 'No report — zero new entries' : ($pending ? 'Draft / reopened form — submit first' : ($unrepresentedHistory ? 'History entries — update, classify and submit the monthly form' : ($monthly->isNotEmpty() ? 'Submitted RBI form' : 'No later report yet')));
                $months[] = ['month' => $month, 'label' => $date->format('F Y'), 'reports' => $monthly->count(), 'report_ids' => $monthly->pluck('id')->values()->all(), 'eligible' => $eligible, 'excluded' => $excluded, 'unclassified' => $unclassified,
                    'inferred_zero' => $inferredZero, 'available' => $available, 'history_entries' => $monthlyHistory->count(), 'source_status' => $sourceStatus];
            }
            $fingerprint = hash('sha256', json_encode(['classified-arrivals-history-gaps-v2', $start->format('Y-m'), $fingerprints], JSON_THROW_ON_ERROR));
            $check = $checks->get($start->format('Y-m'));
            $unclassifiedTotal = array_sum(array_column($months, 'unclassified'));
            $complete = $unclassifiedTotal === 0 && count(array_filter($months, fn ($m) => $m['available'])) === 3 && $end->copy()->endOfMonth()->lt(now()->startOfMonth());
            $valid = $complete && $check?->reviewed_at && $check->end_month === $end->format('Y-m') && hash_equals($fingerprint, $check->source_fingerprint);
            foreach ($months as $m) {
                if (! is_int($check?->monthly_counts[$m['month']] ?? null) || ($check->monthly_counts[$m['month']] ?? -1) < 0 || ($check->monthly_counts[$m['month']] ?? -1) > $m['eligible']) {
                    $valid = false;
                }
            }
            $periods[] = ['start' => $start->format('Y-m'), 'end' => $end->format('Y-m'), 'label' => $start->format('M Y').' – '.$end->format('M Y'),
                'months' => $months, 'complete' => $complete, 'verified' => (bool) $valid, 'fingerprint' => $fingerprint, 'review' => $check,
                'total' => $valid ? array_sum($check->monthly_counts) : null,
                'status' => $valid ? 'Reviewed arrivals' : ($check?->reviewed_at ? 'Source changed — review again' : ($complete ? 'Ready for arrival review' : ($unclassifiedTotal > 0 ? 'Classification needed for '.$unclassifiedTotal.' entries' : 'Awaiting completed monthly sources')))];
        }

        return $periods;
    }

    public function history(?int $barangayId, Carbon $end, int $months): array
    {
        $areas = Barangay::when($barangayId, fn ($q) => $q->whereKey($barangayId))->orderBy('name')->get();
        $all = [];
        foreach ($areas as $area) {
            foreach ($this->periods($area) as $period) {
                foreach ($period['months'] as $m) {
                    $all[$area->id][$m['month']] = ['period' => $period, 'source' => $m];
                }
            }
        }
        $rows = [];
        $coverage = [];
        $byArea = [];
        $start = $end->copy()->startOfMonth()->subMonths($months - 1);
        for ($i = 0; $i < $months; $i++) {
            $date = $start->copy()->addMonths($i);
            $month = $date->format('Y-m');
            $row = ['month' => $month, 'label' => $date->format('M Y'), 'count' => 0, 'verified' => $areas->isNotEmpty(), 'verified_areas' => 0, 'area_count' => $areas->count(),
                'demo_count' => 0, 'duplicates' => 0, 'status' => 'No reviewed RBI arrival period', 'fingerprint' => hash('sha256', 'no-arrival-source|'.$month),
                'verified_at' => null, 'verified_by' => null, 'notes' => null];
            foreach ($areas as $area) {
                $item = $all[$area->id][$month] ?? null;
                $period = $item['period'] ?? null;
                $check = $period['review'] ?? null;
                $verified = (bool) ($period['verified'] ?? false);
                $count = $verified ? $check->monthly_counts[$month] : 0;
                $areaRow = array_merge($row, ['count' => $count, 'verified' => $verified, 'status' => $period['status'] ?? 'No submitted RBI source',
                    'fingerprint' => hash('sha256', json_encode([$period['fingerprint'] ?? '', $check?->monthly_counts ?? [], $month])),
                    'verified_at' => $verified ? $check->reviewed_at->format('M d, Y H:i') : null,
                    'verified_by' => $verified ? $check->reviewer?->name : null, 'notes' => $check?->notes ?? null]);
                $byArea[$area->id][] = $areaRow;
                $row['count'] += $count;
                $row['verified'] = $row['verified'] && $verified;
                $row['verified_areas'] += (int) $verified;
                if ($barangayId) {
                    $row = $areaRow;
                }
            }
            if (! $barangayId) {
                $row['status'] = $row['verified'] ? 'Reviewed RBI arrivals' : $row['verified_areas'].'/'.$areas->count().' barangays reviewed';
            }
            $rows[] = $row;
        }
        foreach ($areas as $area) {
            $series = $byArea[$area->id] ?? [];
            $streak = 0;
            foreach (array_reverse($series) as $r) {
                if (! $r['verified']) {
                    break;
                } $streak++;
            }
            $coverage[] = ['id' => $area->id, 'name' => $area->name, 'events' => array_sum(array_column($series, 'count')),
                'verified_months' => count(array_filter($series, fn ($r) => $r['verified'])), 'consecutive_months' => $streak];
        }
        $consecutive = [];
        foreach (array_reverse($rows) as $r) {
            if (! $r['verified']) {
                break;
            }array_unshift($consecutive, $r);
        }

        return ['rows' => $rows, 'consecutive' => $consecutive, 'coverage' => $coverage, 'storage_ready' => Schema::hasTable('rbi_arrival_periods'),
            'excluded_demo_events' => 0, 'movement_type' => 'in', 'source' => 'submitted_rbi_arrival_periods'];
    }
}
