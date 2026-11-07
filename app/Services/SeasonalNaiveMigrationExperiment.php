<?php

namespace App\Services;

/** A fixed calendar benchmark; the next year repeats the latest observed annual profile. */
class SeasonalNaiveMigrationExperiment
{
    public function __construct(private ProvidedMigrationExperiment $source, private SeasonalNaiveForecast $model = new SeasonalNaiveForecast) {}

    public function train(array $records): array
    {
        // Reuse source validation, then replace all rate parameters with seasonal-profile parameters.
        $result = $this->source->train($records);
        $lastYear = array_slice($records, -12);
        $models = [];
        foreach (['in_migrant', 'out_migrant'] as $key) {
            $models[$key] = ['method_key' => 'seasonal_naive_12', 'method' => 'Seasonal naive (same month last year)',
                'training_months' => count($records), 'training_total' => array_sum(array_column($records, $key)),
                'season_length' => 12, 'seasonal_values' => array_column($lastYear, $key)];
        }
        $forecasts = [];
        foreach ($result['forecasts'] as $i => $row) {
            $row['in_migrant'] = (int) $this->model->predict(array_column($records, 'in_migrant'), $i + 1);
            $row['out_migrant'] = (int) $this->model->predict(array_column($records, 'out_migrant'), $i + 1);
            $row['net_migration'] = $row['in_migrant'] - $row['out_migrant'];
            $forecasts[] = $row;
        }

        return array_replace($result, ['protocol' => 'fixed_seasonal_naive_twelve_month_forecast_v1',
            'method_key' => 'seasonal_naive_12', 'method' => 'Seasonal naive (same month last year)',
            'selection_basis' => 'Fixed calendar benchmark, not parameter selection using target-year errors',
            'target_year_previously_inspected' => true, 'independent_final_test' => false,
            'experiment_design' => 'exploratory_retrospective_model_revision', 'tuned_on_target_actuals' => false,
            'training_seasonal_cycles' => count($records) / 12,
            'refitted_models' => $models, 'forecasts' => $forecasts,
            'annual_forecast' => ['start' => $forecasts[0]['month'], 'end' => end($forecasts)['month'],
                'in_migrant' => array_sum(array_column($forecasts, 'in_migrant')),
                'out_migrant' => array_sum(array_column($forecasts, 'out_migrant')),
                'net_migration' => array_sum(array_column($forecasts, 'net_migration'))]]);
    }
}
