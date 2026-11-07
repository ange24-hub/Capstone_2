<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class DemoMigrationForecastController extends Controller
{
    public function __invoke(Request $request): View
    {
        $request->validate(['scenario' => ['nullable', 'in:stable,trend,change']]);
        $scenarios = [
            'stable' => ['key' => 'Demo A - stable', 'title' => 'Stable movement', 'description' => 'Small monthly variations around a steady level.'],
            'trend' => ['key' => 'Demo B - gradual trend', 'title' => 'Gradual growth', 'description' => 'Arrival and departure counts increase over time.'],
            'change' => ['key' => 'Demo C - abrupt change', 'title' => 'Abrupt change', 'description' => 'The test period introduces a sudden drop in arrivals and rise in departures.'],
        ];
        $selected = $request->input('scenario') ?: 'stable';
        // Immutable experiment snapshot, deliberately separate from live migration data.
        $report = json_decode(file_get_contents(resource_path('demo/migration-forecast.json')), true, 512, JSON_THROW_ON_ERROR);
        abort_unless(($report['data_source'] ?? '') === 'SYNTHETIC_DEMO_ONLY', 500);

        return view('dashboards.migration-demo', [
            'report' => $report, 'scenarios' => $scenarios, 'selected' => $selected,
            'scenario' => $scenarios[$selected], 'evaluations' => $report['results'][$scenarios[$selected]['key']],
        ]);
    }
}
