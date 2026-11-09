<?php

namespace Tests\Feature;

use App\Models\{Barangay, ForecastLog, Household, Inhabitant, MigrationLog, MigrationRecord, User};
use App\Services\{MigrationPredictionService, StatisticalMigrationLogSync};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class StatisticalMigrationForecastTest extends TestCase
{
    use RefreshDatabase;

    private Barangay $area;
    private User $staff;
    private MigrationPredictionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-11-08 12:00', 'Asia/Manila'));
        Http::preventStrayRequests();
        $this->area = Barangay::where('name', 'Hugpa')->firstOrFail();
        $this->staff = User::factory()->create(['role' => User::ROLE_BARANGAY, 'barangay_id' => $this->area->id]);
        $this->service = app(MigrationPredictionService::class);
    }

    private function person(?Barangay $area = null, array $extra = []): Inhabitant
    {
        $area ??= $this->area;
        $house = Household::firstOrCreate(['barangay_id' => $area->id, 'household_number' => 'WMA-1']);
        return Inhabitant::create($extra + ['barangay_id' => $area->id, 'household_id' => $house->id,
            'first_name' => 'Actual', 'last_name' => 'Fixture', 'sex' => 'Female', 'status' => 'active']);
    }

    private function events(string $month, int $count, string $type = 'in'): void
    {
        for ($i = 0; $i < $count; $i++) {
            $person = $this->person();
            MigrationRecord::create(['barangay_id' => $this->area->id, 'inhabitant_id' => $person->id,
                'type' => $type, 'movement_date' => $month.'-02']);
        }
    }

    private function verify(string $month, string $type = 'in'): ForecastLog
    {
        $snapshot = $this->service->monthSnapshot($this->area->id, $type, $month);
        return $this->service->confirmActualMonth($this->area->id, $type, $month, $this->staff->id, $snapshot['source_hash']);
    }

    public function test_cold_start_uses_only_active_non_demo_population_without_fabricated_accuracy(): void
    {
        $this->person(); $this->person();
        $this->person(extra: ['remarks' => \App\Services\MigrationForecastHistory::DEMO_MARKER]);
        $this->person(extra: ['status' => 'migrated_out']);
        $result = $this->service->forecastNextMonth($this->area->id, 'in');
        $this->assertSame('population_baseline', $result['method']);
        $this->assertSame(2, $result['population']);
        $this->assertSame(0.002, $result['predicted_value']);
        $this->assertSame('2026-12', $result['log_month']);
        $this->assertNull($this->service->calculateForecastAccuracy($this->area->id, 'in')['accuracy_rate']);
        $this->assertDatabaseCount('forecast_logs', 0); // Preview does not issue a prediction.
        Http::assertNothingSent();
    }

    public function test_wma_uses_verified_consecutive_months_and_excludes_current_and_future_data(): void
    {
        foreach (['2026-08' => 2, '2026-09' => 4, '2026-10' => 8] as $month => $count) {
            $this->events($month, $count); $this->verify($month);
        }
        $this->events('2026-11', 30); $this->events('2026-12', 40);
        $result = $this->service->forecastNextMonth($this->area->id, 'in');
        $this->assertSame('weighted_moving_average', $result['method']);
        $this->assertSame([1, 2, 3], $result['weights']);
        $this->assertSame(5.6667, $result['predicted_value']);
        $this->assertSame('2026-10', $result['data_through']);
        $this->assertCount(3, $result['history']);
        Http::assertNothingSent();
    }

    public function test_missing_month_is_unknown_but_confirmed_zero_is_valid_history(): void
    {
        $this->verify('2026-08'); $this->verify('2026-10');
        $this->assertSame('population_baseline', $this->service->forecastNextMonth($this->area->id, 'in')['method']);
        $this->assertSame('population_baseline', $this->service->forecastNextMonth($this->area->id, 'out')['method']);
        foreach (['2026-08', '2026-09', '2026-10'] as $month) $this->verify($month, 'out');
        $forecast = $this->service->forecastNextMonth($this->area->id, 'out');
        $this->assertSame('weighted_moving_average', $forecast['method']);
        $this->assertSame(0.0, $forecast['predicted_value']);
    }

    public function test_first_issue_is_immutable_and_accuracy_waits_for_actual_confirmation(): void
    {
        $this->person();
        $first = $this->service->issueForecast($this->area->id, 'in');
        $snapshot = $first->fresh()->getAttributes();
        $this->person();
        $second = $this->service->issueForecast($this->area->id, 'in');
        $this->assertSame($snapshot, $second->getAttributes());
        $this->assertNull($this->service->calculateForecastAccuracy($this->area->id, 'in')['mape']);
        $this->travelTo(\Illuminate\Support\Carbon::parse('2027-01-08 12:00', 'Asia/Manila'));
        $this->events('2026-12', 2);
        $this->assertNull($this->service->calculateForecastAccuracy($this->area->id, 'in')['mape']);
        $this->verify('2026-12');
        $this->assertSame(1, $this->service->calculateForecastAccuracy($this->area->id, 'in')['mape_months']);
        $this->assertSame($first->predicted_value, $first->fresh()->predicted_value);
    }

    public function test_mape_is_mean_percentage_error_excludes_zero_and_rejects_backdated_issues(): void
    {
        foreach ([['2026-07', 2, 1], ['2026-08', 10, 4], ['2026-09', 0, 3], ['2026-10', 1, 100]] as [$month, $actual, $predicted]) {
            $this->events($month, $actual);
            ForecastLog::create(['barangay_id' => $this->area->id, 'migration_type' => 'in', 'log_month' => $month,
                'predicted_value' => $predicted, 'issued_at' => $month === '2026-10' ? '2026-10-02' : '2026-06-01']);
            $this->verify($month);
        }
        $score = $this->service->calculateForecastAccuracy($this->area->id, 'in');
        $this->assertSame(55.0, $score['mape']);
        $this->assertSame(45.0, $score['accuracy_rate']);
        $this->assertSame(2, $score['mape_months']);
        $this->assertSame(1, $score['zero_actual_months']);
        $this->assertEqualsWithDelta(10 / 3, $score['mae'], 0.00001);
        $this->assertEqualsWithDelta(sqrt(46 / 3), $score['rmse'], 0.00001);
    }

    public function test_changed_sources_invalidate_confirmation_and_duplicate_events_block_it(): void
    {
        $this->events('2026-10', 1); $this->verify('2026-10');
        $this->assertTrue($this->service->monthSnapshot($this->area->id, 'in', '2026-10')['verified']);
        $source = MigrationRecord::firstOrFail();
        $source->update(['movement_date' => '2026-10-03']);
        $this->assertFalse($this->service->monthSnapshot($this->area->id, 'in', '2026-10')['verified']);
        MigrationRecord::create($source->only(['barangay_id', 'inhabitant_id', 'type', 'movement_date']));
        $this->expectException(ValidationException::class);
        $this->verify('2026-10');
    }

    public function test_demo_sync_is_excluded_and_repeated_sync_does_not_duplicate_or_change_dates(): void
    {
        $this->events('2020-07', 1);
        $demo = $this->person(extra: ['remarks' => \App\Services\MigrationForecastHistory::DEMO_MARKER]);
        MigrationRecord::create(['barangay_id' => $this->area->id, 'inhabitant_id' => $demo->id, 'type' => 'out', 'movement_date' => '2020-07-02']);
        $sync = app(StatisticalMigrationLogSync::class);
        $sync->syncBarangay($this->area->id); $sync->syncBarangay($this->area->id);
        $this->assertDatabaseCount('migration_logs', 1);
        $this->assertSame('2020-07-02', MigrationLog::first()->transaction_date->format('Y-m-d'));
        $this->assertDatabaseCount('forecast_logs', 0);
    }

    public function test_staff_scope_and_mutation_endpoints_are_protected_and_have_no_network_calls(): void
    {
        $other = Barangay::where('name', 'Looc')->firstOrFail();
        $this->person();
        $this->actingAs($this->staff)->get(route('prediction.migration'))->assertOk()
            ->assertViewIs('barangay.dashboard.prediction')->assertSee('Population-based assumption')->assertDontSee('SVR');
        $this->get(route('prediction.migration', ['barangay_id' => $other->id]))->assertForbidden();
        foreach (['prediction.store', 'prediction.verify', 'prediction.sync', 'prediction.movements.store'] as $route) {
            $this->post(route($route), ['barangay_id' => $other->id])->assertForbidden();
        }
        $this->post(route('prediction.store'), ['target_month' => '2026-12'])->assertRedirect();
        $this->assertDatabaseCount('forecast_logs', 2);
        $this->post(route('prediction.store'), ['target_month' => '2026-12'])->assertRedirect();
        $this->assertDatabaseCount('forecast_logs', 2);
        $this->post(route('prediction.verify'), ['log_month' => '2026-10', 'migration_type' => 'out', 'source_hash' => str_repeat('a', 64)])->assertSessionHasErrors('confirm_complete');
        $resident = User::factory()->create(['role' => User::ROLE_RESIDENT]);
        $this->actingAs($resident)->get(route('prediction.migration'))->assertForbidden();
        $pending = User::factory()->create(['role' => User::ROLE_MUNICIPAL_LGU, 'approval_status' => 'pending']);
        $this->actingAs($pending)->get(route('prediction.migration'))->assertForbidden();
        $admin = User::factory()->create(['role' => User::ROLE_MUNICIPAL_LGU]);
        $this->actingAs($admin)->get(route('prediction.migration', ['barangay_id' => $other->id]))->assertOk()->assertViewHas('barangay', fn ($area) => $area->id === $other->id);
        $this->post(route('municipal.prediction-pooled.future.store'))->assertGone();
        Http::assertNothingSent();
    }

    public function test_log_entry_requires_owned_resident_verified_date_and_no_duplicates(): void
    {
        $person = $this->person();
        $payload = ['resident_id' => $person->id, 'migration_type' => 'in', 'transaction_date' => '2026-10-14', 'confirm_date' => 1];
        $this->actingAs($this->staff)->post(route('prediction.movements.store'), $payload)->assertRedirect();
        $this->assertDatabaseCount('migration_logs', 1);
        $this->post(route('prediction.movements.store'), $payload)->assertSessionHasErrors('transaction_date');
        $otherPerson = $this->person(Barangay::where('name', 'Looc')->firstOrFail());
        $this->post(route('prediction.movements.store'), array_replace($payload, ['resident_id' => $otherPerson->id]))->assertNotFound();
        $this->post(route('prediction.movements.store'), array_replace($payload, ['transaction_date' => '2027-01-01']))->assertSessionHasErrors('transaction_date');
    }

    public function test_unimported_sources_cannot_be_confirmed_as_zero_and_negative_scores_are_not_clamped(): void
    {
        $this->events('2026-10', 1);
        MigrationLog::query()->delete(); // Existing database before its first sync.
        $snapshot = $this->service->monthSnapshot($this->area->id, 'in', '2026-10');
        $this->assertTrue($snapshot['unsynced']);
        try { $this->verify('2026-10'); $this->fail('An unsynced month cannot be verified.'); }
        catch (ValidationException $e) { $this->assertArrayHasKey('log_month', $e->errors()); }
        app(StatisticalMigrationLogSync::class)->syncBarangay($this->area->id);
        ForecastLog::create(['barangay_id'=>$this->area->id,'migration_type'=>'in','log_month'=>'2026-10',
            'predicted_value'=>4,'issued_at'=>'2026-09-01']);
        $this->verify('2026-10');
        $this->assertSame(-200.0, $this->service->calculateForecastAccuracy($this->area->id, 'in')['accuracy_rate']);
    }

    public function test_workspace_distinguishes_preview_and_saved_forecasts_without_replacing_the_first_issue(): void
    {
        $this->person();
        $this->actingAs($this->staff)->get(route('prediction.migration'))->assertOk()
            ->assertSee('Local prediction analytics')->assertSee('December 2026')
            ->assertSee('Save in/out forecasts')->assertSee('History cutoff')
            ->assertDontSee('Last usable month:');
        $this->post(route('prediction.store'), ['target_month' => '2026-12'])->assertRedirect();
        $first = ForecastLog::orderBy('id')->get()->map->getAttributes()->all();
        $this->person();
        $this->get(route('prediction.migration'))->assertOk()
            ->assertSee('Forecasts saved')->assertSee('Saved for December 2026')
            ->assertDontSee('Save in/out forecasts')
            ->assertViewHas('forecasts', fn ($forecasts) => $forecasts['in']['population'] === 1);
        $this->assertSame($first, ForecastLog::orderBy('id')->get()->map->getAttributes()->all());
        Http::assertNothingSent();
    }

    public function test_saved_log_pagination_keeps_scope_and_opens_the_results_section(): void
    {
        foreach (range(1, 25) as $index) {
            ForecastLog::create(['barangay_id' => $this->area->id, 'migration_type' => 'in',
                'log_month' => $this->service->calendar()->startOfMonth()->subMonths($index)->format('Y-m'),
                'predicted_value' => 1, 'method' => 'population_baseline', 'issued_at' => '2020-01-01']);
        }
        $response = $this->actingAs($this->staff)->get(route('prediction.migration', ['page' => 2]))->assertOk()
            ->assertSee('barangay_id='.$this->area->id.'&amp;page=1#saved-forecasts', false);
        $this->assertMatchesRegularExpression('/id="saved-forecasts"\s+open\b/', $response->getContent());
    }

    public function test_invalid_movement_returns_to_an_open_form_with_the_original_values(): void
    {
        $person = $this->person();
        $this->actingAs($this->staff)->from(route('prediction.migration'))
            ->post(route('prediction.movements.store'), ['resident_id' => $person->id,
                'migration_type' => 'out', 'transaction_date' => '2026-10-14'])
            ->assertSessionHasErrors('confirm_date')->assertRedirect(route('prediction.migration'));
        $response = $this->get(route('prediction.migration'))->assertOk()
            ->assertSee('value="2026-10-14"', false)
            ->assertSee('value="out" selected', false);
        $this->assertMatchesRegularExpression('/open\s*><summary>Record a verified movement/', $response->getContent());
    }

    public function test_chart_keeps_unknown_months_blank_and_only_compares_preissued_forecasts_in_scope(): void
    {
        $this->events('2026-08', 3); // Recorded, but coverage is not confirmed.
        $this->verify('2026-09'); // A verified zero must still be plotted.
        $this->events('2026-10', 2);
        $this->verify('2026-10');
        ForecastLog::create(['barangay_id' => $this->area->id, 'migration_type' => 'in',
            'log_month' => '2026-08', 'predicted_value' => 4, 'issued_at' => '2026-07-01']);
        ForecastLog::where('barangay_id', $this->area->id)->where('log_month', '2026-09')
            ->update(['predicted_value' => 99, 'issued_at' => '2026-09-05']);
        $other = Barangay::where('name', 'Looc')->firstOrFail();
        ForecastLog::create(['barangay_id' => $other->id, 'migration_type' => 'in',
            'log_month' => '2026-10', 'predicted_value' => 777, 'issued_at' => '2026-09-01']);

        $response = $this->actingAs($this->staff)->get(route('prediction.migration', ['page' => 2]))->assertOk();
        $rows = collect($response->viewData('migrationCharts')['in'])->keyBy('period');
        $this->assertNull($rows['2026-08']['actual']);
        $this->assertSame(4.0, $rows['2026-08']['predicted']);
        $this->assertSame(0, $rows['2026-09']['actual']);
        $this->assertNull($rows['2026-09']['predicted']);
        $this->assertSame(2, $rows['2026-10']['actual']);
        $this->assertNull($rows['2026-10']['predicted']);
        $this->assertNull($rows['2026-11']['actual']);
        $this->assertNull($rows['2026-12']['actual']);
        $this->assertSame($response->viewData('forecasts')['in']['predicted_value'], $rows['2026-12']['predicted']);
        $this->assertTrue($rows['2026-12']['assumption']);
        Http::assertNothingSent();
    }
}
