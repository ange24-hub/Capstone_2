<?php

namespace App\Services;

use App\Models\Barangay;
use App\Models\BarangayRbiUpdate;
use App\Models\NewInhabitant;
use App\Models\User;
use App\Support\HouseholdRbi;
use App\Support\RbiEntryClassification;
use Illuminate\Support\Carbon;

/** Read the same forms and imported history used by all secretary Report History pages. */
class MunicipalRbiReportHistory
{
    public function __construct(private RbiAgeSuggestion $ages, private RbiDuplicateEntries $duplicates, private RbiReportMonth $months) {}

    public function build(bool $ageEstimates = false): array
    {
        $areas = Barangay::orderBy('name')->get();
        $byName = $areas->keyBy(fn ($area) => mb_strtolower(trim($area->name)));
        $raw = NewInhabitant::whereIn('barangay_id', $areas->modelKeys())->orderBy('id')->get()->reject(fn ($row) => $this->demo($row->getAttributes()));
        $reports = BarangayRbiUpdate::with('barangayUser:id,role,barangay_id')->orderBy('id')->get()
            ->reject(fn ($report) => count($report->rows ?? []) > 0 && collect($report->rows)->every(fn ($row) => $this->demo($row)));
        $mapped = [];
        $unmapped = [];
        foreach ($reports as $report) {
            $ownerArea = $report->barangayUser?->role === User::ROLE_BARANGAY ? $report->barangayUser->barangay_id : null;
            $namedArea = $byName->get(mb_strtolower(trim((string) $report->barangay_name)))?->id;
            if (($ownerArea && $namedArea && $ownerArea !== $namedArea) || ! ($ownerArea ?: $namedArea)) {
                $unmapped[] = ['id' => $report->id, 'month' => $report->reporting_month?->format('Y-m')];
            } else {
                $mapped[$ownerArea ?: $namedArea][] = $report;
            }
        }
        $cutoff = now()->startOfMonth();
        $datasets = [];
        $allMonths = [];
        $fingerprints = [];
        foreach ($areas as $area) {
            $areaRaw = $raw->where('barangay_id', $area->id);
            $areaReports = collect($mapped[$area->id] ?? []);
            $entries = [];
            $keys = [];
            $links = [];
            $monthReports = [];
            $recorded = [];
            $undated = 0;
            $future = 0;
            $duplicateMonths = [];
            foreach ($areaRaw as $record) {
                $month = $this->months->resolve($record->reporting_month?->format('Y-m'), $record->month_submitted);
                if (! $month) {
                    $undated++;

                    continue;
                }
                if ($month >= $cutoff->format('Y-m')) {
                    $future++;

                    continue;
                }
                $row = HouseholdRbi::normalize($record->getAttributes());
                $row['birth_date'] = $record->birth_date?->format('Y-m-d') ?? '';
                $index = count($entries);
                $entries[] = ['month' => $month, 'row' => $row, 'history_id' => $record->id, 'report_id' => null];
                $links[$record->id] = $index;
                foreach ($this->keys($row) as $key) {
                    if (isset($keys[$month.'|'.$key])) {
                        $duplicateMonths[$month] = true;
                    }
                    $keys[$month.'|'.$key] = $index;
                }
                $recorded[$month] = true;
            }
            foreach ($areaReports as $report) {
                $month = $this->months->resolve($report->reporting_month?->format('Y-m'));
                if (! $month) {
                    $undated += count($report->rows ?? []);

                    continue;
                }
                if ($month >= $cutoff->format('Y-m')) {
                    $future += count($report->rows ?? []);

                    continue;
                }
                $monthReports[$month][] = $report->id;
                $recorded[$month] = true;
                $withinReport = [];
                foreach ($report->rows ?? [] as $entry) {
                    if ($this->demo($entry)) {
                        continue;
                    }
                    $entry = HouseholdRbi::normalize($entry);
                    $match = $links[(int) ($entry['history_record_id'] ?? 0)] ?? null;
                    if ($match !== null && $entries[$match]['month'] !== $month) {
                        $match = null;
                    }
                    foreach ($this->keys($entry) as $key) {
                        if (isset($withinReport[$key])) {
                            $duplicateMonths[$month] = true;
                        }
                        $withinReport[$key] = true;
                        $match ??= $keys[$month.'|'.$key] ?? null;
                    }
                    if ($match !== null) {
                        $old = $entries[$match]['row'];
                        $entries[$match]['row'] = array_replace($old, $entry);
                        if (empty($entry['birth_date'])) {
                            $entries[$match]['row']['birth_date'] = $old['birth_date'] ?? '';
                        }
                        $entries[$match]['report_id'] = $report->id;
                    } else {
                        $match = count($entries);
                        $entries[] = ['month' => $month, 'row' => $entry, 'history_id' => null, 'report_id' => $report->id];
                    }
                    foreach ($this->keys($entry) as $key) {
                        $keys[$month.'|'.$key] = $match;
                    }
                }
            }
            $grouped = [];
            foreach ($entries as $entry) {
                $row = $entry['row'];
                $suggestion = $this->ages->forMonth($row['birth_date'] ?? null, $entry['month']);
                $identified = $this->keys($row) !== [] && ! empty($row['first_name']) && ! empty($row['last_name']);
                $confirmed = $identified && RbiEntryClassification::isClassified($row, $entry['month']);
                $estimated = $identified && ! $confirmed && empty($row['entry_type']) && $ageEstimates && $suggestion['type'] !== null;
                $type = $confirmed ? $row['entry_type'] : ($estimated ? $suggestion['type'] : null);
                $grouped[$entry['month']][] = $entry + ['suggestion' => $suggestion, 'confirmed' => $confirmed, 'estimated' => $estimated, 'type' => $type];
            }
            ksort($grouped);
            $seenArrivals = [];
            foreach ($grouped as $month => $monthlyEntries) {
                foreach ($monthlyEntries as $entry) {
                    if ($entry['type'] !== 'in_migration') {
                        continue;
                    }
                    foreach ($this->keys($entry['row']) as $key) {
                        if (isset($seenArrivals[$key])) {
                            $duplicateMonths[$month] = true;
                        }
                        $seenArrivals[$key] = true;
                    }
                }
            }
            $months = array_keys($recorded);
            sort($months);
            $datasets[$area->id] = ['area' => $area, 'entries' => $grouped, 'months' => $months, 'reports' => $monthReports,
                'duplicates' => $duplicateMonths, 'raw_count' => $areaRaw->count(), 'form_count' => $areaReports->count(), 'undated' => $undated, 'future' => $future];
            $allMonths = array_merge($allMonths, $months);
            $fingerprints[] = [$area->id, $areaRaw->map->getAttributes()->all(), $areaReports->map->getAttributes()->all()];
        }
        sort($allMonths);
        $rows = [];
        $coverage = [];
        if ($allMonths) {
            $first = Carbon::createFromFormat('!Y-m', $allMonths[0]);
            $last = Carbon::createFromFormat('!Y-m', end($allMonths));
            for ($date = $first->copy(); $date->lte($last); $date->addMonth()) {
                $month = $date->format('Y-m');
                $count = $complete = $needsReview = $estimated = $births = $late = $observed = 0;
                $areaRows = [];
                foreach ($datasets as $id => $dataset) {
                    $entries = $dataset['entries'][$month] ?? [];
                    $ownMonths = $dataset['months'];
                    $gap = $ownMonths && $month > $ownMonths[0] && $month < end($ownMonths) && ! in_array($month, $ownMonths, true);
                    $known = in_array($month, $ownMonths, true) || $gap;
                    $pending = count(array_filter($entries, fn ($entry) => $entry['type'] === null));
                    $valid = $known && ! $pending && empty($dataset['duplicates'][$month]) && count($dataset['reports'][$month] ?? []) <= 1;
                    $arrivals = count(array_filter($entries, fn ($entry) => $entry['type'] === 'in_migration'));
                    $areaRows[$id] = ['complete' => $valid, 'count' => $valid ? $arrivals : null, 'needs_review' => $pending,
                        'entries' => count($entries), 'gap_zero' => (bool) $gap];
                    $complete += (int) $valid;
                    $count += $arrivals;
                    $needsReview += $pending;
                    $estimated += count(array_filter($entries, fn ($entry) => $entry['estimated']));
                    $births += count(array_filter($entries, fn ($entry) => $entry['type'] === 'birth'));
                    $late += count(array_filter($entries, fn ($entry) => $entry['type'] === 'late_registration'));
                    $observed += count($entries);
                }
                $orphaned = count(array_filter($unmapped, fn ($report) => $report['month'] === $month));
                $isComplete = $complete === $areas->count() && $areas->isNotEmpty() && ! $orphaned;
                $rows[] = ['month' => $month, 'label' => $date->format('M Y'), 'count' => $isComplete ? $count : null,
                    'known_count' => $count, 'complete' => $isComplete, 'complete_areas' => $complete, 'area_count' => $areas->count(),
                    'needs_review' => $needsReview, 'estimated_entries' => $estimated, 'births' => $births, 'late_registrations' => $late,
                    'recorded_entries' => $observed, 'unmapped_reports' => $orphaned, 'areas' => $areaRows];
            }
        }
        foreach ($datasets as $id => $dataset) {
            $coverage[] = ['id' => $id, 'name' => $dataset['area']->name, 'history_entries' => $dataset['raw_count'], 'monthly_forms' => $dataset['form_count'],
                'recorded_months' => count($dataset['months']), 'first_month' => $dataset['months'][0] ?? null, 'last_month' => $dataset['months'] ? end($dataset['months']) : null,
                'undated' => $dataset['undated'], 'future' => $dataset['future']];
        }

        return ['rows' => $rows, 'coverage' => $coverage, 'datasets' => $datasets, 'area_count' => $areas->count(), 'recorded_months' => count(array_unique($allMonths)),
            'age_estimates' => $ageEstimates, 'unmapped_reports' => $unmapped,
            'fingerprint' => hash('sha256', json_encode(['municipal-history-v1', $ageEstimates, $fingerprints], JSON_THROW_ON_ERROR))];
    }

    private function keys(array $row): array
    {
        unset($row['household_head']);

        return $this->duplicates->keys($row, 'rows');
    }

    private function demo(array $row): bool
    {
        return ($row['remarks'] ?? '') === MigrationForecastHistory::DEMO_MARKER || ($row['household_number'] ?? '') === 'DEMO-MIGRATION-V1';
    }
}
