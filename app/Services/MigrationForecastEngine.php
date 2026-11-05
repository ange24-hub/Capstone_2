<?php

namespace App\Services;

class MigrationForecastEngine
{
    private const MIN_TRAINING = 6;
    private const MIN_VALIDATION = 3;
    private const TEST_MONTHS = 3;

    public function requiredMonths(int $horizon): int
    {
        if (! in_array($horizon, [1, 2], true)) throw new \InvalidArgumentException('Use a one- or two-month forecast horizon.');
        return self::MIN_TRAINING + self::MIN_VALIDATION + self::TEST_MONTHS + 2 * ($horizon - 1);
    }

    /** Hyperparameters use only the earlier validation window; the final test months stay held out. */
    public function evaluate(array $rows, int $horizon = 1): array
    {
        $required = $this->requiredMonths($horizon);
        if (count($rows) < $required) return ['ready' => false, 'required_months' => $required, 'available_months' => count($rows)];
        $previous = null;
        foreach ($rows as $row) {
            $date = \DateTimeImmutable::createFromFormat('!Y-m', $row['month'] ?? '');
            if (! $date || $date->format('Y-m') !== ($row['month'] ?? '') || ($row['verified'] ?? false) !== true
                || ! is_int($row['count'] ?? null) || $row['count'] < 0
                || ($previous && $previous->modify('+1 month')->format('Y-m') !== $row['month'])) {
                return ['ready' => false, 'required_months' => $required, 'available_months' => count($rows)];
            }
            $previous = $date;
        }
        $values = array_column($rows, 'count');
        $testStart = count($values) - self::TEST_MONTHS;
        $validation = array_slice($values, 0, $testStart - $horizon + 1);
        $methods = ['last_month', 'moving_average_3', 'smoothing_0.2', 'smoothing_0.5', 'smoothing_0.8', 'holt_0.3_0.1', 'holt_0.6_0.2'];
        $scores = [];
        foreach ($methods as $method) $scores[$method] = $this->mae($validation, $method, $horizon);
        // Stable ties prefer the transparent moving-average benchmark.
        $chosen = 'moving_average_3';
        foreach ($methods as $method) if ($scores[$method] < $scores[$chosen] - 1e-9) $chosen = $method;
        $backtest = [];
        for ($target = $testStart; $target < count($values); $target++) {
            $training = array_slice($values, 0, $target - $horizon + 1);
            $predicted = $this->predict($training, $chosen, $horizon);
            $baseline = $this->predict($training, 'moving_average_3', $horizon);
            $backtest[] = ['month' => $rows[$target]['month'], 'label' => $rows[$target]['label'],
                'actual' => $values[$target], 'predicted' => $predicted, 'baseline' => $baseline,
                'absolute_error' => abs($values[$target] - $predicted)];
        }
        $mae = array_sum(array_column($backtest, 'absolute_error')) / count($backtest);
        $baselineMae = array_sum(array_map(fn ($row) => abs($row['actual'] - $row['baseline']), $backtest)) / count($backtest);
        return ['ready' => true, 'required_months' => $required, 'available_months' => count($rows),
            'prediction' => $this->predict($values, $chosen, $horizon), 'method_key' => $chosen,
            'method' => $this->label($chosen), 'validation_mae' => round($scores[$chosen], 3),
            'mae' => round($mae, 3), 'baseline_mae' => round($baselineMae, 3),
            'beats_baseline' => $mae < $baselineMae - 1e-9, 'backtest' => $backtest,
            'baseline_comparison' => abs($mae - $baselineMae) < 1e-9 ? 'equal' : ($mae < $baselineMae ? 'better' : 'worse'),
            'validation_months' => count($validation) - self::MIN_TRAINING - $horizon + 1,
            'source_start' => $rows[0]['month'], 'source_end' => end($rows)['month'], 'horizon' => $horizon];
    }

    private function mae(array $values, string $method, int $horizon): float
    {
        $errors = [];
        for ($target = self::MIN_TRAINING + $horizon - 1; $target < count($values); $target++) {
            $errors[] = abs($values[$target] - $this->predict(array_slice($values, 0, $target - $horizon + 1), $method, $horizon));
        }
        return array_sum($errors) / count($errors);
    }

    private function predict(array $values, string $method, int $horizon): float
    {
        if ($method === 'last_month') return (float) end($values);
        if (str_starts_with($method, 'holt_')) {
            [, $alpha, $beta] = explode('_', $method);
            $level = (float) $values[0];
            $trend = $values[1] - $values[0];
            foreach (array_slice($values, 1) as $value) {
                $previous = $level;
                $level = (float) $alpha * $value + (1 - (float) $alpha) * ($level + $trend);
                $trend = (float) $beta * ($level - $previous) + (1 - (float) $beta) * $trend;
            }
            return max(0, $level + $horizon * $trend);
        }
        if (str_starts_with($method, 'smoothing_')) {
            $alpha = (float) substr($method, strlen('smoothing_'));
            $level = (float) $values[0];
            foreach (array_slice($values, 1) as $value) $level = $alpha * $value + (1 - $alpha) * $level;
            return max(0, $level);
        }
        for ($step = 0; $step < $horizon; $step++) {
            $last = array_slice($values, -3);
            $prediction = array_sum($last) / count($last);
            $values[] = $prediction;
        }
        return max(0, $prediction);
    }

    private function label(string $method): string
    {
        return match ($method) {
            'last_month' => 'Last-month baseline', 'moving_average_3' => '3-month moving average',
            'holt_0.3_0.1' => 'Holt linear trend (α=0.3, β=0.1)', 'holt_0.6_0.2' => 'Holt linear trend (α=0.6, β=0.2)',
            default => 'Exponential smoothing (α='.substr($method, strlen('smoothing_')).')',
        };
    }
}
