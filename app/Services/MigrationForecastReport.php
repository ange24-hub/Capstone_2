<?php

namespace App\Services;

use Illuminate\Support\Carbon;

class MigrationForecastReport
{
    public function __construct(private MigrationForecastHistory $history, private ValidatedMigrationForecast $engine, private MigrationNetForecast $net) {}

    public function build(?int $barangayId, ?int $year = null): array
    {
        $current = now()->startOfMonth();
        $year ??= $current->year;
        $quarterStart = $current->copy()->startOfQuarter();
        $anchor = $year < $current->year ? Carbon::create($year, 12, 1)->startOfDay() : $quarterStart->copy()->subMonth();
        $target = $year === $current->year ? $current->copy()->addMonth() : $anchor->copy()->addMonth();
        $horizon = $year === $current->year ? (int) $anchor->diffInMonths($target) : 1;
        $source = $this->history->build($barangayId, $anchor);
        $arrivalSource = $this->history->build($barangayId, $anchor, 36, 'in');
        // The same source and four-month selection horizon keep model choice stable within a quarter.
        $selectionHorizon = $year === $current->year ? 4 : 1;
        $evaluation = $this->engine->evaluate($source['consecutive'], $horizon, $selectionHorizon);
        $arrivalEvaluation = $this->engine->evaluate($arrivalSource['consecutive'], $horizon, $selectionHorizon);
        $netEvaluation = $this->net->combine($arrivalEvaluation, $evaluation);
        $recent = array_slice($source['rows'], -3);
        $error = $this->error($source, $evaluation, $year, $current, $anchor);
        $arrivalError = $this->error($arrivalSource, $arrivalEvaluation, $year, $current, $anchor);
        $forecastMonths = [];
        if (! $error || ! $arrivalError) {
            foreach (range(0, 5) as $offset) {
                $date = $target->copy()->addMonths($offset);
                $in = $arrivalError ? null : $this->engine->predict(array_column($arrivalSource['consecutive'], 'count'), $horizon + $offset, $arrivalEvaluation['method_key']);
                $out = $error ? null : $this->engine->predict(array_column($source['consecutive'], 'count'), $horizon + $offset, $evaluation['method_key']);
                $forecastMonths[] = ['month' => $date->format('Y-m'), 'label' => $date->format('M Y'),
                    'in' => $in, 'out' => $out, 'net' => $netEvaluation['ready'] ? $in - $out : null,
                    'reference_month' => $date->copy()->subYear()->format('Y-m')];
            }
        }

        return ['forecastSource' => $source, 'forecastEvaluation' => $evaluation, 'forecastHorizon' => $horizon,
            'forecastArrivalSource' => $arrivalSource, 'forecastArrivalEvaluation' => $arrivalEvaluation,
            'forecastNetEvaluation' => $netEvaluation, 'predictionArrivalError' => $arrivalError, 'forecastMonths' => $forecastMonths,
            'predictedInMigration' => $arrivalError ? null : $arrivalEvaluation['prediction'],
            'predictedNetMigration' => $error || $arrivalError || ! $netEvaluation['ready'] ? null : $netEvaluation['prediction'],
            'forecastRefreshLabel' => $year === $current->year ? $quarterStart->format('F j, Y') : null,
            'forecastNextRefreshLabel' => $year === $current->year ? $quarterStart->copy()->addMonths(3)->format('F j, Y') : null,
            'currentMonthOut' => $recent[2]['count'], 'previousMonthOut' => $recent[1]['count'],
            'threeMonthAverage' => count(array_filter($recent, fn ($row) => $row['verified'])) === 3 ? round(array_sum(array_column($recent, 'count')) / 3, 2) : null,
            'predictionError' => $error, 'predictedOutMigration' => $error ? null : $evaluation['prediction'],
            'predictionMethod' => $error ? null : $evaluation['method'], 'predictionMonthLabel' => $target->format('F Y'),
            'sourceMonthLabel' => $anchor->format('F Y'), 'previousMonthLabel' => $anchor->copy()->subMonth()->format('F Y')];
    }

    private function error(array $source, array $evaluation, int $year, Carbon $current, Carbon $anchor): ?string
    {
        return $year > $current->year ? 'Choose the current year or a past year to estimate migration.'
            : (! $source['storage_ready'] ? 'Reporting-period storage is not ready. Contact the administrator.'
                : (! $evaluation['ready'] ? 'Insufficient verified history. At least '.$evaluation['required_months'].' consecutive complete months through '.$anchor->format('F Y').' are required for a forecast. Held-out error is shown only when separate test history is available.' : null));
    }
}
