param([ValidateSet('Prepare','Apply')][string]$Mode = 'Prepare')
$ErrorActionPreference = 'Stop'
$workspace = [IO.Path]::GetFullPath((Split-Path -Parent $PSScriptRoot)).TrimEnd('\')
$archive = Join-Path $workspace 'storage/app/retired-experiments/cleanup-20261108'
function SafePath([string]$relative) {
    $absolute = [IO.Path]::GetFullPath((Join-Path $workspace $relative))
    if (-not $absolute.StartsWith($workspace + '\', [StringComparison]::OrdinalIgnoreCase)) { throw "Outside workspace: $relative" }
    if ($relative -match '(^|[/\\])(\.git|\.venv|vendor|node_modules)([/\\]|$)') { throw "Protected target: $relative" }
    return $absolute
}
$archive = SafePath 'storage/app/retired-experiments/cleanup-20261108'
$manifestPath = Join-Path $archive 'manifest.json'
if ($Mode -eq 'Prepare') {
    if (Test-Path -LiteralPath $manifestPath) { throw 'A cleanup manifest already exists; inspect it before continuing.' }
    $relativeFiles = @(
        'app/Http/Controllers/DemoMigrationForecastController.php','app/Http/Controllers/ShortHistoryForecastController.php',
        'app/Http/Controllers/UploadedMigrationSvrController.php','app/Http/Controllers/MigrationScenarioController.php',
        'app/Services/UploadedMigrationSvr.php','app/Services/UploadedMigrationSvrForecast.php','app/Services/MigrationScenarioProjection.php',
        'app/Services/AllBarangayHistoricalTrainingSource.php','app/Services/TinagoHistoricalTrainingSource.php',
        'app/Services/ShortHistoryForecast.php','app/Services/TwelveMonthHoldoutForecast.php','app/Services/RbiShortHistorySource.php',
        'app/Services/RollingHistoryBacktest.php','app/Services/MigrationRateForecast.php','app/Services/ProvidedMigrationExperiment.php',
        'app/Services/FrozenMigrationForecastEvaluation.php','app/Services/SeasonalNaiveMigrationExperiment.php',
        'app/Services/MigrationForecastEngine.php','app/Services/DatedRbiHistorySample.php',
        'app/Console/Commands/PredictUploadedMigrationSvr.php','app/Console/Commands/RunShortHistoryForecast.php',
        'app/Console/Commands/TrainAllBarangaySyntheticForecast.php','app/Console/Commands/TrainTinagoHistoricalForecast.php',
        'resources/views/dashboards/migration-demo.blade.php','resources/views/dashboards/_migration-demo-chart.blade.php',
        'resources/views/dashboards/migration-history-experiment.blade.php','resources/views/dashboards/migration-holdout-experiment.blade.php',
        'resources/views/dashboards/migration-rolling-experiment.blade.php','resources/views/dashboards/migration-svr.blade.php',
        'resources/views/dashboards/migration-scenarios.blade.php','resources/views/dashboards/municipal-prediction.blade.php',
        'resources/views/dashboards/pooled-migration-evidence.blade.php','resources/views/dashboards/_pooled-svr-future.blade.php',
        'resources/demo/migration-forecast.json','resources/forecast-models/migration-svr.json','resources/migration-scenario-defense.txt',
        'public/css/migration-demo.css','public/css/migration-scenarios.css',
        'scripts/train-provided-migration.php','scripts/train-demo-forecast.php','scripts/evaluate-provided-migration.php',
        'scripts/render-provided-migration.cjs','scripts/render-migration-forecast.cjs','scripts/render-frozen-migration-evaluation.cjs',
        'scripts/render-historical-forecast-chart.cjs','scripts/preview_migration_scenarios.php',
        'scripts/export_prediction_input_audit.php','scripts/export_forecast_defense_source.php',
        'tests/Unit/UploadedMigrationSvrTest.php','tests/Unit/TwelveMonthHoldoutForecastTest.php','tests/Unit/ShortHistoryForecastTest.php',
        'tests/Unit/SeasonalNaiveMigrationExperimentTest.php','tests/Unit/RollingHistoryBacktestTest.php',
        'tests/Unit/ProvidedMigrationExperimentTest.php','tests/Unit/MigrationForecastEngineTest.php','tests/Unit/FrozenMigrationForecastEvaluationTest.php',
        'tests/Feature/AllBarangayHistoricalTrainingSourceTest.php','tests/Feature/TinagoHistoricalTrainingSourceTest.php',
        'tests/Feature/DemoMigrationForecastTest.php','tests/Feature/MunicipalPredictionTest.php','tests/Feature/MigrationScenarioProjectionTest.php',
        'tests/Feature/RollingHistoryBacktestTest.php','tests/Feature/ShortHistoryForecastTest.php',
        'tests/Feature/UploadedMigrationSvrForecastTest.php','tests/Feature/TwelveMonthHoldoutForecastTest.php'
    )
    $oldPython = @('build_defense_evidence','compare_alternative_tinago_transfer','compare_rf_cyclic75','cyclic75_wide_estimator',
        'defense_multidataset','defense_parameter_search','export_migration_svr','improve_dummy_tinago_transfer',
        'improve_random_forest_transfer','improve_tinago_synthetic','mixed_history_svr','retrain_polynomial_svr',
        'run_cyclic75_wide_search','run_svr_gridsearch_full','run_svr_kernel_comparison','run_svr_wide_search',
        'run_tinago_mixed_history','seasonal_weighted_estimator','seasonal_weighted_mixed_svr','svr_gridsearch_example',
        'test_all_barangay_synthetic','test_alternative_dummy_transfer','test_defense_multidataset','test_dummy_on_tinago',
        'test_dummy_tinago_transfer','test_geographic_dummy_transfer','test_random_forest_refinement','test_system_prediction_inputs',
        'test_tinago_comparison','test_tinago_historical','test_tinago_improvement','test_tinago_synthetic',
        'tinago_model_comparison','train_all_barangay_synthetic','train_tinago_historical','train_tinago_synthetic',
        'try_system_prediction_inputs','verify_defense_multidataset','verify_defense_parameters')
    foreach ($name in $oldPython) { $relativeFiles += 'ml/' + $name + '.py' }
    foreach ($file in Get-ChildItem -LiteralPath (SafePath 'docs') -File) {
        if ($file.Name -match '^(all-barangay-synthetic|dummy-|poly-expanded|random-forest|svr-|system-predictive-inputs|tinago-|migration-demo|migration-svr)') { $relativeFiles += 'docs/' + $file.Name }
    }
    $relativeDirs = @('resources/forecast-experiments','ml/models','output/scenario-projections-20261108')
    $keptOutputs = @('pooled-actual-review-20261108','actual-only-verified-20261108','actual-only-retrain-20261108',
        'new-inhabitants-2025-review-20261108','monthly-in-migration-eda-20261108-075511')
    foreach ($dir in Get-ChildItem -LiteralPath (SafePath 'output/prediction-analysis') -Directory) {
        if ($dir.Name -notin $keptOutputs) { $relativeDirs += 'output/prediction-analysis/' + $dir.Name }
    }
    $entries = @()
    foreach ($relative in ($relativeFiles + $relativeDirs | Select-Object -Unique)) {
        $source = SafePath $relative
        if (-not (Test-Path -LiteralPath $source)) { continue }
        $destination = [IO.Path]::GetFullPath((Join-Path (Join-Path $archive 'files') $relative))
        if (-not $destination.StartsWith($archive + '\files\',[StringComparison]::OrdinalIgnoreCase)) { throw 'Invalid archive destination' }
        $item = Get-Item -LiteralPath $source
        $entries += [pscustomobject]@{path=$relative;directory=[bool]$item.PSIsContainer;sha256=$(if(-not $item.PSIsContainer){(Get-FileHash -LiteralPath $source -Algorithm SHA256).Hash}else{$null});reason='Retired prediction/demo experiment; active actual-data inference and source records retained'}
    }
    New-Item -ItemType Directory -Path (Join-Path $archive 'before-edits') -Force | Out-Null
    $editedFiles = @('routes/web.php','app/Http/Controllers/MunicipalPredictionController.php','app/Http/Controllers/PooledMigrationEvidenceController.php',
        'resources/views/layouts/navigation.blade.php','resources/views/dashboards/migration.blade.php','resources/views/dashboards/_migration-forecast.blade.php',
        'resources/views/dashboards/_barangay-svr-arrival.blade.php','tests/Feature/PooledSvrFutureTest.php','tests/Feature/PooledMigrationEvidenceAccessTest.php',
        'tests/Feature/PredictionAnalyticsAccessTest.php','docs/migration-forecasting.md','resources/pooled-svr-defense.txt')
    foreach($relative in $editedFiles){
        $target=Join-Path (Join-Path $archive 'before-edits') $relative
        New-Item -ItemType Directory -Path (Split-Path -Parent $target) -Force | Out-Null
        Copy-Item -LiteralPath (SafePath $relative) -Destination $target
    }
    [pscustomobject]@{created_at=(Get-Date).ToString('o');workspace=$workspace;archive=$archive;entries=$entries;database_changes=$false;status='prepared'} | ConvertTo-Json -Depth 8 | Set-Content -LiteralPath $manifestPath -Encoding UTF8
    [pscustomobject]@{status='prepared';archive=$archive;files=@($entries|Where-Object{-not $_.directory}).Count;directories=@($entries|Where-Object{$_.directory}).Count} | ConvertTo-Json
    exit
}
$manifest = Get-Content -LiteralPath $manifestPath -Raw | ConvertFrom-Json
if($manifest.status -ne 'prepared'){throw 'Cleanup already applied or manifest state is invalid.'}
foreach($entry in $manifest.entries){
    $source=SafePath $entry.path
    $destination=[IO.Path]::GetFullPath((Join-Path (Join-Path $archive 'files') $entry.path))
    if(-not $destination.StartsWith($archive + '\files\',[StringComparison]::OrdinalIgnoreCase)){throw 'Invalid archive target'}
    if(-not (Test-Path -LiteralPath $source)){throw "Source missing: $($entry.path)"}
    if(-not $entry.directory -and (Get-FileHash -LiteralPath $source -Algorithm SHA256).Hash -ne $entry.sha256){throw "Source changed since planning: $($entry.path)"}
    if(Test-Path -LiteralPath $destination){throw 'Archive target already exists'}
}
foreach($entry in $manifest.entries){
    $source=SafePath $entry.path
    $destination=[IO.Path]::GetFullPath((Join-Path (Join-Path $archive 'files') $entry.path))
    if(-not $destination.StartsWith($archive + '\files\',[StringComparison]::OrdinalIgnoreCase)){throw 'Invalid archive target'}
    New-Item -ItemType Directory -Path (Split-Path -Parent $destination) -Force | Out-Null
    Move-Item -LiteralPath $source -Destination $destination
}
$manifest.status='applied';$manifest|Add-Member -NotePropertyName applied_at -NotePropertyValue (Get-Date).ToString('o')
$manifest|ConvertTo-Json -Depth 8|Set-Content -LiteralPath $manifestPath -Encoding UTF8
[pscustomobject]@{status='applied';archived_paths=@($manifest.entries).Count;archive=$archive;database_changes=$false}|ConvertTo-Json
