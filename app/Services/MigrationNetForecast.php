<?php

namespace App\Services;

class MigrationNetForecast
{
    /** Net errors are measured after subtraction, never by adding component MAEs. */
    public function combine(array $arrivals, array $departures): array
    {
        $unavailable = ['ready' => false,
            'required_months' => max($arrivals['required_months'], $departures['required_months']),
            'available_months' => min($arrivals['available_months'], $departures['available_months'])];
        if (! $arrivals['ready'] || ! $departures['ready']) return $unavailable;
        if ($arrivals['source_end'] !== $departures['source_end'] || $arrivals['horizon'] !== $departures['horizon']
            || array_column($arrivals['backtest'], 'month') !== array_column($departures['backtest'], 'month')) return $unavailable;

        $rows = [];
        foreach ($arrivals['backtest'] as $index => $in) {
            $out = $departures['backtest'][$index];
            $actual = $in['actual'] - $out['actual'];
            $predicted = $in['predicted'] - $out['predicted'];
            $rows[] = ['month' => $in['month'], 'label' => $in['label'], 'actual' => $actual, 'predicted' => $predicted,
                'baseline' => $in['baseline'] - $out['baseline'], 'absolute_error' => abs($actual - $predicted)];
        }
        if (! $rows) return $unavailable;
        $mae = array_sum(array_column($rows, 'absolute_error')) / count($rows);
        $baselineMae = array_sum(array_map(fn ($row) => abs($row['actual'] - $row['baseline']), $rows)) / count($rows);
        return ['ready' => true, 'required_months' => $unavailable['required_months'], 'available_months' => $unavailable['available_months'],
            'prediction' => $arrivals['prediction'] - $departures['prediction'], 'backtest' => $rows,
            'mae' => round($mae, 3), 'baseline_mae' => round($baselineMae, 3), 'beats_baseline' => $mae < $baselineMae - 1e-9,
            'baseline_comparison' => abs($mae - $baselineMae) < 1e-9 ? 'equal' : ($mae < $baselineMae ? 'better' : 'worse'),
            'source_end' => $arrivals['source_end'], 'horizon' => $arrivals['horizon']];
    }
}
