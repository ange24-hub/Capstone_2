<?php

declare(strict_types=1);

// No Laravel bootstrap: the experiment never connects to or changes the reporting database.
require __DIR__.'/../vendor/autoload.php';

use App\Services\MigrationRateForecast;
use App\Services\ProvidedMigrationExperiment;
use App\Services\RollingHistoryBacktest;
use App\Services\SeasonalNaiveMigrationExperiment;

$arguments = array_slice($argv, 1);
$methodArguments = array_values(array_filter($arguments, fn ($argument) => str_starts_with($argument, '--method=')));
if (count($methodArguments) > 1) {
    throw new InvalidArgumentException('Choose one fixed forecasting method.');
}
$method = $methodArguments ? substr($methodArguments[0], strlen('--method=')) : 'poisson_jeffreys';
if (! in_array($method, ['poisson_jeffreys', 'mean_rate', 'seasonal_naive_12'], true)) {
    throw new InvalidArgumentException('Use poisson_jeffreys, mean_rate or seasonal_naive_12.');
}
$trainOnly = in_array('--train-only', $arguments, true);
$paths = array_values(array_filter($arguments, fn ($argument) => $argument !== '--train-only' && ! str_starts_with($argument, '--method=')));
if (count($paths) > 1) {
    throw new InvalidArgumentException('Provide one CSV path, optionally --train-only and --method=METHOD.');
}
$input = $paths[0] ?? __DIR__.'/../resources/forecast-experiments/user-provided-2025.csv';
$stream = fopen($input, 'rb');
if (! $stream) {
    throw new RuntimeException('Cannot read the provided CSV.');
}
$header = fgetcsv($stream, null, ',', '"', '');
$header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0] ?? '');
$columns = ['year', 'month_num', 'month', 'in_migrant', 'out_migrant', 'net_migration'];
if ($header !== $columns && $header !== array_merge(['dataset'], $columns)) {
    throw new InvalidArgumentException('Use year,month_num,month,in_migrant,out_migrant,net_migration, with an optional leading dataset column.');
}
$trainOnly = $trainOnly || $header[0] === 'dataset' || $method === 'seasonal_naive_12';
$records = [];
while (($values = fgetcsv($stream, null, ',', '"', '')) !== false) {
    if ($values === [null]) {
        continue;
    }
    if (count($values) !== count($header)) {
        throw new InvalidArgumentException('Every CSV row must contain every header column.');
    }
    $record = array_combine($header, array_map('trim', $values));
    foreach (array_diff($header, ['month', 'dataset']) as $field) {
        if (! preg_match('/^-?\d+$/D', $record[$field])) {
            throw new InvalidArgumentException('Numeric columns must contain whole numbers.');
        }
        $record[$field] = (int) $record[$field];
    }
    $records[] = $record;
}
fclose($stream);
$model = new MigrationRateForecast;
$experiment = new ProvidedMigrationExperiment(new RollingHistoryBacktest($model), $model);
$result = $method === 'seasonal_naive_12' ? (new SeasonalNaiveMigrationExperiment($experiment))->train($records)
    : ($trainOnly ? $experiment->train($records, $method) : $experiment->evaluate($records, $method));
$result['calculated_at'] = gmdate('c');
$result['input_sha256'] = hash_file('sha256', $input);
$output = __DIR__.'/../output/prediction-analysis/provided-'.gmdate('Ymd-His').'-'.bin2hex(random_bytes(4));
if (! mkdir($output, 0755, true)) {
    throw new RuntimeException('Cannot create experiment output directory.');
}
copy($input, $output.'/input.csv');
file_put_contents($output.'/model-result.json', json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n");
$write = static function (string $file, array $columns, array $rows) use ($output): void {
    $csv = fopen($output.'/'.$file, 'xb');
    fputcsv($csv, $columns, ',', '"', '');
    foreach ($rows as $row) {
        fputcsv($csv, $row, ',', '"', '');
    }
    fclose($csv);
};
$tests = [];
foreach ($result['evaluations'] as $key => $evaluation) {
    foreach ($evaluation['backtest'] as $row) {
        $tests[] = [$key, $row['month'], $row['training_through'], $row['actual'], $row['predicted'], $row['baseline'], $row['absolute_error']];
    }
}
if ($tests) {
    $write('test-results.csv', ['series', 'month', 'training_through', 'actual', 'predicted', 'mean_rate_baseline', 'absolute_error'], $tests);
}
$write('forecasts.csv', ['month', 'in_migrant', 'out_migrant', 'net_migration', 'actual'], array_map(fn ($row) => [$row['month'], $row['in_migrant'], $row['out_migrant'], $row['net_migration'], ''], $result['forecasts']));
echo json_encode(['output' => realpath($output), 'initial_training' => [$result['source_start'], $result['initial_training_end']],
    'tests' => [$result['test_start'], $result['test_end']], 'method' => $result['method'],
    'mode' => $trainOnly ? 'train_only' : 'rolling', 'accuracy_ready' => $result['accuracy_ready'] ?? true,
    'metrics' => array_map(fn ($e) => $e['metrics'], $result['evaluations']), 'refitted_models' => $result['refitted_models'],
    'annual_forecast' => $result['annual_forecast'] ?? null, 'forecasts' => $result['forecasts']], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)."\n";
