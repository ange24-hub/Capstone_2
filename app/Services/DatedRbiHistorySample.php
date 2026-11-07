<?php

namespace App\Services;

use Illuminate\Support\Carbon;

/** Forecast eligible dated source entries, not unobserved municipality-wide movement. */
class DatedRbiHistorySample
{
    public function build(array $inventory): array
    {
        $monthly = [];
        $areas = [];
        $included = $estimated = $births = $late = 0;
        $missingBirthDate = $unclassified = $ambiguous = 0;
        $membership = [];
        foreach ($inventory['datasets'] as $areaId => $dataset) {
            foreach ($dataset['entries'] as $month => $entries) {
                foreach ($entries as $entry) {
                    // The age helper validates the full birth date against the explicit report month.
                    if ($entry['suggestion']['age_months'] === null) {
                        $missingBirthDate++;

                        continue;
                    }
                    if ($entry['type'] === null) {
                        $unclassified++;

                        continue;
                    }
                    if (! empty($dataset['duplicates'][$month]) || count($dataset['reports'][$month] ?? []) > 1) {
                        $ambiguous++;

                        continue;
                    }
                    $monthly[$month] ??= ['count' => 0, 'entries' => 0, 'areas' => []];
                    $monthly[$month]['count'] += (int) ($entry['type'] === 'in_migration');
                    $monthly[$month]['entries']++;
                    $monthly[$month]['areas'][$areaId] = true;
                    $areas[$areaId] = $dataset['area']->name;
                    $included++;
                    $estimated += (int) $entry['estimated'];
                    $births += (int) ($entry['type'] === 'birth');
                    $late += (int) ($entry['type'] === 'late_registration');
                    $membership[] = [$areaId, $month, $entry['history_id'], $entry['report_id'], $entry['type'], $entry['estimated']];
                }
            }
        }
        ksort($monthly);
        $rows = [];
        if ($monthly) {
            $first = Carbon::createFromFormat('!Y-m', array_key_first($monthly));
            $last = Carbon::createFromFormat('!Y-m', array_key_last($monthly));
            for ($date = $first->copy(); $date->lte($last); $date->addMonth()) {
                $month = $date->format('Y-m');
                $sample = $monthly[$month] ?? null;
                // A zero describes this filtered dataset only; it never verifies missing barangay reports.
                $rows[] = ['month' => $month, 'label' => $date->format('M Y'), 'count' => $sample['count'] ?? 0,
                    'complete' => true, 'sample_entries' => $sample['entries'] ?? 0,
                    'sample_areas' => count($sample['areas'] ?? []), 'no_sample_entries' => $sample === null];
            }
        }

        return ['rows' => $rows, 'scope' => 'date_complete_record_sample', 'included_entries' => $included,
            'included_areas' => $areas, 'included_area_count' => count($areas), 'estimated_entries' => $estimated,
            'births_excluded' => $births, 'late_registrations_excluded' => $late,
            'missing_birth_date' => $missingBirthDate, 'unclassified_entries' => $unclassified,
            'ambiguous_entries' => $ambiguous, 'undated_source_rows' => array_sum(array_column($inventory['coverage'], 'undated')),
            'recorded_months' => count($monthly), 'age_estimates' => $inventory['age_estimates'],
            'fingerprint' => hash('sha256', json_encode(['dated-record-sample-v1', $inventory['fingerprint'], $membership], JSON_THROW_ON_ERROR))];
    }
}
