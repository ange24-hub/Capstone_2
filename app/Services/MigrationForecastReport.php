<?php

namespace App\Services;

use Illuminate\Support\Carbon;

class MigrationForecastReport
{
    public function __construct(private MigrationForecastHistory $history, private MigrationForecastEngine $engine) {}

    public function build(?int $barangayId, ?int $year = null): array
    {
        $current = now()->startOfMonth();
        $year ??= $current->year;
        $anchor = $year < $current->year ? Carbon::create($year, 12, 1)->startOfDay() : $current->copy()->subMonth();
        $target = $year === $current->year ? $current->copy()->addMonth() : $anchor->copy()->addMonth();
        $horizon = $year === $current->year ? 2 : 1;
        $source = $this->history->build($barangayId, $anchor);
        $evaluation = $this->engine->evaluate($source['consecutive'], $horizon);
        $recent = array_slice($source['rows'], -3);
        $error = $year > $current->year ? 'Choose the current year or a past year to estimate migration.'
            : (! $source['storage_ready'] ? 'Reporting-period storage is not ready. Contact the administrator.'
                : (! $evaluation['ready'] ? 'Insufficient verified history. At least '.$evaluation['required_months'].' consecutive complete months through '.$anchor->format('F Y').' are required for training and chronological evaluation.' : null));
        return ['forecastSource' => $source, 'forecastEvaluation' => $evaluation, 'forecastHorizon' => $horizon,
            'currentMonthOut' => $recent[2]['count'], 'previousMonthOut' => $recent[1]['count'],
            'threeMonthAverage' => count(array_filter($recent, fn ($row) => $row['verified'])) === 3 ? round(array_sum(array_column($recent, 'count')) / 3, 2) : null,
            'predictionError' => $error, 'predictedOutMigration' => $error ? null : $evaluation['prediction'],
            'predictionMethod' => $error ? null : $evaluation['method'], 'predictionMonthLabel' => $target->format('F Y'),
            'sourceMonthLabel' => $anchor->format('F Y'), 'previousMonthLabel' => $anchor->copy()->subMonth()->format('F Y')];
    }
}
