<?php

namespace App\Services;

class ShortHistoryForecast
{
    public const MONTHS = 7;

    public const INITIAL_TRAINING = 3;

    public const TEST_MONTHS = 4;

    private const METHODS = [
        'last_month' => ['label' => 'Last-month baseline', 'alpha' => null],
        'moving_average_3' => ['label' => '3-month moving average', 'alpha' => null],
        'smoothing_0.2' => ['label' => 'Exponential smoothing (α=0.2)', 'alpha' => 0.2],
        'smoothing_0.5' => ['label' => 'Exponential smoothing (α=0.5)', 'alpha' => 0.5],
        'smoothing_0.8' => ['label' => 'Exponential smoothing (α=0.8)', 'alpha' => 0.8],
    ];

    /** Select using only the third month; the four later test targets never choose the method. */
    public function evaluate(array $rows): array
    {
        $unavailable = ['ready' => false, 'required_months' => self::MONTHS, 'available_months' => count(array_filter($rows, fn ($row) => $row['complete'] ?? false))];
        if (count($rows) !== self::MONTHS) {
            return $unavailable;
        }
        $previous = null;
        foreach ($rows as $row) {
            $date = \DateTimeImmutable::createFromFormat('!Y-m', $row['month'] ?? '');
            if (! $date || $date->format('Y-m') !== ($row['month'] ?? '') || ($row['complete'] ?? false) !== true
                || ! is_int($row['count'] ?? null) || $row['count'] < 0
                || ($previous && $previous->modify('+1 month')->format('Y-m') !== $row['month'])) {
                return $unavailable;
            }
            $previous = $date;
        }
        $values = array_column($rows, 'count');
        $validation = [];
        foreach (self::METHODS as $key => $method) {
            $prediction = $this->predict(array_slice($values, 0, 2), $key);
            $validation[$key] = ['prediction' => $prediction, 'error' => abs($values[2] - $prediction)];
        }
        $chosen = 'moving_average_3';
        foreach ($validation as $key => $score) {
            if ($score['error'] < $validation[$chosen]['error'] - 1e-9) {
                $chosen = $key;
            }
        }
        $comparison = [];
        foreach (self::METHODS as $key => $method) {
            $tests = $this->backtest($rows, $values, $key);
            $comparison[] = ['method_key' => $key, 'method' => $method['label'], 'selected' => $key === $chosen,
                'validation_prediction' => $validation[$key]['prediction'], 'validation_error' => $validation[$key]['error'],
                'test_mae' => array_sum(array_column($tests, 'absolute_error')) / self::TEST_MONTHS];
        }
        $backtest = $this->backtest($rows, $values, $chosen);
        $mae = array_sum(array_column($backtest, 'absolute_error')) / self::TEST_MONTHS;
        $baselineMae = array_sum(array_map(fn ($row) => abs($row['actual'] - $row['baseline']), $backtest)) / self::TEST_MONTHS;

        return ['ready' => true, 'required_months' => self::MONTHS, 'available_months' => self::MONTHS,
            'method_key' => $chosen, 'method' => self::METHODS[$chosen]['label'], 'alpha' => self::METHODS[$chosen]['alpha'], 'initial_training_months' => self::INITIAL_TRAINING,
            'test_months' => self::TEST_MONTHS, 'protocol' => 'earlier_validation_four_rolling_tests_v2', 'horizon' => 1, 'prediction' => $this->predict($values, $chosen),
            'baseline_prediction' => $this->predict($values, 'moving_average_3'),
            'validation_month' => $rows[2]['month'], 'validation_label' => $rows[2]['label'], 'validation_actual' => $values[2],
            'validation_training_through' => $rows[1]['month'], 'validation_error' => $validation[$chosen]['error'], 'comparison' => $comparison,
            'mae' => $mae, 'baseline_mae' => $baselineMae,
            'baseline_comparison' => abs($mae - $baselineMae) < 1e-9 ? 'equal' : ($mae < $baselineMae ? 'better' : 'worse'),
            'source_start' => $rows[0]['month'], 'source_end' => end($rows)['month'],
            'target_month' => $previous->modify('+1 month')->format('Y-m'), 'backtest' => $backtest];
    }

    private function backtest(array $rows, array $values, string $method): array
    {
        $backtest = [];
        for ($target = self::INITIAL_TRAINING; $target < self::MONTHS; $target++) {
            $training = array_slice($values, 0, $target);
            $predicted = $this->predict($training, $method);
            $baseline = $this->predict($training, 'moving_average_3');
            $backtest[] = ['month' => $rows[$target]['month'], 'label' => $rows[$target]['label'],
                'training_through' => $rows[$target - 1]['month'], 'training_months' => $target,
                'actual' => $values[$target], 'predicted' => $predicted, 'baseline' => $baseline,
                'absolute_error' => abs($values[$target] - $predicted)];
        }

        return $backtest;
    }

    private function predict(array $values, string $method): float
    {
        if ($method === 'last_month') {
            return (float) end($values);
        }
        if ($method === 'moving_average_3') {
            $recent = array_slice($values, -3);

            return array_sum($recent) / count($recent);
        }
        $alpha = self::METHODS[$method]['alpha'];
        $level = (float) $values[0];
        foreach (array_slice($values, 1) as $value) {
            $level = $alpha * $value + (1 - $alpha) * $level;
        }

        return $level;
    }
}
