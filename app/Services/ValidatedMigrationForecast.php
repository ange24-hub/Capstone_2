<?php

namespace App\Services;

/** Select independently for each target, before inspecting the final test outcomes. */
class ValidatedMigrationForecast
{
    public const VALIDATION_MONTHS = 6;
    public const METHODS = [
        'seasonal_naive_12' => 'Seasonal naive (same month last year)',
        'moving_average_3' => '3-month moving average',
        'moving_average_6' => '6-month moving average',
        'moving_average_12' => '12-month moving average',
        'last_month' => 'Last-month baseline',
        'smoothing_0.2' => 'Exponential smoothing (alpha=0.2)',
        'smoothing_0.5' => 'Exponential smoothing (alpha=0.5)',
        'smoothing_0.8' => 'Exponential smoothing (alpha=0.8)',
        'holt_0.3_0.1' => 'Holt linear trend (alpha=0.3, beta=0.1)',
        'holt_0.6_0.2' => 'Holt linear trend (alpha=0.6, beta=0.2)',
    ];

    public function __construct(private SeasonalNaiveForecast $seasonal = new SeasonalNaiveForecast) {}

    public function evaluate(array $rows, int $horizon = 1, ?int $selectionHorizon = null): array
    {
        $selectionHorizon ??= $horizon;
        if ($selectionHorizon < $horizon || $selectionHorizon > 12) {
            throw new \InvalidArgumentException('Selection horizon must cover the evaluation horizon and be at most twelve months.');
        }
        // Also enforces calendar continuity, verified counts and the horizon bounds.
        $base = $this->seasonal->evaluate($rows, $horizon);
        $required = SeasonalNaiveForecast::TRAINING_MONTHS + self::VALIDATION_MONTHS
            + SeasonalNaiveForecast::TEST_MONTHS + 2 * ($selectionHorizon - 1);
        $base += ['selection_ready' => false, 'selection_required_months' => $required,
            'selection_scores' => [], 'selection_through' => null, 'rmse' => null,
            'baseline_rmse' => null, 'seasonal_mae' => null, 'seasonal_rmse' => null,
            'selection_horizon' => $selectionHorizon];
        if (! $base['ready']) return $base;

        $base['rmse'] = $this->errors($base['backtest'], 'predicted')['rmse'];
        $base['baseline_rmse'] = $this->errors($base['backtest'], 'baseline')['rmse'];
        $base['seasonal_mae'] = $base['mae'];
        $base['seasonal_rmse'] = $base['rmse'];
        if (count($rows) < $required) return $base;

        $values = array_column($rows, 'count');
        $testStart = count($rows) - SeasonalNaiveForecast::TEST_MONTHS;
        // Guard band: even the last validation actual is known at the first test origin.
        $validationEnd = $testStart - $selectionHorizon + 1;
        $validationStart = $validationEnd - self::VALIDATION_MONTHS;
        $scores = [];
        foreach (self::METHODS as $key => $label) {
            $validation = $this->backtest($rows, $key, $selectionHorizon, $validationStart, $validationEnd);
            $scores[$key] = ['method' => $label] + $this->errors($validation, 'predicted');
        }
        $chosen = 'seasonal_naive_12';
        $seasonalScore = $scores[$chosen];
        foreach ($scores as $key => $score) {
            // A challenger must improve validation MAE without worsening validation RMSE.
            if ($score['mae'] < $seasonalScore['mae'] - 1e-9 && $score['rmse'] <= $seasonalScore['rmse'] + 1e-9
                && ($score['mae'] < $scores[$chosen]['mae'] - 1e-9
                    || (abs($score['mae'] - $scores[$chosen]['mae']) < 1e-9 && $score['rmse'] < $scores[$chosen]['rmse'] - 1e-9))) {
                $chosen = $key;
            }
        }
        $tests = $this->backtest($rows, $chosen, $horizon, $testStart, count($rows));
        $metrics = $this->errors($tests, 'predicted');
        $baseline = $this->errors($tests, 'baseline');
        $seasonal = $this->errors($tests, 'seasonal');
        $comparison = abs($metrics['mae'] - $baseline['mae']) < 1e-9 ? 'equal'
            : ($metrics['mae'] < $baseline['mae'] ? 'better' : 'worse');
        $fitStart = str_starts_with($chosen, 'moving_average_') ? count($rows) - (int) substr($chosen, 15)
            : ($chosen === 'seasonal_naive_12' ? count($rows) - 12 : ($chosen === 'last_month' ? count($rows) - 1 : 0));

        return array_replace($base, [
            'prediction' => $this->predict($values, $horizon, $chosen), 'method_key' => $chosen, 'method' => self::METHODS[$chosen],
            'protocol' => 'verified_chronological_validation6_test3_v1', 'model_choice' => 'earlier_validation_only',
            'selection_ready' => true, 'selection_scores' => $scores, 'selection_through' => $rows[$validationEnd - 1]['month'],
            'validation_months' => self::VALIDATION_MONTHS, 'validation_mae' => $scores[$chosen]['mae'],
            'validation_rmse' => $scores[$chosen]['rmse'], 'backtest' => $tests, 'mae' => $metrics['mae'], 'rmse' => $metrics['rmse'],
            'baseline_mae' => $baseline['mae'], 'baseline_rmse' => $baseline['rmse'],
            'seasonal_mae' => $seasonal['mae'], 'seasonal_rmse' => $seasonal['rmse'],
            'beats_baseline' => $comparison === 'better', 'baseline_comparison' => $comparison,
            'fit_start' => $rows[$fitStart]['month'], 'training_months' => count($rows) - $fitStart,
            'reference_month' => $chosen === 'seasonal_naive_12' ? $base['reference_month'] : null,
        ]);
    }

    public function predict(array $values, int $horizon = 1, string $method = 'seasonal_naive_12'): float
    {
        if (! isset(self::METHODS[$method])) throw new \InvalidArgumentException('Unknown forecast method.');
        // Keep every prediction under the same checked-input contract.
        $seasonal = $this->seasonal->predict($values, $horizon);
        if ($method === 'seasonal_naive_12') return $seasonal;
        if ($method === 'last_month') return (float) end($values);
        if (str_starts_with($method, 'moving_average_')) {
            $window = (int) substr($method, 15);
            for ($step = 0; $step < $horizon; $step++) {
                $prediction = array_sum(array_slice($values, -$window)) / $window;
                $values[] = $prediction;
            }
            return $prediction;
        }
        if (str_starts_with($method, 'smoothing_')) {
            $alpha = (float) substr($method, 10);
            $level = (float) $values[0];
            foreach (array_slice($values, 1) as $value) $level = $alpha * $value + (1 - $alpha) * $level;
            return max(0, $level);
        }
        [, $alpha, $beta] = explode('_', $method);
        $alpha = (float) $alpha;
        $beta = (float) $beta;
        $level = (float) $values[0];
        $trend = $values[1] - $values[0];
        foreach (array_slice($values, 1) as $value) {
            $previous = $level;
            $level = $alpha * $value + (1 - $alpha) * ($level + $trend);
            $trend = $beta * ($level - $previous) + (1 - $beta) * $trend;
        }
        return max(0, $level + $horizon * $trend);
    }

    private function backtest(array $rows, string $method, int $horizon, int $start, int $end): array
    {
        $tests = [];
        for ($target = $start; $target < $end; $target++) {
            $origin = $target - $horizon;
            $prefix = array_column(array_slice($rows, 0, $origin + 1), 'count');
            $prediction = $this->predict($prefix, $horizon, $method);
            $tests[] = ['month' => $rows[$target]['month'], 'label' => $rows[$target]['label'],
                'actual' => $rows[$target]['count'], 'predicted' => $prediction,
                'baseline' => $this->predict($prefix, $horizon, 'moving_average_3'),
                'seasonal' => $this->predict($prefix, $horizon), 'absolute_error' => abs($rows[$target]['count'] - $prediction),
                'training_through' => $rows[$origin]['month'], 'reference_month' => $rows[$target - 12]['month'], 'horizon' => $horizon];
        }
        return $tests;
    }

    /** Metrics retain full precision; presentation controls rounding. */
    private function errors(array $tests, string $key): array
    {
        if (! $tests) return ['mae' => null, 'rmse' => null];
        $errors = array_map(fn ($row) => $row['actual'] - $row[$key], $tests);
        return ['mae' => array_sum(array_map('abs', $errors)) / count($errors),
            'rmse' => sqrt(array_sum(array_map(fn ($error) => $error ** 2, $errors)) / count($errors))];
    }
}
