<?php

namespace Tests\Feature;

use App\Models\Barangay;
use App\Models\BarangayRbiUpdate;
use App\Models\Household;
use App\Models\Inhabitant;
use App\Models\MigrationRecord;
use App\Models\MigrationReportingPeriod;
use App\Models\RbiArrivalPeriod;
use App\Models\RegistryActivity;
use App\Models\User;
use App\Services\MigrationForecastHistory;
use App\Services\MigrationForecastReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Support\SubmittedArrivalFixture;
use Tests\TestCase;

class AdminSeasonalForecastTest extends TestCase
{
    use RefreshDatabase, SubmittedArrivalFixture;

    private function history(array $in, array $out, string $start = '2025-04'): array
    {
        $area = Barangay::where('name', 'Looc')->firstOrFail();
        $owner = User::factory()->create(['name' => 'Seasonal Secretary Fixture', 'role' => 'barangay', 'barangay_id' => $area->id]);
        $home = Household::create(['barangay_id' => $area->id, 'household_number' => 'SEASONAL-INTEGRATION']);
        $person = Inhabitant::create(['barangay_id' => $area->id, 'household_id' => $home->id, 'first_name' => 'Private', 'last_name' => 'Fixture', 'sex' => 'Female']);
        $this->submittedArrivals($area, $owner, $start, $in, false);
        $last = BarangayRbiUpdate::orderByDesc('reporting_month')->firstOrFail();
        $last->update(['rows' => array_merge($last->rows, [
            ['first_name' => 'Newborn', 'last_name' => 'Excluded', 'birth_date' => $last->reporting_month->format('Y-m-d'), 'entry_type' => 'birth'],
            ['first_name' => 'Late', 'last_name' => 'Excluded', 'birth_date' => '1990-01-01', 'entry_type' => 'late_registration'],
        ])]);
        $this->reviewArrivals($area, $owner);
        foreach ($out as $i => $count) {
            $month = Carbon::createFromFormat('!Y-m', $start)->addMonths($i);
            for ($j = 0; $j < $count; $j++) {
                MigrationRecord::create(['barangay_id' => $area->id, 'inhabitant_id' => $person->id, 'type' => 'out', 'movement_date' => $month->copy()->addDays($j)->toDateString()]);
            }
            $source = app(MigrationForecastHistory::class)->build($area->id, $month, 1)['rows'][0];
            MigrationReportingPeriod::create(['barangay_id' => $area->id, 'month' => $month->format('Y-m'), 'out_count' => $count,
                'source_fingerprint' => $source['fingerprint'], 'verified_by' => $owner->id, 'verified_at' => now()]);
        }

        return [$area, $owner, $person];
    }

    public function test_admin_forecast_uses_actual_rbi_and_departure_counts_without_writing_or_reusing_csv_results(): void
    {
        $this->travelTo(Carbon::parse('2026-11-07 10:00:00'));
        $in = [1, 2, 0, 3, 1, 2, 4, 0, 2, 1, 3, 2, 1, 4, 2, 0, 1, 3];
        $out = [0, 1, 2, 0, 1, 2, 1, 3, 4, 0, 2, 1, 0, 2, 3, 1, 2, 0];
        [$area, $owner, $person] = $this->history($in, $out);
        // Legacy arrivals and marked demonstration departures are not training inputs.
        MigrationRecord::create(['barangay_id' => $area->id, 'inhabitant_id' => $person->id, 'type' => 'in', 'movement_date' => '2025-12-01']);
        MigrationRecord::create(['barangay_id' => $area->id, 'inhabitant_id' => $person->id, 'type' => 'out', 'movement_date' => '2025-12-25', 'reason' => MigrationForecastHistory::DEMO_MARKER]);
        $before = [BarangayRbiUpdate::all()->toArray(), MigrationRecord::all()->toArray(), MigrationReportingPeriod::all()->toArray(), RbiArrivalPeriod::all()->toArray(), RegistryActivity::count()];
        $admin = User::factory()->create(['name' => 'Seasonal Admin Fixture', 'role' => 'municipal_lgu']);
        $response = $this->actingAs($admin)->get(route('municipal.prediction-forecast', ['barangay_id' => $area->id]));
        $response->assertOk()->assertSee('Prediction analytics')->assertSee('Seasonal naive (same month last year)')
            ->assertViewHas('predictedInMigration', 2.0)->assertViewHas('predictedOutMigration', 4.0)->assertViewHas('predictedNetMigration', -2.0)
            ->assertViewHas('forecastEvaluation', fn ($e) => $e['method_key'] === 'seasonal_naive_12' && $e['reference_month'] === '2025-12' && count($e['backtest']) === 3)
            ->assertViewHas('forecastMonths', fn ($rows) => count($rows) === 6 && $rows[0]['month'] === '2026-12' && $rows[5]['month'] === '2027-05' && $rows[0]['net'] === -2.0)
            ->assertSee('Next six monthly estimates')->assertSee('Actual versus predicted net migration')->assertDontSee('Private Fixture');
        $this->assertSame($before, [BarangayRbiUpdate::all()->toArray(), MigrationRecord::all()->toArray(), MigrationReportingPeriod::all()->toArray(), RbiArrivalPeriod::all()->toArray(), RegistryActivity::count()]);
        $this->assertSame(1, $response->viewData('forecastSource')['excluded_demo_events']);
        $this->actingAs($owner)->get(route('municipal.prediction-forecast'))->assertForbidden();
        $this->get(route('migration.dashboard', ['section' => 'forecast']))->assertForbidden();
        $this->get(route('migration.dashboard'))->assertOk()->assertDontSee('Next six monthly estimates')->assertDontSee(route('municipal.prediction-forecast'));
        $this->actingAs(User::factory()->create(['role' => 'resident']))->get(route('municipal.prediction-forecast'))->assertForbidden();
        $this->actingAs($admin)->get(route('municipal.prediction-forecast'))->assertOk()->assertSee('Municipal forecast is not ready')
            ->assertViewHas('predictedNetMigration', null);
        // A reviewed arrival source edit invalidates the fit until the source is reviewed again.
        $last = BarangayRbiUpdate::orderByDesc('reporting_month')->firstOrFail();
        $rows = $last->rows;
        $rows[] = ['first_name' => 'Extra', 'last_name' => 'Arrival', 'birth_date' => '1990-01-01', 'entry_type' => 'in_migration', 'previous_residence' => 'Cebu', 'arrival_date' => '2026-09-20'];
        $last->update(['rows' => $rows]);
        $stale = app(MigrationForecastReport::class)->build($area->id);
        $this->assertNull($stale['predictedInMigration']);
        $this->assertNull($stale['predictedNetMigration']);
        $this->assertSame(4.0, $stale['predictedOutMigration']);
        $original = $response->viewData('forecastArrivalEvaluation');
        $this->reviewArrivals($area, $owner);
        $reviewed = app(MigrationForecastReport::class)->build($area->id);
        $this->assertSame(2.0, $reviewed['predictedInMigration']);
        $this->assertSame(array_column($original['backtest'], 'predicted'), array_column($reviewed['forecastArrivalEvaluation']['backtest'], 'predicted'));
        $this->assertNotSame($original['mae'], $reviewed['forecastArrivalEvaluation']['mae']);
    }

    public function test_full_year_history_can_forecast_without_reporting_unmeasured_errors_as_zero(): void
    {
        $this->travelTo(Carbon::parse('2026-01-07 10:00:00'));
        [$area] = $this->history(array_fill(0, 12, 0), array_fill(0, 12, 1), '2025-01');
        $admin = User::factory()->create(['role' => 'municipal_lgu']);
        $this->actingAs($admin)->get(route('municipal.prediction-forecast', ['barangay_id' => $area->id, 'year' => 2025]))
            ->assertOk()->assertViewHas('predictedInMigration', 0.0)->assertViewHas('predictedOutMigration', 1.0)->assertViewHas('predictedNetMigration', -1.0)
            ->assertViewHas('forecastEvaluation', fn ($e) => $e['ready'] && ! $e['backtest_ready'] && $e['mae'] === null)
            ->assertViewHas('forecastNetEvaluation', fn ($e) => $e['ready'] && $e['mae'] === null)
            ->assertSee('Held-out error not measured yet')->assertSee('Not measured');
        $this->postJson(route('assistant.chat'), ['message' => 'Predict net migration next month in Looc', 'language' => 'en'])->assertOk()
            ->assertJsonPath('facts.predicted_net_migration', -1)->assertJsonPath('facts.forecast_month', 'February 2026')
            ->assertJsonPath('facts.mae', null)->assertSee('held-out error not measured yet');
        // The same historical report remains a read-only fit using only the selected year's data.
        $report = app(MigrationForecastReport::class)->build($area->id, 2025);
        $this->assertSame('January 2026', $report['predictionMonthLabel']);
        $this->assertSame('2025-12', $report['forecastEvaluation']['source_end']);
    }
}
