<?php

declare(strict_types=1);

// Read-only model scoring; no Laravel bootstrap, reporting database or model refit.
require __DIR__.'/../vendor/autoload.php';

use App\Services\FrozenMigrationForecastEvaluation;

$arguments = array_slice($argv, 1);
$actualsAfterForecast = in_array('--actuals-after-forecast', $arguments, true);
$paths = array_values(array_filter($arguments, fn ($argument) => $argument !== '--actuals-after-forecast'));
if (count($paths) !== 2) {
    throw new InvalidArgumentException('Provide the frozen model-result.json path and actual CSV path, optionally --actuals-after-forecast when confirmed.');
}
[$modelPath, $actualPath] = $paths;
$modelBytes = file_get_contents($modelPath);
$modelHash = hash('sha256', $modelBytes);
$model = json_decode($modelBytes, true, 512, JSON_THROW_ON_ERROR);
$stream = fopen($actualPath, 'rb');
$header = fgetcsv($stream, null, ',', '"', '');
$header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0] ?? '');
if ($header !== ['dataset', 'year', 'month_num', 'month', 'in_migrant', 'out_migrant', 'net_migration']) {
    throw new InvalidArgumentException('Use dataset,year,month_num,month,in_migrant,out_migrant,net_migration columns.');
}
$records = [];
while (($values = fgetcsv($stream, null, ',', '"', '')) !== false) {
    if ($values === [null]) {
        continue;
    }
    if (count($values) !== count($header)) {
        throw new InvalidArgumentException('Every actual row must contain all columns.');
    }
    $record = array_combine($header, array_map('trim', $values));
    foreach (array_diff($header, ['dataset', 'month']) as $key) {
        if (! preg_match('/^-?\d+$/D', $record[$key])) {
            throw new InvalidArgumentException('Actual dates and counts must be integers.');
        }
        $record[$key] = (int) $record[$key];
    }
    $records[] = $record;
}
fclose($stream);
$result = (new FrozenMigrationForecastEvaluation)->evaluate($model, $records);
$result['evaluated_at'] = gmdate('c');
$result['frozen_model_sha256'] = $modelHash;
$result['actual_sha256'] = hash_file('sha256', $actualPath);
$result['evaluation_context'] = ['actual_dataset_received_after_forecast' => $actualsAfterForecast,
    'source' => $actualsAfterForecast ? 'Explicit user confirmation: separate new actual dataset, supplied after saved forecast' : 'Existing model-development metadata',
    'source_model_metadata_preserved' => true, 'target_actuals_used_for_fitting' => false,
    'method_developed_after_other_target_data_inspected' => $model['target_year_previously_inspected'] ?? null];
$output = __DIR__.'/../output/prediction-analysis/provided-evaluation-'.gmdate('Ymd-His').'-'.bin2hex(random_bytes(4));
mkdir($output, 0755, true);
file_put_contents($output.'/frozen-model.json', $modelBytes);
copy($actualPath, $output.'/actual.csv');
copy(dirname($modelPath).'/input.csv', $output.'/input.csv');
copy(dirname($modelPath).'/forecasts.csv', $output.'/forecasts.csv');
file_put_contents($output.'/model-result.json', json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n");
$csv = fopen($output.'/test-results.csv', 'xb');
fputcsv($csv, ['series', 'month', 'forecast_horizon', 'training_through', 'actual', 'predicted', 'absolute_error'], ',', '"', '');
foreach ($result['evaluations'] as $key => $evaluation) {
    foreach ($evaluation['backtest'] as $row) {
        fputcsv($csv, [$key, $row['month'], $row['forecast_horizon'], $row['training_through'], $row['actual'], $row['predicted'], $row['absolute_error']], ',', '"', '');
    }
}
fclose($csv);
if (hash_file('sha256', $modelPath) !== $modelHash) {
    throw new RuntimeException('The frozen model changed while being evaluated.');
}
echo json_encode(['output' => realpath($output), 'model_refitted' => false, 'frozen_model_sha256' => $modelHash,
    'monthly_metrics' => array_map(fn ($e) => $e['metrics'], $result['evaluations']),
    'annual_comparison' => array_map(fn ($e) => $e['annual'], $result['evaluations'])], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)."\n";
