<?php

namespace App\Services;

/** Fixed twelve-month calendar model shared by live forecasts and CSV experiments. */
class SeasonalNaiveForecast
{
    public const TRAINING_MONTHS = 12;

    public const TEST_MONTHS = 3;

    public function predict(array $counts, int $horizon = 1): float
    {
        if ($horizon < 1 || $horizon > 12 || count($counts) < self::TRAINING_MONTHS
            || array_filter($counts, fn ($count) => ! is_int($count) || $count < 0)) {
            throw new \InvalidArgumentException('Use twelve complete nonnegative monthly counts and a horizon from one to twelve months.');
        }

        return (float) $counts[count($counts) - self::TRAINING_MONTHS + $horizon - 1];
    }

    /** Forecast readiness and held-out error readiness are deliberately separate. */
    public function evaluate(array $rows, int $horizon = 1): array
    {
        if ($horizon < 1 || $horizon > 12) {
            throw new \InvalidArgumentException('Use a forecast horizon from one to twelve months.');
        }
        $testRequired = self::TRAINING_MONTHS + $horizon - 1 + self::TEST_MONTHS;
        $unavailable = ['ready' => false, 'required_months' => self::TRAINING_MONTHS, 'available_months' => count($rows),
            'evaluation_required_months' => $testRequired, 'backtest_ready' => false, 'method_key' => 'seasonal_naive_12',
            'method' => 'Seasonal naive (same month last year)'];
        if (count($rows) < self::TRAINING_MONTHS) {
            return $unavailable;
        }
        $previous = null;
        foreach ($rows as $row) {
            $date = \DateTimeImmutable::createFromFormat('!Y-m', $row['month'] ?? '');
            if (! $date || $date->format('Y-m') !== ($row['month'] ?? '') || ($row['verified'] ?? false) !== true
                || ! is_int($row['count'] ?? null) || $row['count'] < 0
                || ($previous && $previous->modify('+1 month')->format('Y-m') !== $row['month'])) {
                return $unavailable;
            }
            $previous = $date;
        }
        $values = array_column($rows, 'count');
        $backtest = [];
        if (count($rows) >= $testRequired) {
            for ($target = count($rows) - self::TEST_MONTHS; $target < count($rows); $target++) {
                $origin = $target - $horizon;
                $prefix = array_slice($values, 0, $origin + 1);
                $prediction = $this->predict($prefix, $horizon);
                $backtest[] = ['month' => $rows[$target]['month'], 'label' => $rows[$target]['label'],
                    'actual' => $values[$target], 'predicted' => $prediction, 'baseline' => $this->movingAverage($prefix, $horizon),
                    'absolute_error' => abs($values[$target] - $prediction), 'training_through' => $rows[$origin]['month'],
                    'reference_month' => $rows[$target - self::TRAINING_MONTHS]['month'], 'horizon' => $horizon];
            }
        }
        $mae = $backtest ? array_sum(array_column($backtest, 'absolute_error')) / count($backtest) : null;
        $baselineMae = $backtest ? array_sum(array_map(fn ($row) => abs($row['actual'] - $row['baseline']), $backtest)) / count($backtest) : null;

        return array_replace($unavailable, ['ready' => true, 'prediction' => $this->predict($values, $horizon),
            'protocol' => 'fixed_seasonal_naive_verified_history_v1', 'model_choice' => 'fixed_seasonal_naive',
            'backtest_ready' => $backtest !== [], 'backtest' => $backtest, 'training_months' => self::TRAINING_MONTHS,
            'mae' => $mae === null ? null : round($mae, 3), 'baseline_mae' => $baselineMae === null ? null : round($baselineMae, 3),
            'beats_baseline' => $mae === null ? null : $mae < $baselineMae - 1e-9,
            'baseline_comparison' => $mae === null ? 'not_evaluated' : (abs($mae - $baselineMae) < 1e-9 ? 'equal' : ($mae < $baselineMae ? 'better' : 'worse')),
            'validation_months' => 0, 'validation_mae' => null,
            'source_start' => $rows[0]['month'], 'source_end' => end($rows)['month'],
            'fit_start' => $rows[count($rows) - self::TRAINING_MONTHS]['month'],
            'reference_month' => $rows[count($rows) - self::TRAINING_MONTHS + $horizon - 1]['month'], 'horizon' => $horizon]);
    }

    private function movingAverage(array $values, int $horizon): float
    {
        for ($step = 0; $step < $horizon; $step++) {
            $prediction = array_sum(array_slice($values, -3)) / 3;
            $values[] = $prediction;
        }

        return $prediction;
    }
}
