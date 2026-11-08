<?php

namespace App\Services;

use App\Models\Barangay;

/** Identity-free evidence shared by the administrator download and read-only CLI. */
class MigrationForecastEvidence
{
    public function __construct(private MigrationForecastReport $reports) {}

    public function build(bool $allBarangays = false): array
    {
        $scopes = [['id' => null, 'name' => 'All configured barangays']];
        if ($allBarangays) $scopes = array_merge($scopes, Barangay::orderBy('name')->get(['id', 'name'])->toArray());
        $hashes = [];
        foreach ([MigrationForecastReport::class, ValidatedMigrationForecast::class, SeasonalNaiveForecast::class,
            MigrationForecastHistory::class, RbiSavedArrivalHistory::class, MigrationNetForecast::class] as $class) {
            $file = (new \ReflectionClass($class))->getFileName();
            $hashes[class_basename($class)] = hash_file('sha256', $file);
        }
        $result = ['generated_at' => now()->toIso8601String(), 'format' => 'rbim.future_forecast_readiness.v1',
            'source_records_changed' => false, 'implementation_sha256' => $hashes,
            'arrival_basis' => 'Classified arrivals by RBI first-save month; actual arrival month may differ.',
            'selection_protocol' => 'Six earlier validation targets; final three rolling-origin test targets never select models. Seasonal fallback when selection history is insufficient.',
            'accuracy_claim' => 'This export records readiness and measured errors when available. It does not guarantee future accuracy. Unknown errors remain null.',
            'scopes' => []];
        foreach ($scopes as $scope) {
            $report = $this->reports->build($scope['id']);
            $item = $scope + ['source_through' => $report['sourceMonthLabel'], 'target' => $report['predictionMonthLabel']];
            foreach (['in' => ['forecastArrivalSource', 'forecastArrivalEvaluation', 'predictionArrivalError'],
                'out' => ['forecastSource', 'forecastEvaluation', 'predictionError']] as $type => [$sourceKey, $evaluationKey, $errorKey]) {
                $source = $report[$sourceKey];
                $blocked = array_values(array_map(fn ($row) => array_intersect_key($row, array_flip(['month', 'status', 'count', 'duplicates'])),
                    array_filter(array_slice($source['rows'], -12), fn ($row) => ! $row['verified'])));
                $item[$type] = ['recorded_events_in_window' => array_sum(array_column($source['rows'], 'count')),
                    'excluded_demo_events' => $source['excluded_demo_events'],
                    'consecutive_verified_months' => count($source['consecutive']),
                    'forecast_error' => $report[$errorKey], 'evaluation' => $report[$evaluationKey],
                    'blocked_recent_months' => $blocked, 'coverage' => $source['coverage']];
            }
            $item['net_evaluation'] = $report['forecastNetEvaluation'];
            $item['future_estimates'] = $report['forecastMonths'];
            $result['scopes'][] = $item;
        }
        return $result;
    }
}
