<?php

require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$dryRun = in_array('--dry-run', $argv, true);
$results = [];
foreach (App\Models\Barangay::has('inhabitants')->get() as $barangay) {
    if (! $barangay->usesResidenceRegistry()) { $results[$barangay->name] = ['skipped'=>'Residence update excluded']; continue; }
    $path = storage_path('app/import-sources/'.strtoupper($barangay->name).'.xlsx');
    if (! is_file($path)) { $results[$barangay->name] = ['error'=>'Source workbook missing']; continue; }
    $plan = $dryRun ? App\Support\SourceResidenceSync::plan($barangay, $path) : App\Support\SourceResidenceSync::apply($barangay, $path);
    $results[$barangay->name] = $dryRun ? [
        'changes'=>count($plan['updates']), 'unmatched'=>count($plan['unmatched']),
        'classifications'=>array_count_values(array_column($plan['updates'],'residence_status')),
    ] : $plan;
}
echo json_encode($results, JSON_PRETTY_PRINT).PHP_EOL;
