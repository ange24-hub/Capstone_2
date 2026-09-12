<?php

// Synthetic aggregates only. This check does not query or modify RBI records.
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
config(['local_ai.enabled' => true]);
if (isset($argv[1])) config(['local_ai.model' => $argv[1]]);

$cases = [
    'population' => ['scope' => 'Sample barangay', 'residents' => 20, 'families' => 6,
        'households' => 5, 'male' => 9, 'female' => 11, 'seniors' => 3, 'pwd' => 1,
        'basis' => 'Available encoded records only. Completeness unverified. Not a complete census.'],
    'no_migration_records' => ['scope' => 'Sample barangay', 'year' => 2026,
        'in_migration' => 0, 'out_migration' => 0, 'net_change' => 0, 'recorded_events' => 0,
        'basis' => 'No recorded events does not confirm no actual movement. Completeness unverified. No forecasts.'],
    'migration' => ['scope' => 'Sample barangay', 'year' => 2026,
        'in_migration' => 3, 'out_migration' => 5, 'net_change' => -2, 'recorded_events' => 8,
        'basis' => 'Recorded movement events, not unique people. Completeness unverified. No forecasts or confirmed causes.'],
    'migration_more_in' => ['in_migration' => 7, 'out_migration' => 2, 'recorded_events' => 9],
    'migration_equal' => ['in_migration' => 3, 'out_migration' => 3, 'recorded_events' => 6],
    'population_more_male' => ['residents' => 10, 'male' => 7, 'female' => 3],
];
$failed = false;
foreach ($cases as $name => $facts) {
    $started = microtime(true);
    $text = $app->make(App\Services\LocalAiNarrator::class)->explain($facts);
    $expectsFallback = $name === 'no_migration_records';
    $passed = $expectsFallback ? $text === null : $text !== null;
    echo json_encode(['case' => $name, 'seconds' => round(microtime(true) - $started, 2),
        'passed' => $passed, 'mode' => $text === null ? 'database_summary' : 'local_ai',
        'explanation' => $text], JSON_UNESCAPED_UNICODE).PHP_EOL;
    $failed = $failed || !$passed;
}
exit($failed ? 1 : 0);
