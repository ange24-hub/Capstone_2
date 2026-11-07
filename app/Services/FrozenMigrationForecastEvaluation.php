<?php

namespace App\Services;

/** Scores saved forecasts only; deliberately has no training/model-fitting dependency. */
class FrozenMigrationForecastEvaluation
{
    public function evaluate(array $model, array $actuals): array
    {
        if (($model['data_source'] ?? '') !== 'USER_PROVIDED_AGGREGATE_EXPERIMENT' || ($model['mode'] ?? '') !== 'train_only'
            || count($model['forecasts'] ?? []) !== 12 || count($actuals) !== 12) {
            throw new \InvalidArgumentException('Provide a saved full-history model and twelve separate monthly actuals.');
        }
        $origin = \DateTimeImmutable::createFromFormat('!Y-m', $model['source_end'] ?? '');
        if (! $origin || $origin->format('Y-m') !== $model['source_end']) {
            throw new \InvalidArgumentException('The saved forecast origin is invalid.');
        }
        $baselines = [];
        foreach (['in_migrant', 'out_migrant'] as $key) {
            $fit = $model['refitted_models'][$key] ?? [];
            if (! is_int($fit['training_months'] ?? null) || $fit['training_months'] < 1 || ! is_int($fit['training_total'] ?? null) || $fit['training_total'] < 0) {
                throw new \InvalidArgumentException('Saved training totals are required for the fixed mean-rate comparator.');
            }
            $baselines[$key] = $fit['training_total'] / $fit['training_months'];
        }
        $baselines['net_migration'] = $baselines['in_migrant'] - $baselines['out_migrant'];
        $evaluations = ['in_migrant' => ['backtest' => []], 'out_migrant' => ['backtest' => []], 'net_migration' => ['backtest' => []]];
        foreach ($actuals as $i => $record) {
            if (isset($record['dataset']) && $record['dataset'] !== 'actual') {
                throw new \InvalidArgumentException('Comparison rows must be labeled actual.');
            }
            foreach (['year', 'month_num', 'in_migrant', 'out_migrant', 'net_migration'] as $key) {
                if (! is_int($record[$key] ?? null)) {
                    throw new \InvalidArgumentException('Actual date fields and counts must be integers.');
                }
            }
            $target = $origin->modify('+'.($i + 1).' months');
            $month = sprintf('%04d-%02d', $record['year'], $record['month_num']);
            $forecast = $model['forecasts'][$i];
            if ($month !== $target->format('Y-m') || $forecast['month'] !== $month || strcasecmp($record['month'] ?? '', $target->format('F')) !== 0
                || $record['in_migrant'] < 0 || $record['out_migrant'] < 0 || $record['net_migration'] !== $record['in_migrant'] - $record['out_migrant']) {
                throw new \InvalidArgumentException('Actual rows must match every forecast month, with valid in/out counts and net = in minus out.');
            }
            foreach (array_keys($evaluations) as $key) {
                if (! is_numeric($forecast[$key] ?? null) || ! is_finite((float) $forecast[$key]) || ($key !== 'net_migration' && $forecast[$key] < 0)) {
                    throw new \InvalidArgumentException('Invalid saved prediction.');
                }
                $evaluations[$key]['backtest'][] = ['month' => $month, 'label' => $target->format('M Y'), 'actual' => $record[$key],
                    'predicted' => $forecast[$key], 'baseline' => $baselines[$key], 'training_through' => $model['source_end'],
                    'forecast_horizon' => $i + 1, 'absolute_error' => abs($record[$key] - $forecast[$key])];
            }
            if (abs($forecast['net_migration'] - ($forecast['in_migrant'] - $forecast['out_migrant'])) > 1e-9) {
                throw new \InvalidArgumentException('Saved net prediction must equal predicted in minus predicted out.');
            }
        }
        foreach ($evaluations as &$evaluation) {
            $rows = $evaluation['backtest'];
            $absoluteError = array_sum(array_column($rows, 'absolute_error'));
            $absoluteActual = array_sum(array_map(fn ($row) => abs($row['actual']), $rows));
            $actualTotal = array_sum(array_column($rows, 'actual'));
            $predictedTotal = array_sum(array_column($rows, 'predicted'));
            $evaluation['metrics'] = ['mae' => $absoluteError / 12,
                'rmse' => sqrt(array_sum(array_map(fn ($row) => ($row['actual'] - $row['predicted']) ** 2, $rows)) / 12),
                'wape_percent' => $absoluteActual > 0 ? 100 * $absoluteError / $absoluteActual : null,
                'skill_vs_zero_percent' => $absoluteActual > 0 ? 100 * (1 - $absoluteError / $absoluteActual) : null,
                'baseline_mae' => array_sum(array_map(fn ($row) => abs($row['actual'] - $row['baseline']), $rows)) / 12,
                'absolute_error_total' => $absoluteError, 'absolute_actual_total' => $absoluteActual];
            $evaluation['annual'] = ['actual' => $actualTotal, 'predicted' => $predictedTotal,
                'absolute_error' => abs($actualTotal - $predictedTotal),
                'absolute_percentage_error' => $actualTotal !== 0 ? 100 * abs($actualTotal - $predictedTotal) / abs($actualTotal) : null];
        }
        unset($evaluation);

        // Pool arrivals and departures only: net is derived and must not be counted a second time.
        $pooledError = $evaluations['in_migrant']['metrics']['absolute_error_total'] + $evaluations['out_migrant']['metrics']['absolute_error_total'];
        $pooledActual = $evaluations['in_migrant']['metrics']['absolute_actual_total'] + $evaluations['out_migrant']['metrics']['absolute_actual_total'];

        return array_replace($model, ['mode' => 'external_test', 'protocol' => 'frozen_twelve_month_fixed_origin_evaluation_v1',
            'test_start' => $model['forecasts'][0]['month'], 'test_end' => $model['forecasts'][11]['month'],
            'test_months' => 12, 'accuracy_ready' => true, 'model_refitted' => false,
            'actual_records' => $actuals, 'evaluations' => $evaluations,
            'pooled_in_out' => ['absolute_error_total' => $pooledError, 'absolute_actual_total' => $pooledActual,
                'wape_percent' => $pooledActual > 0 ? 100 * $pooledError / $pooledActual : null,
                'skill_vs_zero_percent' => $pooledActual > 0 ? 100 * (1 - $pooledError / $pooledActual) : null]]);
    }
}
