<?php

require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$rows = [];
foreach (App\Models\Barangay::orderBy('name')->get() as $area) {
    $people = App\Models\Inhabitant::where('barangay_id', $area->id)->get(['remarks']);
    $rows[] = ['id' => $area->id, 'name' => $area->name, 'real_inhabitants' => $people->filter(fn ($p) => $p->remarks !== App\Services\MigrationForecastHistory::DEMO_MARKER)->count(),
        'demo_inhabitants' => $people->filter(fn ($p) => $p->remarks === App\Services\MigrationForecastHistory::DEMO_MARKER)->count(),
        'households' => App\Models\Household::where('barangay_id', $area->id)->count(),
        'deceased' => App\Models\DeceasedInhabitant::where('barangay_id', $area->id)->count(),
        'new_history' => App\Models\NewInhabitant::where('barangay_id', $area->id)->count(),
        'forms' => App\Models\BarangayRbiUpdate::where('barangay_name', $area->name)->count(),
        'secretaries' => App\Models\User::where('role', 'barangay')->where('barangay_id', $area->id)->count()];
}
echo json_encode($rows, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL;
