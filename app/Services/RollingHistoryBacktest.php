<?php

namespace App\Services;

class RollingHistoryBacktest
{
    public function __construct(private MigrationRateForecast $model) {}

    public function evaluate(array $rows, string $method = 'poisson_jeffreys', int $initialMonths = 6, int $testMonths = 6): array
    {
        if (! in_array($method, ['mean_rate', 'poisson_jeffreys'], true) || $initialMonths < 6 || ! in_array($testMonths, [1, 3, 6], true)) {
            throw new \InvalidArgumentException('Use a fixed rate method, at least six initial months and one, three or six test months.');
        }
        $required = $initialMonths + $testMonths;
        $usable = fn ($row) => ($row['complete'] ?? false) === true && is_int($row['count'] ?? null) && $row['count'] >= 0;
        $unavailable = ['ready' => false, 'required_months' => $required, 'available_months' => count(array_filter($rows, $usable))];
        if (count($rows) !== $required) {
            return $unavailable;
        }
        $previous = null;
        foreach ($rows as $row) {
            $date = \DateTimeImmutable::createFromFormat('!Y-m', $row['month'] ?? '');
            if (! $date || $date->format('Y-m') !== ($row['month'] ?? '') || ! $usable($row)
                || ($previous && $previous->modify('+1 month')->format('Y-m') !== $row['month'])) {
                return $unavailable;
            }
            $previous = $date;
        }

        $backtest = [];
        for ($index = $initialMonths; $index < $required; $index++) {
            // The current target and all later observations stay outside this fit.
            $counts = array_column(array_slice($rows, 0, $index), 'count');
            $fit = $this->model->fit($counts, $method);
            $baseline = $this->model->fit($counts, 'mean_rate');
            $backtest[] = ['month' => $rows[$index]['month'], 'label' => $rows[$index]['label'],
                'actual' => $rows[$index]['count'], 'predicted' => $fit['prediction'], 'baseline' => $baseline['prediction'],
                'absolute_error' => abs($rows[$index]['count'] - $fit['prediction']),
                'training_start' => $rows[0]['month'], 'training_through' => $rows[$index - 1]['month'],
                'training_months' => $index, 'training_total' => $fit['training_total'],
                'prior' => $fit['prior'], 'posterior' => $fit['posterior']];
        }
        $initial = $this->model->fit(array_column(array_slice($rows, 0, $initialMonths), 'count'), $method);
        $mae = array_sum(array_column($backtest, 'absolute_error')) / $testMonths;
        $baselineMae = array_sum(array_map(fn ($row) => abs($row['actual'] - $row['baseline']), $backtest)) / $testMonths;

        return ['ready' => true, 'protocol' => 'historical_expanding_one_step_backtest_v1',
            'method_choice' => 'exploratory_fixed_method', 'method_key' => $method, 'method' => $initial['method'],
            'prior' => $initial['prior'], 'initial_training_months' => $initialMonths, 'initial_training_total' => $initial['training_total'],
            'source_start' => $rows[0]['month'], 'source_end' => $rows[$initialMonths - 1]['month'],
            'target_month' => $rows[$initialMonths]['month'], 'test_start' => $rows[$initialMonths]['month'],
            'test_end' => end($rows)['month'], 'forecast_end' => end($rows)['month'],
            'horizon' => 1, 'test_months' => $testMonths, 'observed_months' => $testMonths,
            'mae' => $mae, 'baseline_mae' => $baselineMae, 'mean_rate_mae' => $baselineMae,
            'mae_change_from_mean' => $mae - $baselineMae,
            'actual_total' => array_sum(array_column($backtest, 'actual')),
            'predicted_total' => array_sum(array_column($backtest, 'predicted')),
            'backtest' => $backtest];
    }
}
