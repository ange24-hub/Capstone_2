<?php

// Read-only mapping audit; reports counts without resident or household names.
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$layers = app(App\Services\GisLayers::class);
$summary = ['registered_households' => 0, 'mapped_households' => 0,
    'households_needing_coordinates' => 0, 'mapped_households_outside_assigned_boundary' => 0,
    'barangays' => []];
foreach (App\Models\Household::with('barangay:id,name')->get(['id', 'barangay_id', 'latitude', 'longitude']) as $household) {
    $name = $household->barangay?->name ?? 'Unassigned';
    $summary['barangays'][$name] ??= ['registered' => 0, 'mapped' => 0, 'needs_coordinates' => 0, 'needs_boundary_review' => 0];
    $summary['registered_households']++;
    $summary['barangays'][$name]['registered']++;
    if ($household->latitude === null || $household->longitude === null
        || ! is_numeric($household->latitude) || ! is_numeric($household->longitude)
        || abs((float) $household->latitude) > 90 || abs((float) $household->longitude) > 180) {
        $summary['households_needing_coordinates']++;
        $summary['barangays'][$name]['needs_coordinates']++;
        continue;
    }
    $summary['mapped_households']++;
    $summary['barangays'][$name]['mapped']++;
    if (! $household->barangay || ! $layers->contains($household->barangay, (float) $household->latitude, (float) $household->longitude)) {
        $summary['mapped_households_outside_assigned_boundary']++;
        $summary['barangays'][$name]['needs_boundary_review']++;
    }
}
ksort($summary['barangays']);
echo json_encode($summary, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL;
