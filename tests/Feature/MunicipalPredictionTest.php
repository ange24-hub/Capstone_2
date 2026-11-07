<?php

namespace Tests\Feature;

use App\Models\Barangay;
use App\Models\BarangayRbiUpdate;
use App\Models\NewInhabitant;
use App\Models\RegistryActivity;
use App\Models\User;
use App\Services\DatedRbiHistorySample;
use App\Services\MunicipalRbiReportHistory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\SubmittedArrivalFixture;
use Tests\TestCase;

class MunicipalPredictionTest extends TestCase
{
    use RefreshDatabase, SubmittedArrivalFixture;

    public function test_forecasts_and_all_secretary_history_are_admin_only(): void
    {
        $area = Barangay::where('name', 'Cabascan')->firstOrFail();
        $staff = User::factory()->create(['role' => 'barangay', 'barangay_id' => $area->id]);
        foreach (['municipal.prediction-analytics', 'migration.history-experiment', 'migration.demo'] as $route) {
            $this->actingAs($staff)->get(route($route))->assertForbidden();
        }
        $this->get(route('migration.dashboard', ['section' => 'forecast']))->assertForbidden();
        $this->get(route('municipal.prediction-history', $area))->assertForbidden();
        $this->get(route('migration.dashboard'))->assertOk()->assertDontSee(route('municipal.prediction-analytics'))->assertDontSee(route('migration.history-experiment'));
        $this->postJson(route('assistant.chat'), ['message' => 'Predict migration next month', 'language' => 'en'])->assertOk()
            ->assertSee('administrators only')->assertJsonMissingPath('facts.predicted_departures');
        $admin = User::factory()->create(['role' => 'municipal_lgu']);
        $this->actingAs($admin)->get(route('municipal.prediction-analytics'))->assertOk()->assertSee('Prediction analytics')->assertSee('Not enough eligible dated history yet');
        $this->get(route('municipal.prediction-history', $area))->assertOk();
    }

    public function test_all_barangays_are_aggregated_and_last_six_months_are_reserved_without_writes(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 7));
        foreach (Barangay::all() as $area) {
            $owner = User::factory()->create(['role' => 'barangay', 'barangay_id' => $area->id]);
            $this->submittedArrivals($area, $owner, '2020-06', array_fill(0, 12, 1), false);
        }
        $admin = User::factory()->create(['role' => 'municipal_lgu']);
        $before = [BarangayRbiUpdate::all()->toArray(), NewInhabitant::all()->toArray(), RegistryActivity::count()];
        $response = $this->actingAs($admin)->get(route('municipal.prediction-analytics'));
        $response->assertOk()->assertSee('Reserved test results')->assertSee('municipal-prediction-title')
            ->assertViewHas('evaluation', fn ($e) => $e['ready'] && $e['source_end'] === '2020-11' && $e['test_start'] === '2020-12' && $e['test_end'] === '2021-05' && $e['initial_training_months'] === 6 && $e['actual_total'] === 174);
        $original = $response->viewData('evaluation');
        $this->assertSame(29, $response->viewData('history')['area_count']);
        $this->get(route('municipal.prediction-analytics', ['test_end' => '2021-06']))->assertOk()
            ->assertViewHas('evaluation', fn ($e) => ! $e['ready']);
        $this->assertSame($before, [BarangayRbiUpdate::all()->toArray(), NewInhabitant::all()->toArray(), RegistryActivity::count()]);
        $last = BarangayRbiUpdate::whereDate('reporting_month', '2021-05-01')->firstOrFail();
        $rows = $last->rows;
        $rows[0]['entry_type'] = 'birth';
        $rows[0]['birth_date'] = '2021-05-01';
        $last->update(['rows' => $rows]);
        $changed = $this->get(route('municipal.prediction-analytics'))->assertOk()->viewData('evaluation');
        $this->assertSame(array_column($original['backtest'], 'predicted'), array_column($changed['backtest'], 'predicted'));
        $this->assertNotSame($original['mae'], $changed['mae']);
        // Missing one barangay's source cannot silently turn a subset into a municipal total.
        BarangayRbiUpdate::where('barangay_name', $last->barangay_name)->delete();
        $this->get(route('municipal.prediction-analytics', ['scope' => 'municipal', 'barangay_id' => $last->barangayUser->barangay_id]))->assertOk()
            ->assertSee('Complete the municipal source counts first')->assertViewHas('evaluation', fn ($e) => ! $e['ready'])
            ->assertViewHas('history', fn ($h) => $h['area_count'] === 29 && $h['rows'][0]['count'] === null);
    }

    public function test_imported_and_saved_history_are_matched_and_confirmed_infant_migrants_are_preserved(): void
    {
        $area = Barangay::where('name', 'Cabascan')->firstOrFail();
        $owner = User::factory()->create(['role' => 'barangay', 'barangay_id' => $area->id]);
        $record = NewInhabitant::create(['barangay_id' => $area->id, 'reporting_month' => '2021-05-01', 'household_number' => '1', 'first_name' => 'Infant', 'last_name' => 'Confirmed', 'birth_date' => '2021-04-19']);
        BarangayRbiUpdate::create(['barangay_user_id' => $owner->id, 'barangay_name' => $area->name, 'reporting_month' => '2021-05-01', 'status' => 'draft', 'rows' => [['history_record_id' => $record->id, 'first_name' => 'Infant', 'last_name' => 'Confirmed', 'birth_date' => '2021-04-19', 'household_number' => '1', 'entry_type' => 'in_migration', 'previous_residence_unknown' => '1', 'arrival_month' => '2021-05']]]);
        NewInhabitant::create(['barangay_id' => $area->id, 'household_number' => '2', 'first_name' => 'Legacy', 'last_name' => 'Dated', 'month_submitted' => 'SEPTEMBER 10, 2024', 'birth_date' => '2000-01-01']);
        NewInhabitant::create(['barangay_id' => $area->id, 'household_number' => '3', 'first_name' => 'Unknown', 'last_name' => 'Year', 'month_submitted' => 'JULY']);
        $history = app(MunicipalRbiReportHistory::class)->build();
        $entries = $history['datasets'][$area->id]['entries'];
        $this->assertCount(1, $entries['2021-05']);
        $this->assertSame('in_migration', $entries['2021-05'][0]['type']);
        $this->assertSame('birth', $entries['2021-05'][0]['suggestion']['type']);
        $this->assertTrue($entries['2021-05'][0]['confirmed']);
        $this->assertFalse($entries['2021-05'][0]['estimated']);
        $this->assertArrayHasKey('2024-09', $entries);
        $this->assertNull($entries['2024-09'][0]['type']);
        $this->assertSame(1, $history['datasets'][$area->id]['undated']);
        $estimated = app(MunicipalRbiReportHistory::class)->build(true);
        $this->assertSame('in_migration', $estimated['datasets'][$area->id]['entries']['2024-09'][0]['type']);
        $this->assertTrue($estimated['datasets'][$area->id]['entries']['2024-09'][0]['estimated']);
        $this->assertTrue($estimated['datasets'][$area->id]['entries']['2021-05'][0]['confirmed']);
        $this->assertFalse($estimated['datasets'][$area->id]['entries']['2021-05'][0]['estimated']);
        $admin = User::factory()->create(['role' => 'municipal_lgu']);
        $this->actingAs($admin)->get(route('municipal.prediction-history', [$area, 'month' => '2021-05']))->assertOk()
            ->assertSee('Infant Confirmed')->assertSee('In-migration')->assertSee('Probable newborn');
        $this->get(route('municipal.prediction-analytics', ['basis' => 'age_estimates']))->assertOk()->assertSee('AGE-BASED ESTIMATES')->assertSee('No source classifications are overwritten');
    }

    public function test_date_complete_sample_can_train_without_claiming_complete_municipal_coverage(): void
    {
        $area = Barangay::where('name', 'Cabascan')->firstOrFail();
        $owner = User::factory()->create(['role' => 'barangay', 'barangay_id' => $area->id]);
        $this->submittedArrivals($area, $owner, '2020-06', array_fill(0, 12, 1), false);
        NewInhabitant::create(['barangay_id' => $area->id, 'household_number' => '1', 'reporting_month' => '2020-06-01', 'first_name' => 'No', 'last_name' => 'Birthday']);
        NewInhabitant::create(['barangay_id' => $area->id, 'household_number' => '2', 'month_submitted' => 'JULY', 'birth_date' => '1990-01-01', 'first_name' => 'No', 'last_name' => 'ReportYear']);
        $before = [BarangayRbiUpdate::all()->toArray(), NewInhabitant::all()->toArray(), RegistryActivity::count()];
        $admin = User::factory()->create(['role' => 'municipal_lgu']);
        $response = $this->actingAs($admin)->get(route('municipal.prediction-analytics'))->assertOk()
            ->assertSee('DATE-COMPLETE RECORD SAMPLE')->assertSee('AGE-BASED ESTIMATES')
            ->assertViewHas('sample', fn ($s) => $s['included_entries'] === 12 && $s['included_area_count'] === 1 && $s['missing_birth_date'] === 1 && $s['undated_source_rows'] === 1)
            ->assertViewHas('evaluation', fn ($e) => $e['ready'] && $e['initial_training_months'] === 6 && $e['actual_total'] === 6 && $e['test_start'] === '2020-12' && $e['test_end'] === '2021-05');
        $this->assertSame(29, $response->viewData('history')['area_count']);
        $this->assertNull($response->viewData('history')['rows'][0]['count']);
        $this->get(route('municipal.prediction-analytics', ['scope' => 'municipal']))->assertOk()
            ->assertViewHas('evaluation', fn ($e) => ! $e['ready']);
        $this->assertSame($before, [BarangayRbiUpdate::all()->toArray(), NewInhabitant::all()->toArray(), RegistryActivity::count()]);
    }

    public function test_sample_excludes_invalid_dates_and_ambiguous_sources_and_preserves_confirmed_exceptions(): void
    {
        $area = Barangay::where('name', 'Cabascan')->firstOrFail();
        $owner = User::factory()->create(['role' => 'barangay', 'barangay_id' => $area->id]);
        $this->submittedArrivals($area, $owner, '2021-01', [1], false);
        // Confirmed infant arrival takes precedence over the newborn age suggestion.
        $report = BarangayRbiUpdate::firstOrFail();
        $rows = $report->rows;
        $rows[0]['birth_date'] = '2020-12-01';
        $report->update(['rows' => $rows]);
        NewInhabitant::create(['barangay_id' => $area->id, 'household_number' => '1', 'reporting_month' => '2021-03-01', 'birth_date' => '1990-01-01', 'first_name' => 'Adult', 'last_name' => 'Estimated']);
        NewInhabitant::create(['barangay_id' => $area->id, 'household_number' => '2', 'reporting_month' => '2021-03-01', 'birth_date' => '2021-04-01', 'first_name' => 'Future', 'last_name' => 'Birthday']);
        NewInhabitant::create(['barangay_id' => $area->id, 'household_number' => '3', 'reporting_month' => '2021-03-01', 'first_name' => 'Missing', 'last_name' => 'Birthday']);
        $source = app(MunicipalRbiReportHistory::class);
        $service = app(DatedRbiHistorySample::class);
        $confirmed = $service->build($source->build());
        $this->assertSame(1, $confirmed['included_entries']);
        $this->assertSame(1, $confirmed['unclassified_entries']);
        $estimated = $service->build($source->build(true));
        $this->assertSame(2, $estimated['included_entries']);
        $this->assertSame(1, $estimated['estimated_entries']);
        $this->assertSame(2, $estimated['missing_birth_date']);
        $this->assertSame([1, 0, 1], array_column($estimated['rows'], 'count'));
        $this->assertTrue($estimated['rows'][1]['no_sample_entries']);
        $this->assertNull($source->build(true)['rows'][1]['count']);
        BarangayRbiUpdate::create(['barangay_user_id' => $owner->id, 'barangay_name' => $area->name, 'reporting_month' => '2021-01-01', 'rows' => [], 'status' => 'draft']);
        $ambiguous = $service->build($source->build(true));
        $this->assertSame(1, $ambiguous['ambiguous_entries']);
        $this->assertSame(1, $ambiguous['included_entries']);
        $this->assertSame('2021-03', $ambiguous['rows'][0]['month']);
    }
}
