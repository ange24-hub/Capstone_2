<?php

namespace App\Console\Commands;

use App\Models\Barangay;
use App\Services\RbiShortHistorySource;
use App\Services\ShortHistoryForecast;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class RunShortHistoryForecast extends Command
{
    protected $signature = 'migration:history-experiment {--barangay= : Barangay ID; defaults to Cawayan} {--end-month= : First test/forecast month (rolling/holdout) or last pilot month, YYYY-MM} {--mode=short : short, holdout or rolling} {--method=selected : selected, mean_rate or poisson_jeffreys; rolling requires a fixed rate method} {--horizon=1 : 1, 3 or 6 forecast/test months} {--training-months=12 : 6 or 12 training months; initial window in rolling mode}';

    protected $description = 'Compare historical forecasting models and save private aggregate results without changing RBI records';

    public function handle(RbiShortHistorySource $source, ShortHistoryForecast $engine): int
    {
        $data = ['barangay_id' => $this->option('barangay'), 'end_month' => $this->option('end-month'), 'mode' => $this->option('mode'),
            'method' => $this->option('method'), 'horizon' => $this->option('horizon'), 'training_months' => $this->option('training-months')];
        $validator = Validator::make($data, ['barangay_id' => ['nullable', 'integer', 'exists:barangays,id'],
            'end_month' => ['required_if:mode,rolling', 'nullable', 'date_format:Y-m', 'before:'.now()->startOfMonth()->format('Y-m')], 'mode' => ['required', 'in:short,holdout,rolling'],
            'method' => ['required', 'in:selected,mean_rate,poisson_jeffreys'], 'horizon' => ['required', 'integer', 'in:1,3,6'], 'training_months' => ['required', 'integer', 'in:6,12']]);
        if ($validator->fails()) {
            $this->error($validator->errors()->first());

            return self::FAILURE;
        }
        $area = $data['barangay_id'] ? Barangay::findOrFail($data['barangay_id']) : Barangay::where('name', 'Cawayan')->firstOrFail();
        $holdout = $data['mode'] === 'holdout';
        $rolling = $data['mode'] === 'rolling';
        if (($data['method'] === 'selected' && ($rolling || (int) $data['horizon'] !== 1 || (int) $data['training_months'] !== 12)) || (! $holdout && ! $rolling && ($data['method'] !== 'selected' || (int) $data['horizon'] !== 1))) {
            $this->error('Use --mode=holdout or --mode=rolling with --method=mean_rate or --method=poisson_jeffreys for six-month training or multiple targets.');

            return self::FAILURE;
        }
        $history = $holdout || $rolling ? $source->forecastWindow($area, $data['end_month'], (int) $data['training_months'], (int) $data['horizon']) : $source->build($area, $data['end_month']);
        $evaluation = $rolling ? app(\App\Services\RollingHistoryBacktest::class)->evaluate($history['rows'], $data['method'], (int) $data['training_months'], (int) $data['horizon'])
            : ($holdout ? app(\App\Services\TwelveMonthHoldoutForecast::class)->evaluate($history['rows'], $data['method'], (int) $data['horizon'], (int) $data['training_months']) : $engine->evaluate($history['rows']));
        if (! $evaluation['ready']) {
            $this->error($rolling ? 'Complete consecutive classified records are required for the initial training window and every rolling test month.' : ($holdout ? $data['training_months'].' complete classified training months are needed. Forecast targets stay outside training.' : 'Seven complete classified months are needed for this experiment. Review the source records first.'));

            return self::FAILURE;
        }
        $directory = 'private/forecast-history/'.$area->id.'/'.now()->format('Ymd-His').'-'.bin2hex(random_bytes(4));
        $result = ['data_source' => 'CLASSIFIED_RBI_HISTORY_EXPERIMENT', 'preliminary' => true,
            'calculated_at' => now()->toIso8601String(), 'mode' => $data['mode'], 'barangay' => ['id' => $area->id, 'name' => $area->name],
            'history' => $history, 'evaluation' => $evaluation];
        Storage::disk('local')->put($directory.'/result.json', json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        $csv = 'month,recorded,predicted,'.($rolling ? 'mean_rate_baseline' : 'moving_average').",absolute_error,training_through\n";
        foreach ($evaluation['backtest'] as $row) {
            $csv .= implode(',', [$row['month'], $row['actual'], $row['predicted'], $row['baseline'], $row['absolute_error'], $row['training_through']])."\n";
        }
        if ($holdout) {
            foreach ($evaluation['forecasts'] as $row) {
                if ($row['actual'] !== null) {
                    $csv .= implode(',', [$row['month'], $row['actual'], $row['predicted'], $evaluation['holdout_baseline'], $row['absolute_error'], $evaluation['source_end']])."\n";
                }
            }
        }
        Storage::disk('local')->put($directory.'/test-results.csv', $csv);
        if ($holdout) {
            $forecastCsv = "month,predicted,recorded,absolute_error,training_through\n";
            foreach ($evaluation['forecasts'] as $row) {
                $forecastCsv .= implode(',', [$row['month'], $row['predicted'], $row['actual'] ?? '', $row['absolute_error'] ?? '', $evaluation['source_end']])."\n";
            }
            Storage::disk('local')->put($directory.'/forecast.csv', $forecastCsv);
        }
        $testRows = $evaluation['backtest'] ?: ($evaluation['forecasts'] ?? []);
        $this->line(json_encode(['barangay' => $area->name, 'mode' => $data['mode'], 'method' => $evaluation['method'], 'alpha' => $evaluation['alpha'] ?? null,
            'source_start' => $evaluation['source_start'], 'source_end' => $evaluation['source_end'], 'target_month' => $evaluation['target_month'],
            'prediction' => $evaluation['prediction'] ?? null, 'test_mae' => $evaluation['mae'], 'baseline_mae' => $evaluation['baseline_mae'],
            'first_test_month' => $testRows[0]['month'] ?? null, 'first_test_prediction' => $testRows[0]['predicted'] ?? null,
            'first_test_recorded' => $testRows[0]['actual'] ?? null, 'test_months' => $evaluation['observed_months'] ?? $evaluation['test_months'] ?? count($testRows),
            'holdout_recorded' => $evaluation['holdout_actual'] ?? null, 'holdout_error' => $evaluation['holdout_error'] ?? null,
            'forecast_months' => $evaluation['forecast_months'] ?? 1, 'forecast_end' => $evaluation['forecast_end'] ?? $evaluation['target_month'],
            'forecast_total' => $evaluation['forecast_total'] ?? null, 'predicted_total' => $evaluation['predicted_total'] ?? null,
            'training_months' => $evaluation['training_months'] ?? $evaluation['initial_training_months'] ?? null, 'observed_months' => $evaluation['observed_months'] ?? null,
            'observed_mae' => $evaluation['observed_mae'] ?? ($rolling ? $evaluation['mae'] : null), 'actual_total' => $evaluation['actual_total'] ?? null,
            'mean_rate_mae' => $evaluation['mean_rate_mae'] ?? null, 'mae_change_from_mean' => $evaluation['mae_change_from_mean'] ?? null,
            'test_start' => $evaluation['test_start'] ?? null, 'test_end' => $evaluation['test_end'] ?? null,
            'draft_months' => $history['draft_months'], 'results' => Storage::disk('local')->path($directory.'/result.json')], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

        return self::SUCCESS;
    }
}
