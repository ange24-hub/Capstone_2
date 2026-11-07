<?php

namespace App\Services;

/** Historical fixed-origin forecasts; source drafts never become official verification. */
class TwelveMonthHoldoutForecast
{
    public function __construct(private MigrationForecastEngine $engine, private MigrationRateForecast $rateModel = new MigrationRateForecast) {}

    public function evaluate(array $rows, string $method = 'selected', int $horizon = 1, int $trainingMonths = 12): array
    {
        if (! in_array($method, ['selected', 'mean_rate', 'poisson_jeffreys'], true) || ! in_array($horizon, [1, 3, 6], true)
            || ! in_array($trainingMonths, [6, 12], true) || ($method === 'selected' && ($horizon !== 1 || $trainingMonths !== 12))) {
            throw new \InvalidArgumentException('Use mean_rate or poisson_jeffreys with six or twelve training months and a one-, three- or six-month forecast. Automatic selection requires twelve training months and one target.');
        }
        $training = array_slice($rows, 0, $trainingMonths);
        $unavailable = ['ready' => false, 'required_months' => $trainingMonths, 'available_months' => count(array_filter($training, fn ($row) => $row['complete'] ?? false))];
        if (count($rows) !== $trainingMonths + $horizon) {
            return $unavailable;
        }
        $previous = null;
        foreach ($rows as $index => $row) {
            $date = \DateTimeImmutable::createFromFormat('!Y-m', $row['month'] ?? '');
            if (! $date || $date->format('Y-m') !== ($row['month'] ?? '') || ($previous && $previous->modify('+1 month')->format('Y-m') !== $row['month'])
                || ($index < $trainingMonths && (($row['complete'] ?? false) !== true || ! is_int($row['count'] ?? null) || $row['count'] < 0))) {
                return $unavailable;
            }
            $previous = $date;
        }
        if ($method !== 'selected') {
            return $this->fixedRate($training, array_slice($rows, $trainingMonths), $method);
        }

        // Completeness flags are used only in memory, never written as formal review.
        $calculationRows = array_map(fn ($row) => array_merge($row, ['verified' => true]), $training);
        $fit = $this->engine->evaluate($calculationRows, 1);
        $actual = $this->actual($rows[12]);
        $comparison = [];
        foreach (['last_month', 'moving_average_3', 'smoothing_0.2', 'smoothing_0.5', 'smoothing_0.8', 'holt_0.3_0.1', 'holt_0.6_0.2'] as $candidateMethod) {
            $candidate = $this->engine->evaluate($calculationRows, 1, $candidateMethod);
            $comparison[] = ['method_key' => $candidateMethod, 'method' => $candidate['method'], 'selected' => $candidateMethod === $fit['method_key'],
                'validation_mae' => $candidate['validation_mae'], 'test_mae' => $candidate['mae']];
        }
        $baseline = array_sum(array_column(array_slice($training, -3), 'count')) / 3;
        $internalTests = $fit['backtest'];
        foreach ($internalTests as &$test) {
            $position = array_search($test['month'], array_column($training, 'month'), true);
            $test['training_through'] = $training[$position - 1]['month'];
            $test['training_months'] = $position;
        }
        unset($test);

        return array_merge($fit, ['protocol' => 'twelve_training_one_holdout_v1', 'method_choice' => 'earlier_validation',
            'training_months' => 12, 'training_total' => array_sum(array_column($training, 'count')), 'target_month' => $rows[12]['month'],
            'alpha' => str_starts_with($fit['method_key'], 'smoothing_') ? (float) substr($fit['method_key'], strlen('smoothing_')) : null,
            'backtest' => $internalTests, 'holdout_actual' => $actual, 'holdout_error' => $actual === null ? null : abs($actual - $fit['prediction']),
            'holdout_baseline' => $baseline, 'comparison' => $comparison,
            'forecast_months' => 1, 'forecast_end' => $rows[12]['month'], 'forecast_total' => $fit['prediction'],
            'forecasts' => [['month' => $rows[12]['month'], 'label' => $rows[12]['label'], 'predicted' => $fit['prediction'], 'actual' => $actual, 'absolute_error' => $actual === null ? null : abs($actual - $fit['prediction'])]],
            'validation_start' => $training[6]['label'], 'validation_end' => $training[8]['label'],
            'internal_test_start' => $training[9]['label'], 'internal_test_end' => $training[11]['label']]);
    }

    private function actual(array $row): ?int
    {
        return ($row['complete'] ?? false) === true && is_int($row['count'] ?? null) && $row['count'] >= 0 ? $row['count'] : null;
    }

    private function fixedRate(array $training, array $targets, string $method): array
    {
        $fit = $this->rateModel->fit(array_column($training, 'count'), $method);
        $total = $fit['training_total'];
        $bayesian = $method === 'poisson_jeffreys';
        // Jeffreys' Poisson prior gives Gamma(sum(counts)+1/2, n) in shape/rate form.
        // This fixed mathematical assumption is not a resident or observed arrival.
        // Neither the prior nor the point estimate is tuned using target actuals.
        $prediction = $fit['prediction'];
        $meanPrediction = $total / count($training);
        $forecasts = [];
        foreach ($targets as $row) {
            $actual = $this->actual($row);
            $forecasts[] = ['month' => $row['month'], 'label' => $row['label'], 'predicted' => $prediction,
                'actual' => $actual, 'absolute_error' => $actual === null ? null : abs($actual - $prediction)];
        }
        $observed = array_values(array_filter($forecasts, fn ($row) => $row['actual'] !== null));
        $mae = $observed ? array_sum(array_column($observed, 'absolute_error')) / count($observed) : null;
        $actualTotal = count($observed) === count($forecasts) ? array_sum(array_column($observed, 'actual')) : null;
        $baseline = array_sum(array_column(array_slice($training, -3), 'count')) / 3;
        $meanMae = $observed ? array_sum(array_map(fn ($row) => abs($row['actual'] - $meanPrediction), $observed)) / count($observed) : null;
        $rateComparison = [];
        foreach (['mean_rate' => $meanPrediction, 'poisson_jeffreys' => ($total + 0.5) / count($training)] as $candidate => $estimate) {
            $rateComparison[] = ['method_key' => $candidate, 'method' => $candidate === 'mean_rate' ? 'Mean-rate baseline' : 'Bayesian Poisson (Jeffreys prior)',
                'prediction' => $estimate, 'forecast_total' => $estimate * count($targets), 'observed_months' => count($observed),
                'mae' => $observed ? array_sum(array_map(fn ($row) => abs($row['actual'] - $estimate), $observed)) / count($observed) : null,
                'selected' => $candidate === $method];
        }

        return ['ready' => true, 'required_months' => count($training), 'available_months' => count($training),
            'protocol' => $bayesian ? 'historical_poisson_jeffreys_fixed_origin_v1' : 'historical_mean_rate_fixed_origin_v1',
            'method_choice' => $bayesian ? 'exploratory_fixed_prior' : 'user_selected_baseline',
            'method_key' => $method, 'method' => $bayesian ? 'Bayesian Poisson (Jeffreys prior)' : 'Mean-rate baseline', 'alpha' => null,
            'prior' => $fit['prior'], 'posterior' => $fit['posterior'],
            'rate_comparison' => $rateComparison, 'mean_rate_mae' => $meanMae,
            'mae_change_from_mean' => $mae === null ? null : $mae - $meanMae,
            'training_months' => count($training), 'training_total' => $total,
            'source_start' => $training[0]['month'], 'source_end' => end($training)['month'],
            'target_month' => $targets[0]['month'], 'forecast_end' => end($targets)['month'],
            'horizon' => count($targets), 'forecast_months' => count($targets), 'prediction' => $prediction,
            'forecast_total' => $prediction * count($targets), 'forecasts' => $forecasts,
            'observed_months' => count($observed), 'observed_mae' => $mae, 'actual_total' => $actualTotal,
            'total_absolute_error' => $actualTotal === null ? null : abs($actualTotal - $prediction * count($targets)),
            'holdout_actual' => $forecasts[0]['actual'], 'holdout_error' => $forecasts[0]['absolute_error'],
            'holdout_baseline' => $baseline, 'backtest' => [], 'comparison' => [],
            'mae' => $mae, 'baseline_mae' => $observed ? array_sum(array_map(fn ($row) => abs($row['actual'] - $baseline), $observed)) / count($observed) : null];
    }
}
