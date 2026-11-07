<?php

namespace App\Services;

use App\Models\Barangay;
use App\Models\BarangayRbiUpdate;
use App\Models\NewInhabitant;
use App\Support\HouseholdRbi;
use App\Support\RbiEntryClassification;
use Illuminate\Support\Carbon;

class RbiShortHistorySource
{
    public function forecastWindow(Barangay $area, ?string $firstForecastMonth, int $trainingMonths, int $horizon): array
    {
        if (! in_array($trainingMonths, [6, 12], true) || ! in_array($horizon, [1, 3, 6], true)) {
            throw new \InvalidArgumentException('Use six or twelve training months and one, three or six forecast months.');
        }
        if ($firstForecastMonth === null) {
            $latest = $this->build($area);
            if (! $latest['rows']) {
                return $latest;
            }
            $firstForecastMonth = end($latest['rows'])['month'];
        }
        $end = Carbon::createFromFormat('!Y-m', $firstForecastMonth)->addMonths($horizon - 1)->format('Y-m');

        return $this->build($area, $end, $trainingMonths + $horizon);
    }

    /** Read classified drafts for an explicitly preliminary experiment, without formal verification. */
    public function build(Barangay $area, ?string $endMonth = null, int $months = 7): array
    {
        if (! in_array($months, [7, 9, 12, 13, 15, 18], true)) {
            throw new \InvalidArgumentException('Use a seven-month pilot or a six-/twelve-month fit followed by one, three or six targets.');
        }
        $cutoff = now()->startOfMonth();
        $allHistory = NewInhabitant::where('barangay_id', $area->id)->orderBy('id')->get();
        $history = $allHistory->filter(fn ($record) => $record->reporting_month && $record->reporting_month->lt($cutoff) && ! $this->isDemo($record->getAttributes()));
        $reports = BarangayRbiUpdate::where('barangay_name', $area->name)->whereNotNull('reporting_month')
            ->whereDate('reporting_month', '<', $cutoff->toDateString())->orderBy('id')->get()
            ->reject(fn ($report) => count($report->rows ?? []) > 0 && collect($report->rows)->every(fn ($row) => $this->isDemo($row)));
        $recorded = $history->pluck('reporting_month')->merge($reports->pluck('reporting_month'))->sort();
        $undated = $allHistory->whereNull('reporting_month')->count();
        if ($recorded->isEmpty()) {
            return ['rows' => [], 'undated_entries' => $undated, 'draft_months' => 0, 'fingerprint' => hash('sha256', 'empty|'.$area->id), 'source' => 'classified_rbi_history_experiment'];
        }
        $first = $recorded->first()->copy()->startOfMonth();
        $last = $recorded->last()->copy()->startOfMonth();
        $end = $endMonth ? Carbon::createFromFormat('!Y-m', $endMonth) : $last->copy();
        $start = $end->copy()->subMonths($months - 1);
        $rows = [];
        $fingerprints = [];
        $seenArrivals = [];
        $drafts = 0;
        $duplicates = app(RbiDuplicateEntries::class);
        for ($i = 0; $i < $months; $i++) {
            $date = $start->copy()->addMonths($i);
            $month = $date->format('Y-m');
            $monthly = $reports->filter(fn ($report) => $report->reporting_month->format('Y-m') === $month);
            $raw = $history->filter(fn ($record) => $record->reporting_month->format('Y-m') === $month);
            $gapZero = $monthly->isEmpty() && $raw->isEmpty() && $date->gt($first) && $date->lt($last);
            $row = ['month' => $month, 'label' => $date->format('M Y'), 'count' => null, 'complete' => false,
                'gap_zero' => $gapZero, 'report_id' => null, 'report_status' => null, 'excluded' => 0,
                'unclassified' => 0, 'unknown_origins' => 0, 'status' => 'No recorded source for this month'];
            $fingerprints[] = [$month, $gapZero, $monthly->map(fn ($report) => $report->getAttributes())->values()->all(), $raw->map(fn ($record) => $record->getAttributes())->values()->all()];
            if ($gapZero) {
                $row = array_replace($row, ['count' => 0, 'complete' => true, 'status' => 'No report — zero new entries']);
            } elseif ($monthly->count() !== 1) {
                $row['status'] = $monthly->isNotEmpty() ? 'Review multiple forms for this month' : ($raw->isNotEmpty() ? 'Prepare and classify the saved history entries' : $row['status']);
                $row['unclassified'] = $raw->count();
            } else {
                $report = $monthly->first();
                $row['report_id'] = $report->id;
                $row['report_status'] = $report->status;
                $drafts += (int) ($report->status !== BarangayRbiUpdate::STATUS_SUBMITTED || ! $report->submitted_at);
                $count = 0;
                $coveredIds = [];
                $coveredKeys = [];
                $monthKeys = [];
                $repeated = false;
                foreach ($report->rows ?? [] as $entry) {
                    if ($this->isDemo($entry)) {
                        $row['excluded']++;

                        continue;
                    }
                    $entry = HouseholdRbi::normalize($entry);
                    $keys = $duplicates->keys($entry, 'rows');
                    if (! $keys || empty($entry['first_name']) || empty($entry['last_name'])) {
                        $row['unclassified']++;

                        continue;
                    }
                    if (! empty($entry['history_record_id'])) {
                        $coveredIds[(string) $entry['history_record_id']] = true;
                    }
                    $identity = $entry;
                    unset($identity['household_head']);
                    foreach ($duplicates->keys($identity, 'rows') as $key) {
                        $coveredKeys[$key] = true;
                    }
                    $repeated = $repeated || (bool) array_intersect($keys, array_keys($monthKeys));
                    foreach ($keys as $key) {
                        $monthKeys[$key] = true;
                    }
                    if (! RbiEntryClassification::isClassified($entry, $month)) {
                        $row['unclassified']++;

                        continue;
                    }
                    if (! RbiEntryClassification::isArrival($entry, $month)) {
                        $row['excluded']++;

                        continue;
                    }
                    $repeated = $repeated || (bool) array_intersect($keys, array_keys($seenArrivals));
                    foreach ($keys as $key) {
                        $seenArrivals[$key] = true;
                    }
                    $count++;
                    $row['unknown_origins'] += (int) filter_var($entry['previous_residence_unknown'] ?? false, FILTER_VALIDATE_BOOLEAN);
                }
                $row['unclassified'] += $raw->filter(fn ($record) => ! isset($coveredIds[(string) $record->id]) && ! array_intersect($duplicates->keys($record->getAttributes(), 'rows'), array_keys($coveredKeys)))->count();
                $row['complete'] = $row['unclassified'] === 0 && ! $repeated;
                $row['count'] = $row['complete'] ? $count : null;
                $row['status'] = $repeated ? 'Repeated identity — review the source entries' : ($row['unclassified'] > 0 ? 'Entry classifications need review' : ($report->status === BarangayRbiUpdate::STATUS_SUBMITTED && $report->submitted_at ? 'Classified submitted form' : 'Classified draft — not formally submitted'));
            }
            $rows[] = $row;
        }

        return ['rows' => $rows, 'undated_entries' => $undated, 'draft_months' => $drafts,
            'unknown_origins' => array_sum(array_column($rows, 'unknown_origins')),
            'fingerprint' => hash('sha256', json_encode(['short-history-v1', $area->id, $fingerprints], JSON_THROW_ON_ERROR)),
            'source' => 'classified_rbi_history_experiment'];
    }

    private function isDemo(array $row): bool
    {
        return ($row['remarks'] ?? '') === MigrationForecastHistory::DEMO_MARKER || ($row['household_number'] ?? '') === 'DEMO-MIGRATION-V1';
    }
}
