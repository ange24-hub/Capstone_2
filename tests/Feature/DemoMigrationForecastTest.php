<?php

namespace Tests\Feature;

use App\Models\{Barangay, MigrationRecord, MigrationReportingPeriod, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoMigrationForecastTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_is_restricted_to_approved_staff(): void
    {
        $this->get(route('migration.demo'))->assertRedirect(route('login'));
        $resident = User::factory()->create(['role' => User::ROLE_RESIDENT]);
        $this->actingAs($resident)->get(route('migration.demo'))->assertForbidden();
        $pending = User::factory()->create(['role' => User::ROLE_BARANGAY, 'approval_status' => User::APPROVAL_PENDING]);
        $this->actingAs($pending)->get(route('migration.demo'))->assertRedirect(route('approval.pending'));
    }

    public function test_each_scenario_shows_labeled_graphs_and_preserves_live_records(): void
    {
        $before = [MigrationRecord::count(), MigrationReportingPeriod::count()];
        $staff = User::factory()->create(['role' => User::ROLE_BARANGAY, 'barangay_id' => Barangay::firstOrFail()->id]);
        $snapshot = json_decode(file_get_contents(resource_path('demo/migration-forecast.json')), true);
        foreach (['stable' => 'Demo A - stable', 'trend' => 'Demo B - gradual trend', 'change' => 'Demo C - abrupt change'] as $key => $name) {
            $this->actingAs($staff)->get(route('migration.demo', ['scenario' => $key]))->assertOk()
                ->assertSee('DEMO DATA ONLY')->assertSee($name)->assertSee('Actual (synthetic)')
                ->assertSee('demo-arrivals-svg-title')->assertSee('demo-departures-svg-title')->assertSee('demo-net-svg-title')
                ->assertViewHas('evaluations', $snapshot['results'][$name]);
        }
        $this->assertSame($before, [MigrationRecord::count(), MigrationReportingPeriod::count()]);
        $municipal = User::factory()->create(['role' => User::ROLE_MUNICIPAL_LGU]);
        $this->actingAs($municipal)->get(route('migration.demo'))->assertOk();
        $this->actingAs($staff)->get(route('migration.dashboard'))->assertSee(route('migration.demo'));
        $this->getJson(route('migration.demo', ['scenario' => 'invalid']))->assertUnprocessable();
    }

    public function test_snapshot_metrics_and_net_predictions_match_the_test_values(): void
    {
        $report = json_decode(file_get_contents(resource_path('demo/migration-forecast.json')), true);
        $this->assertSame('SYNTHETIC_DEMO_ONLY', $report['data_source']);
        foreach ($report['results'] as $scenario) {
            foreach ($scenario as $evaluation) {
                $mae = array_sum(array_map(fn($r) => abs($r['actual'] - $r['predicted']), $evaluation['backtest'])) / 3;
                $this->assertEqualsWithDelta($mae, $evaluation['mae'], .00051);
                $this->assertSame(['2026-07', '2026-08', '2026-09'], array_column($evaluation['backtest'], 'month'));
            }
            $this->assertEqualsWithDelta($scenario['arrivals']['prediction'] - $scenario['departures']['prediction'], $scenario['net']['prediction'], 1e-9);
        }
    }
}
