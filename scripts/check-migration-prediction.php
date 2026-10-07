<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Read existing records through the dashboard without creating an account or events.
$user = new App\Models\User(['role' => App\Models\User::ROLE_MUNICIPAL_LGU]);
$areaId = App\Models\MigrationRecord::query()->value('barangay_id');
$failed = false;

foreach (array_unique([null, $areaId], SORT_REGULAR) as $barangayId) {
    $request = Illuminate\Http\Request::create('/migration-monitoring', 'GET',
        $barangayId ? ['barangay_id' => $barangayId] : []);
    $request->setUserResolver(fn () => $user);
    $data = app(App\Http\Controllers\MigrationDashboardController::class)(
        $request, app(App\Services\MigrationPredictionService::class)
    )->getData();

    echo json_encode([
        'barangay_id' => $barangayId,
        'source_month' => $data['sourceMonthLabel'],
        'prediction_month' => $data['predictionMonthLabel'],
        'predicted_departures' => $data['predictedOutMigration'],
        'method' => $data['predictionMethod'],
        'error' => $data['predictionError'],
        'demo_events_in_scope' => $data['demoRecordCount'],
    ], JSON_THROW_ON_ERROR).PHP_EOL;

    $failed = $failed || $data['predictedOutMigration'] === null
        || $data['predictionMethod'] !== 'Gradient Boosting prototype';
}

exit($failed ? 1 : 0);
