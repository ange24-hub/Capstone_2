<?php

namespace Tests\Feature;

use App\Models\Barangay;
use App\Models\BarangayRbiUpdate;
use App\Models\RbiArrivalPeriod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Support\SubmittedArrivalFixture;
use Tests\TestCase;

class TwelveMonthHoldoutForecastTest extends TestCase
{
    use RefreshDatabase, SubmittedArrivalFixture;

    public function test_twelve_month_window_predicts_the_thirteenth_month_without_formal_review_writes(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 7));
        $area = Barangay::where('name', 'Cabascan')->firstOrFail();
        $staff = User::factory()->create(['role' => 'barangay', 'barangay_id' => $area->id]);
        $this->submittedArrivals($area, $staff, '2020-06', [0, 1, 0, 1, 0, 2, 4, 3, 0, 0, 1, 7, 1], false);
        $before = BarangayRbiUpdate::all()->toArray();
        $url = route('migration.history-experiment', ['barangay_id' => $area->id, 'mode' => 'holdout', 'method' => 'selected', 'end_month' => '2021-06']);
        $this->actingAs(User::factory()->create(['role' => 'municipal_lgu']))->get($url)->assertOk()->assertSee('12-month training and next-month test')->assertSee('June 2021 prediction')->assertSee('holdout-history-title')
            ->assertViewHas('evaluation', fn ($e) => $e['source_start'] === '2020-06' && $e['source_end'] === '2021-05' && $e['target_month'] === '2021-06' && $e['holdout_actual'] === 1);
        $this->assertSame($before, BarangayRbiUpdate::all()->toArray());
        $this->assertSame(0, RbiArrivalPeriod::count());
        // Removing the earliest zero report cannot silently invent June 2020 = 0.
        BarangayRbiUpdate::where('barangay_name', $area->name)->whereDate('reporting_month', '2020-06-01')->delete();
        $this->get($url)->assertOk()->assertSee('Complete the twelve training months first')->assertViewHas('history', fn ($h) => $h['rows'][0]['month'] === '2020-06' && $h['rows'][0]['count'] === null);
        $this->getJson(route('migration.history-experiment', ['mode' => 'unknown']))->assertUnprocessable();
    }

    public function test_runner_exports_the_separate_june_holdout_without_training_on_it(): void
    {
        Storage::fake('local');
        $area = Barangay::where('name', 'Cabascan')->firstOrFail();
        $staff = User::factory()->create(['role' => 'barangay', 'barangay_id' => $area->id]);
        $this->submittedArrivals($area, $staff, '2020-06', [0, 1, 0, 1, 0, 2, 4, 3, 0, 0, 1, 7, 1], false);
        $this->artisan('migration:history-experiment', ['--barangay' => $area->id, '--mode' => 'holdout', '--end-month' => '2021-06'])->assertSuccessful();
        $files = Storage::disk('local')->allFiles('private/forecast-history');
        $jsonPath = collect($files)->first(fn ($file) => str_ends_with($file, '.json'));
        $csvPath = collect($files)->first(fn ($file) => str_ends_with($file, '/test-results.csv'));
        $result = json_decode(Storage::disk('local')->get($jsonPath), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('holdout', $result['mode']);
        $this->assertSame('2021-05', $result['evaluation']['source_end']);
        $this->assertSame('2021-06', $result['evaluation']['target_month']);
        $this->assertSame(1, $result['evaluation']['holdout_actual']);
        $this->assertStringContainsString('2021-06,1,', Storage::disk('local')->get($csvPath));
        $this->assertDatabaseCount('rbi_arrival_periods', 0);
    }

    public function test_six_month_training_forecasts_december_through_may_and_exports_frozen_predictions(): void
    {
        Storage::fake('local');
        $area = Barangay::where('name', 'Cabascan')->firstOrFail();
        $staff = User::factory()->create(['role' => 'barangay', 'barangay_id' => $area->id]);
        $this->submittedArrivals($area, $staff, '2020-06', [0, 0, 0, 0, 0, 0, 4, 2, 0, 0, 1, 7, 1], false);
        $before = BarangayRbiUpdate::all()->toArray();
        $query = ['barangay_id' => $area->id, 'mode' => 'holdout', 'method' => 'mean_rate', 'end_month' => '2020-12', 'training_months' => 6, 'horizon' => 6];
        $this->actingAs(User::factory()->create(['role' => 'municipal_lgu']))->get(route('migration.history-experiment', $query))->assertOk()
            ->assertSee('ZERO ARRIVALS IN THE TRAINING WINDOW')->assertSee('December 2020–May 2021 prediction')->assertSee('2.33')
            ->assertViewHas('evaluation', fn ($e) => $e['source_start'] === '2020-06' && $e['source_end'] === '2020-11' && $e['actual_total'] === 14 && count($e['forecasts']) === 6)
            ->assertViewHas('history', fn ($h) => count($h['rows']) === 12 && $h['rows'][11]['month'] === '2021-05');
        $this->artisan('migration:history-experiment', ['--barangay' => $area->id, '--mode' => 'holdout', '--method' => 'mean_rate', '--end-month' => '2020-12', '--training-months' => 6, '--horizon' => 6])->assertSuccessful();
        $files = Storage::disk('local')->allFiles('private/forecast-history');
        $jsonPath = collect($files)->first(fn ($file) => str_ends_with($file, '/result.json'));
        $forecastPath = collect($files)->first(fn ($file) => str_ends_with($file, '/forecast.csv'));
        $result = json_decode(Storage::disk('local')->get($jsonPath), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('historical_mean_rate_fixed_origin_v1', $result['evaluation']['protocol']);
        $this->assertSame(6, $result['evaluation']['observed_months']);
        $this->assertEqualsWithDelta(14 / 6, $result['evaluation']['observed_mae'], 1e-12);
        $csv = Storage::disk('local')->get($forecastPath);
        $this->assertStringContainsString('2020-12,0,4,4,2020-11', $csv);
        $this->assertStringContainsString('2021-05,0,7,7,2020-11', $csv);
        $this->assertSame($before, BarangayRbiUpdate::all()->toArray());
        $this->assertDatabaseCount('rbi_arrival_periods', 0);
        $this->getJson(route('migration.history-experiment', array_merge($query, ['method' => 'selected'])))->assertUnprocessable();
    }

    public function test_bayesian_option_displays_and_exports_a_prior_without_creating_arrivals(): void
    {
        Storage::fake('local');
        $area = Barangay::where('name', 'Cabascan')->firstOrFail();
        $staff = User::factory()->create(['role' => 'barangay', 'barangay_id' => $area->id]);
        $this->submittedArrivals($area, $staff, '2020-06', [0, 0, 0, 0, 0, 0, 4, 2, 0, 0, 1, 7, 1], false);
        $before = BarangayRbiUpdate::all()->toArray();
        $this->actingAs(User::factory()->create(['role' => 'municipal_lgu']))->get(route('migration.history-experiment', ['barangay_id' => $area->id, 'method' => 'poisson_jeffreys', 'mode' => 'holdout', 'training_months' => 6, 'horizon' => 6, 'end_month' => '2020-12']))
            ->assertOk()->assertSee('Bayesian Poisson: (0 observed arrivals + 0.5 prior adjustment)')->assertSee('not an added resident')
            ->assertSee('2.31')->assertSee('2.3333')->assertSee('2.3056')->assertSee('Comparison with the mean-rate baseline')
            ->assertViewHas('evaluation', fn ($e) => $e['training_total'] === 0 && $e['actual_total'] === 14 && $e['posterior']['shape'] === 0.5);
        $this->artisan('migration:history-experiment', ['--barangay' => $area->id, '--mode' => 'holdout', '--method' => 'poisson_jeffreys', '--end-month' => '2020-12', '--training-months' => 6, '--horizon' => 6])->assertSuccessful();
        $jsonPath = collect(Storage::disk('local')->allFiles('private/forecast-history'))->first(fn ($file) => str_ends_with($file, '/result.json'));
        $result = json_decode(Storage::disk('local')->get($jsonPath), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('exploratory_fixed_prior', $result['evaluation']['method_choice']);
        $this->assertSame(0, $result['evaluation']['training_total']);
        $this->assertFalse($result['evaluation']['prior']['tuned']);
        $this->assertEqualsWithDelta(1 / 12, $result['evaluation']['forecasts'][0]['predicted'], 1e-12);
        $this->assertSame(4, $result['evaluation']['forecasts'][0]['actual']);
        $this->assertSame($before, BarangayRbiUpdate::all()->toArray());
        $this->assertDatabaseCount('rbi_arrival_periods', 0);
    }
}
