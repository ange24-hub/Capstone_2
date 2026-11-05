<?php

namespace App\Services;

use Illuminate\Support\Carbon;

class MigrationForecastReport
{
    public function __construct(private MigrationForecastHistory $history, private MigrationForecastEngine $engine, private MigrationNetForecast $net) {}

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
        $selectionHorizon = $year === $current->year ? 4 : 1;
        $evaluation = $this->evaluate($source, $horizon, $selectionHorizon);
        $arrivalEvaluation = $this->evaluate($arrivalSource, $horizon, $selectionHorizon);
        $netEvaluation = $this->net->combine($arrivalEvaluation, $evaluation);
        $recent = array_slice($source['rows'], -3);
        $error = $this->error($source, $evaluation, $year, $current, $anchor);
        $arrivalError = $this->error($arrivalSource, $arrivalEvaluation, $year, $current, $anchor);
        return ['forecastSource' => $source, 'forecastEvaluation' => $evaluation, 'forecastHorizon' => $horizon,
            'forecastArrivalSource' => $arrivalSource, 'forecastArrivalEvaluation' => $arrivalEvaluation,
            'forecastNetEvaluation' => $netEvaluation, 'predictionArrivalError' => $arrivalError,
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

    private function evaluate(array $source, int $horizon, int $selectionHorizon): array
    {
        // Select one method at the longest quarterly horizon, using a fixed source
        // window. Monthly target changes must not trigger a different model choice.
        $selection = $this->engine->evaluate($source['consecutive'], $selectionHorizon);
        $evaluation = $selection;
        if ($selection['ready']) {
            $evaluation = $this->engine->evaluate($source['consecutive'], $horizon, $selection['method_key']);
            $evaluation['validation_months'] = $selection['validation_months'];
            $evaluation['validation_mae'] = $selection['validation_mae'];
        }
        $evaluation['required_months'] = $selection['required_months'];
        $evaluation['model_selection_horizon'] = $selectionHorizon;
        return $evaluation;
    }

    private function error(array $source, array $evaluation, int $year, Carbon $current, Carbon $anchor): ?string
    {
        return $year > $current->year ? 'Choose the current year or a past year to estimate migration.'
            : (! $source['storage_ready'] ? 'Reporting-period storage is not ready. Contact the administrator.'
                : (! $evaluation['ready'] ? 'Insufficient verified history. At least '.$evaluation['required_months'].' consecutive complete months through '.$anchor->format('F Y').' are required for training and chronological evaluation.' : null));
    }
}
