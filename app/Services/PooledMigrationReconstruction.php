<?php

namespace App\Services;

use App\Models\{Barangay, BarangayRbiUpdate, Inhabitant, MigrationRecord, MigrationReportingPeriod, NewInhabitant, RbiArrivalPeriod};
use App\Support\{RbiEntryClassification, RegistryRemarks};

/** Source reconciliation only: never writes labels, dates or official attestations. */
class PooledMigrationReconstruction
{
    public function __construct(private MunicipalRbiReportHistory $history) {}

    public function build(): array
    {
        $inventory = $this->history->build(false);
        $samples = []; $review = []; $coverage = [];
        $classified = $arrivals = $unknownOrigin = $pending = $duplicates = 0;
        foreach ($inventory['datasets'] as $areaId => $dataset) {
            $usable = 0;
            foreach ($dataset['entries'] as $month => $entries) {
                $unclassified = $count = $other = 0;
                $ambiguous = !empty($dataset['duplicates'][$month]) || count($dataset['reports'][$month] ?? []) > 1;
                foreach ($entries as $position => $entry) {
                    $row = $entry['row'];
                    if ($entry['confirmed']) {
                        $classified++;
                        if ($entry['type'] === 'in_migration') {
                            $arrivals++; $count++;
                            $unknownOrigin += (int) empty(trim((string) ($row['previous_residence'] ?? '')));
                        } else $other++;
                    } else {
                        $pending++; $unclassified++;
                    }
                    $reasons = [];
                    if (!$entry['confirmed']) $reasons[] = empty($row['entry_type']) ? 'Entry classification missing' : 'Classification fields invalid: '.implode('; ', RbiEntryClassification::issues($row, $month));
                    if ($entry['type'] === 'in_migration' && empty(trim((string) ($row['previous_residence'] ?? '')))) $reasons[] = 'Previous residence unknown; municipal boundary cannot be determined';
                    if ($ambiguous) $reasons[] = 'Duplicate identity or conflicting monthly forms';
                    if (!empty($row['birth_date']) && substr($row['birth_date'], 0, 7) > $month) $reasons[] = 'Birth date occurs after source report month; resolve from original source';
                    if ($reasons) $review[] = ['kind' => 'arrival_entry', 'barangay_id' => $areaId, 'barangay' => $dataset['area']->name,
                        'history_id' => $entry['history_id'], 'report_id' => $entry['report_id'], 'entry_position' => $position,
                        'reporting_month' => $month, 'existing_entry_type' => $row['entry_type'] ?? null,
                        'existing_arrival_month' => $row['arrival_month'] ?? null, 'existing_arrival_date' => $row['arrival_date'] ?? null,
                        'review_reasons' => $reasons, 'source_note' => RegistryRemarks::display($row['remarks'] ?? ''),
                        'confirmed_type' => null, 'confirmed_movement_month' => null, 'confirmed_boundary' => null,
                        'evidence_reference' => null, 'reviewed_by' => null];
                }
                $duplicates += (int) $ambiguous;
                // This is the count WITHIN existing classified records, not a complete movement total.
                // Explicit classified non-arrivals establish sample zero; missing months never do.
                if ($entries && !$unclassified && !$ambiguous) {
                    $samples[] = ['barangay_id' => (int) $areaId, 'barangay' => $dataset['area']->name, 'month' => $month,
                        'count' => $count, 'observed_entries' => count($entries), 'non_arrivals_excluded' => $other,
                        'target' => 'existing_classified_arrival_entries_in_observed_source_month',
                        'complete_physical_migration_total' => false, 'classification_independently_verified' => false];
                    $usable++;
                }
            }
            $coverage[] = ['barangay_id' => $areaId, 'barangay' => $dataset['area']->name,
                'history_entries' => $dataset['raw_count'], 'forms' => $dataset['form_count'], 'observed_months' => count($dataset['months']),
                'experimental_sample_rows' => $usable, 'undated_source_entries' => $dataset['undated']];
        }
        foreach (NewInhabitant::whereNull('reporting_month')->get() as $row) {
            if ($row->remarks === MigrationForecastHistory::DEMO_MARKER || $row->household_number === 'DEMO-MIGRATION-V1') continue;
            $review[] = ['kind' => 'undated_new_history', 'history_id' => $row->id, 'barangay_id' => $row->barangay_id,
                'source_position' => $row->source_position, 'source_report_label' => $row->month_submitted,
                'review_reasons' => ['No explicit valid reporting month/year; do not derive it from birth or import date'],
                'confirmed_movement_month' => null, 'evidence_reference' => null, 'reviewed_by' => null];
        }
        $events = MigrationRecord::with(['inhabitant.household'])->orderBy('movement_date')->get()->reject(fn ($e) =>
            $e->reason === MigrationForecastHistory::DEMO_MARKER || $e->inhabitant?->remarks === MigrationForecastHistory::DEMO_MARKER
            || $e->inhabitant?->household?->household_number === 'DEMO-MIGRATION-V1');
        $ledger = $events->map(fn ($e) => ['type' => $e->type, 'barangay_id' => $e->barangay_id,
            'month' => $e->movement_date->format('Y-m'), 'has_origin' => filled($e->origin), 'has_destination' => filled($e->destination)])->values()->all();
        $undatedAway = 0;
        foreach (Inhabitant::movedOutRecords()->with('household')->get() as $person) {
            if ($person->remarks === MigrationForecastHistory::DEMO_MARKER || $person->household?->household_number === 'DEMO-MIGRATION-V1') continue;
            if ($events->contains(fn ($e) => $e->inhabitant_id === $person->id && $e->type === 'out')) continue;
            $undatedAway++;
            $review[] = ['kind' => 'undated_departure', 'inhabitant_id' => $person->id, 'barangay_id' => $person->barangay_id,
                'source_note' => RegistryRemarks::display($person->remarks),
                'review_reasons' => ['Away/transferred status is a snapshot, not a dated departure; confirm date and destination boundary'],
                'confirmed_movement_month' => null, 'confirmed_destination' => null, 'evidence_reference' => null, 'reviewed_by' => null];
        }
        usort($samples, fn ($a,$b) => [$a['month'],$a['barangay_id']] <=> [$b['month'],$b['barangay_id']]);

        return ['aggregate' => ['format' => 'rbim.pooled_actual_review.v1', 'generated_at' => now()->toIso8601String(),
            'source_fingerprint' => $inventory['fingerprint'], 'configured_barangays' => Barangay::count(),
            'reports' => ['total' => BarangayRbiUpdate::count(), 'submitted' => BarangayRbiUpdate::where('status','submitted')->count()],
            'classified_entries' => $classified, 'existing_classified_arrivals' => $arrivals, 'arrival_entries_with_unknown_origin' => $unknownOrigin,
            'unclassified_dated_entries' => $pending, 'ambiguous_area_months' => $duplicates,
            'undated_departure_profiles' => $undatedAway, 'non_demo_dated_events' => $ledger,
            'arrival_review_periods' => RbiArrivalPeriod::count(), 'departure_verified_periods' => MigrationReportingPeriod::whereNotNull('verified_at')->count(),
            'coverage' => $coverage, 'experimental_arrival_samples' => $samples,
            'actual_municipal_in_training_rows' => [], 'actual_municipal_out_training_rows' => [],
            'actual_target_status' => 'No complete physical municipal arrival/departure periods established by this source review. Official verification is never inferred.',
            'source_records_changed' => false, 'synthetic_rows' => 0, 'age_inferred_labels' => 0,
            'sample_caution' => 'Only existing saved classifications in fully classified observed source months. Selection favors reviewed months; draft labels and unknown origins are not independent physical migration ground truth. No missing month is filled with zero.',
            'review_queue_items' => count($review)], 'private_review_queue' => $review];
    }
}
