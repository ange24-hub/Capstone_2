<?php

declare(strict_types=1);

// Standalone simulation: no Laravel bootstrap, database access, or production model writes.
require __DIR__.'/../vendor/autoload.php';

use App\Services\MigrationForecastEngine;
use App\Services\MigrationNetForecast;

$seed = 20261106;
$start = new DateTimeImmutable('2023-10-01');
$months = 36;
$horizon = 3; // September source cutoff -> December demo forecast.
$selectionHorizon = 4; // Same fixed selection horizon as the current-year dashboard.
$output = __DIR__.'/../storage/app/forecast-demo/'.gmdate('Ymd-His').'-'.bin2hex(random_bytes(3));
if (! mkdir($output, 0755, true)) throw new RuntimeException('Cannot create demo output directory.');

$noise = static function (string $scenario, string $series, int $month) use ($seed): int {
    return (int) (hexdec(substr(hash('sha256', "$seed|$scenario|$series|$month"), 0, 8)) % 7) - 3;
};
$scenarios = [
    'Demo A - stable' => static fn (int $i, string $series): float => $series === 'arrivals' ? 14 : 11,
    'Demo B - gradual trend' => static fn (int $i, string $series): float => $series === 'arrivals' ? 9 + .65 * $i : 7 + .35 * $i,
    'Demo C - abrupt change' => static fn (int $i, string $series): float => $series === 'arrivals'
        ? ($i < 33 ? 18 : 7) : ($i < 33 ? 10 : 24),
];
$engine = new MigrationForecastEngine();
$net = new MigrationNetForecast();
$dataset = [];
$metrics = [];
$heldOut = [];
$results = [];
foreach ($scenarios as $scenario => $pattern) {
    $seriesRows = ['arrivals' => [], 'departures' => []];
    for ($i = 0; $i < $months; $i++) {
        $date = $start->modify("+$i months");
        $counts = [];
        foreach (array_keys($seriesRows) as $series) {
            $counts[$series] = max(0, (int) round($pattern($i, $series) + $noise($scenario, $series, $i)));
            // This flag satisfies the engine's complete-series contract in memory ONLY.
            // It is not a staff verification and is never persisted to reporting tables.
            $seriesRows[$series][] = ['month' => $date->format('Y-m'), 'label' => $date->format('M Y'),
                'count' => $counts[$series], 'verified' => true];
        }
        $dataset[] = [$scenario, $date->format('Y-m'), $counts['arrivals'], $counts['departures'],
            'SYNTHETIC_DEMO_ONLY', $seed];
    }
    $evaluations = [];
    foreach ($seriesRows as $series => $rows) {
        $selection = $engine->evaluate($rows, $selectionHorizon);
        if (! $selection['ready']) throw new RuntimeException('Demo history does not meet the readiness gate.');
        $evaluations[$series] = $engine->evaluate($rows, $horizon, $selection['method_key']);
        $evaluations[$series]['selection_horizon'] = $selectionHorizon;
        $evaluations[$series]['selection_validation_mae'] = $selection['validation_mae'];
        $evaluations[$series]['selection_validation_months'] = $selection['validation_months'];
    }
    $evaluations['net'] = $net->combine($evaluations['arrivals'], $evaluations['departures']);
    foreach ($evaluations as $series => $evaluation) {
        if (! $evaluation['ready']) throw new RuntimeException('Demo evaluation unavailable.');
        $manualMae = array_sum(array_map(static fn ($row) => abs($row['actual'] - $row['predicted']), $evaluation['backtest'])) / 3;
        if (abs($manualMae - $evaluation['mae']) > .00051) throw new RuntimeException('MAE verification failed.');
        $metrics[] = [$scenario, $series, $evaluation['method'] ?? 'Arrivals minus departures',
            $evaluation['mae'], $evaluation['baseline_mae'], $evaluation['baseline_comparison'],
            round($evaluation['prediction'], 3), '2026-12', 'SYNTHETIC_DEMO_ONLY'];
        foreach ($evaluation['backtest'] as $row) {
            $target = DateTimeImmutable::createFromFormat('!Y-m', $row['month']);
            $heldOut[] = [$scenario, $series, $row['month'], $target->modify("-$horizon months")->format('Y-m'),
                $row['actual'], round($row['predicted'], 6), round($row['baseline'], 6),
                round($row['absolute_error'], 6), 'SYNTHETIC_DEMO_ONLY'];
        }
    }
    $results[$scenario] = $evaluations;
}

$writeCsv = static function (string $name, array $header, array $rows) use ($output): void {
    $stream = fopen("$output/$name", 'xb');
    if (! $stream) throw new RuntimeException('Cannot write demo CSV.');
    fputcsv($stream, $header, ',', '"', '');
    foreach ($rows as $row) fputcsv($stream, $row, ',', '"', '');
    fclose($stream);
};
$writeCsv('demo-monthly-migration.csv', ['scenario', 'month', 'arrivals', 'departures', 'data_source', 'seed'], $dataset);
$writeCsv('demo-evaluation.csv', ['scenario', 'series', 'method', 'test_mae', 'baseline_mae', 'comparison', 'forecast', 'target_month', 'data_source'], $metrics);
$writeCsv('demo-held-out-predictions.csv', ['scenario', 'series', 'test_month', 'training_through', 'actual', 'predicted', 'baseline', 'absolute_error', 'data_source'], $heldOut);
$manifest = [
    'data_source' => 'SYNTHETIC_DEMO_ONLY', 'seed' => $seed,
    'warning' => 'Simulation results only. Not actual barangay data and not evidence of real-world accuracy.',
    'database_access' => false, 'production_model_changed' => false,
    'source_start' => '2023-10', 'source_end' => '2026-09', 'target_month' => '2026-12',
    'months_per_scenario' => $months, 'scenarios' => count($scenarios), 'dataset_rows' => count($dataset),
    'methodology' => [
        'candidate_methods' => ['last_month', 'moving_average_3', 'smoothing_0.2', 'smoothing_0.5', 'smoothing_0.8', 'holt_0.3_0.1', 'holt_0.6_0.2'],
        'selection_horizon_months' => 4, 'evaluation_horizon_months' => 3,
        'selection_validation_targets' => '2024-07 through 2026-03',
        'selection_data_ends' => '2026-03', 'held_out_targets' => ['2026-07', '2026-08', '2026-09'],
        'test_training_cutoffs' => ['2026-04', '2026-05', '2026-06'],
        'description' => 'Earlier rolling validation selects the method; final three targets are held out. Each test forecast uses only data available at its origin. The future demo estimate is then refitted through September.',
        'baseline' => 'Recursive 3-month moving average; net baseline subtracts departure baseline from arrival baseline.',
        'mae_units' => 'Movement events per month. Lower is better. Not an accuracy percentage.',
    ],
    'engine_sha256' => hash_file('sha256', __DIR__.'/../app/Services/MigrationForecastEngine.php'),
    'dataset_sha256' => hash_file('sha256', "$output/demo-monthly-migration.csv"),
    'results' => $results,
];
file_put_contents("$output/demo-model-results.json", json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n");
$lines = ['DEMO MIGRATION MODEL TRAINING AND EVALUATION', '', $manifest['warning'],
    '108 synthetic monthly rows: 3 fictional areas, 36 months each (Oct 2023-Sep 2026).',
    'Models: persistence, moving average, exponential smoothing, and Holt trend.',
    'Selection uses earlier rolling validation at 4 months; test horizon is 3 months.',
    'Held-out targets: Jul-Sep 2026. Training cutoffs: Apr-Jun 2026 respectively.',
    'The December 2026 estimate is a synthetic forecast; no future actual is available.',
    'Three test targets per scenario are limited evidence. A sudden-change scenario deliberately tests a harder case.',
    'Database records and the deployed forecasting engine have not been changed.', '',
    'Scenario | Series | Test MAE | Baseline MAE | Comparison | December demo forecast'];
foreach ($metrics as $row) $lines[] = implode(' | ', [$row[0], $row[1], $row[3], $row[4], $row[5], $row[6]]);
$lines[] = '';
$lines[] = 'Reproduce: php scripts/train-demo-forecast.php';
$lines[] = 'Each run creates a separate folder. The fixed seed reproduces the dataset and model results.';
file_put_contents("$output/README.txt", implode("\n", $lines)."\n");
echo implode("\n", $lines)."\n\nOutput: ".realpath($output)."\n";
