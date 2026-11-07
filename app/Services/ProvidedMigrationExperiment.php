<?php

namespace App\Services;

/** Standalone aggregate experiment: no reporting database or verification writes. */
class ProvidedMigrationExperiment
{
    public function __construct(private RollingHistoryBacktest $backtest, private MigrationRateForecast $model) {}

    public function evaluate(array $records, string $method = 'poisson_jeffreys'): array
    {
        if (array_filter($records, fn ($record) => isset($record['dataset']))) {
            throw new \InvalidArgumentException('Explicit train labels require full-history training, without an automatic holdout.');
        }
        $series = $this->series($records);
        $previous = \DateTimeImmutable::createFromFormat('!Y-m', end($series['in_migrant'])['month']);

        $initial = count($records) - 6;
        $evaluations = $models = [];
        foreach ($series as $key => $rows) {
            $evaluations[$key] = $this->backtest->evaluate($rows, $method, $initial, 6);
            if (! $evaluations[$key]['ready']) {
                throw new \RuntimeException('The supplied history is not usable for this experiment.');
            }
            $evaluations[$key]['metrics'] = $this->metrics($evaluations[$key]['backtest']);
            // A distinct post-evaluation refit supplies later estimates, not test predictions.
            $models[$key] = $this->model->fit(array_column($rows, 'count'), $method);
        }
        $netRows = [];
        foreach ($evaluations['in_migrant']['backtest'] as $index => $in) {
            $out = $evaluations['out_migrant']['backtest'][$index];
            $net = $in;
            $net['actual'] = $in['actual'] - $out['actual'];
            $net['predicted'] = $in['predicted'] - $out['predicted'];
            $net['baseline'] = $in['baseline'] - $out['baseline'];
            $net['absolute_error'] = abs($net['actual'] - $net['predicted']);
            unset($net['training_total'], $net['prior'], $net['posterior']);
            $netRows[] = $net;
        }
        $evaluations['net_migration'] = ['method' => 'Predicted in minus predicted out', 'backtest' => $netRows, 'metrics' => $this->metrics($netRows)];

        return ['data_source' => 'USER_PROVIDED_AGGREGATE_EXPERIMENT', 'source_verified' => false,
            'database_access' => false, 'production_model_changed' => false, 'input_records' => $records,
            'protocol' => 'fixed_method_six_rolling_tests_then_separate_full_history_refit_v1', 'method' => $models['in_migrant']['method'],
            'method_key' => $method, 'method_selected_using_test_scores' => false,
            'source_start' => $series['in_migrant'][0]['month'], 'source_end' => $previous->format('Y-m'),
            'initial_training_end' => $series['in_migrant'][$initial - 1]['month'], 'initial_training_months' => $initial,
            'test_start' => $netRows[0]['month'], 'test_end' => end($netRows)['month'], 'test_months' => 6,
            'evaluations' => $evaluations, 'refitted_models' => $models, 'forecasts' => $this->forecasts($models, $previous, 6)];
    }

    public function train(array $records, string $method = 'poisson_jeffreys'): array
    {
        $series = $this->series($records);
        $last = \DateTimeImmutable::createFromFormat('!Y-m', end($series['in_migrant'])['month']);
        $models = [];
        foreach ($series as $key => $rows) {
            $models[$key] = $this->model->fit(array_column($rows, 'count'), $method);
        }
        $forecasts = $this->forecasts($models, $last, 12);

        return ['data_source' => 'USER_PROVIDED_AGGREGATE_EXPERIMENT', 'source_verified' => false,
            'database_access' => false, 'production_model_changed' => false, 'input_records' => $records,
            'mode' => 'train_only', 'protocol' => 'full_history_training_twelve_month_fixed_origin_forecast_v1',
            'method' => $models['in_migrant']['method'], 'method_key' => $method, 'method_selected_using_test_scores' => false,
            'source_start' => $series['in_migrant'][0]['month'], 'source_end' => $last->format('Y-m'),
            'initial_training_end' => $last->format('Y-m'), 'initial_training_months' => count($records),
            'test_start' => null, 'test_end' => null, 'test_months' => 0, 'accuracy_ready' => false,
            'evaluations' => [], 'refitted_models' => $models, 'forecasts' => $forecasts,
            'annual_forecast' => ['start' => $forecasts[0]['month'], 'end' => end($forecasts)['month'],
                'in_migrant' => array_sum(array_column($forecasts, 'in_migrant')),
                'out_migrant' => array_sum(array_column($forecasts, 'out_migrant')),
                'net_migration' => array_sum(array_column($forecasts, 'net_migration'))]];
    }

    private function series(array $records): array
    {
        if (count($records) < 12) {
            throw new \InvalidArgumentException('Provide at least 12 consecutive months.');
        }
        $previous = null;
        $series = ['in_migrant' => [], 'out_migrant' => []];
        foreach ($records as $record) {
            if (isset($record['dataset']) && $record['dataset'] !== 'train') {
                throw new \InvalidArgumentException('Only train rows may enter model fitting. Supply test actuals separately.');
            }
            foreach (['year', 'month_num', 'in_migrant', 'out_migrant', 'net_migration'] as $field) {
                if (! is_int($record[$field] ?? null)) {
                    throw new \InvalidArgumentException('Month, year and all counts must be integers.');
                }
            }
            $month = sprintf('%04d-%02d', $record['year'], $record['month_num']);
            $date = \DateTimeImmutable::createFromFormat('!Y-m', $month);
            if (! $date || $date->format('Y-m') !== $month || $record['year'] < 1900 || $record['year'] > 2099
                || strcasecmp($record['month'] ?? '', $date->format('F')) !== 0
                || ($previous && $previous->modify('+1 month')->format('Y-m') !== $month)) {
                throw new \InvalidArgumentException('Provide consecutive unique months with matching year, month number and English month name.');
            }
            if ($record['in_migrant'] < 0 || $record['out_migrant'] < 0 || $record['net_migration'] !== $record['in_migrant'] - $record['out_migrant']) {
                throw new \InvalidArgumentException('In/out counts must be nonnegative; net must equal in minus out.');
            }
            foreach (array_keys($series) as $key) {
                $series[$key][] = ['month' => $month, 'label' => $date->format('M Y'), 'count' => $record[$key], 'complete' => true];
            }
            $previous = $date;
        }

        return $series;
    }

    private function forecasts(array $models, \DateTimeImmutable $previous, int $months): array
    {
        $forecasts = [];
        for ($step = 1; $step <= $months; $step++) {
            $date = $previous->modify('+'.$step.' months');
            $forecasts[] = ['month' => $date->format('Y-m'), 'label' => $date->format('M Y'),
                'in_migrant' => $models['in_migrant']['prediction'], 'out_migrant' => $models['out_migrant']['prediction'],
                'net_migration' => $models['in_migrant']['prediction'] - $models['out_migrant']['prediction'], 'actual' => null];
        }

        return $forecasts;
    }

    private function metrics(array $rows): array
    {
        $absolute = array_sum(array_column($rows, 'absolute_error'));
        $actual = array_sum(array_map(fn ($row) => abs($row['actual']), $rows));

        return ['mae' => $absolute / count($rows),
            'rmse' => sqrt(array_sum(array_map(fn ($row) => ($row['actual'] - $row['predicted']) ** 2, $rows)) / count($rows)),
            'wape_percent' => $actual > 0 ? 100 * $absolute / $actual : null,
            'baseline_mae' => array_sum(array_map(fn ($row) => abs($row['actual'] - $row['baseline']), $rows)) / count($rows),
            'absolute_error_total' => $absolute, 'absolute_actual_total' => $actual];
    }
}
