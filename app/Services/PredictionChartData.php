<?php

namespace App\Services;

use App\Models\ForecastLog;
use Illuminate\Support\Carbon;

class PredictionChartData
{
    public function population(array $projection): array
    {
        $history = collect($projection['history'])->keyBy('period');
        if ($history->isEmpty()) {
            return [];
        }

        $latest = (int) $history->keys()->max();
        $steps = $projection['estimate'] === null ? [] : $projection['steps'];
        $end = $latest + count($steps);
        $rows = [];
        for ($period = max((int) $history->keys()->min(), $end - 9); $period <= $end; $period++) {
            $rows[] = [
                'period' => $period,
                'label' => intdiv($period, 2).' S'.($period % 2 + 1),
                'actual' => $history->get($period)['population'] ?? null,
                'predicted' => $steps[$period - $latest - 1]['estimate'] ?? null,
                'assumption' => false,
            ];
        }

        return $rows;
    }

    public function migration(int $barangayId, array $history, array $forecasts): array
    {
        $start = $history['in'][0]['month'];
        $end = $forecasts['in']['log_month'];
        // The chart is independent of saved-log pagination and always scoped to this barangay.
        $saved = ForecastLog::where('barangay_id', $barangayId)
            ->whereBetween('log_month', [$start, $end])->whereNotNull('predicted_value')
            ->whereNotNull('issued_at')->get()->groupBy('migration_type');
        $charts = [];
        foreach (['in', 'out'] as $type) {
            $actuals = collect($history[$type])->keyBy('month');
            $issued = $saved->get($type, collect())->keyBy('log_month');
            $rows = [];
            $month = Carbon::createFromFormat('!Y-m', $start, config('migration_forecast.timezone'));
            while ($month->format('Y-m') <= $end) {
                $key = $month->format('Y-m');
                $log = $issued->get($key);
                $eligible = $log && $log->issued_at->lt($month);
                $target = $key === $end;
                $actual = $actuals->get($key);
                $rows[] = [
                    'period' => $key,
                    'label' => $month->format('M y'),
                    'actual' => ($actual['verified'] ?? false) ? $actual['count'] : null,
                    'predicted' => $target ? $forecasts[$type]['predicted_value'] : ($eligible ? $log->predicted_value : null),
                    'assumption' => ($target ? $forecasts[$type]['method'] : $log?->method) === 'population_baseline',
                ];
                $month->addMonth();
            }
            $charts[$type] = $rows;
        }

        return $charts;
    }
}
