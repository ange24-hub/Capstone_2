<?php

namespace Tests\Feature;

use App\Models\Barangay;
use App\Models\Household;
use App\Models\Inhabitant;
use App\Models\MigrationRecord;
use App\Models\User;
use App\Services\MigrationPredictionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MigrationForecastTest extends TestCase
{
    use RefreshDatabase;

    public function test_unverified_counts_preserve_scope_but_do_not_train_at_month_end(): void
    {
        $this->travelTo(now()->setDate(2026, 3, 31));
        $area = Barangay::firstOrFail();
        $other = Barangay::where('id', '!=', $area->id)->firstOrFail();
        $user = User::factory()->create(['role' => User::ROLE_BARANGAY, 'barangay_id' => $area->id]);
        $person = $this->person($area);
        foreach (['2025-11-01', '2025-12-01', '2026-01-01', '2026-02-01', '2026-02-02', '2026-03-01'] as $date) {
            $this->event($person, $date);
        }
        $this->event($this->person($other), '2026-02-01');
        Http::fake(['*/predict' => Http::response(['predicted_next_month_out_migration' => 4.2])]);
        $this->actingAs($user)->get(route('migration.dashboard', ['barangay_id' => $other->id]))
            ->assertOk()->assertViewHas('selectedBarangayId', $area->id)
            ->assertViewHas('totalOut', 4)
            ->assertViewMissing('forecastEvaluation')
            ->assertViewMissing('predictedOutMigration')
            ->assertDontSee('Prediction analytics');
        Http::assertNothingSent();
    }

    public function test_historical_unverified_empty_or_future_history_does_not_call_a_model(): void
    {
        $this->travelTo(now()->setDate(2026, 9, 23));
        $area = Barangay::firstOrFail();
        $user = User::factory()->create(['role' => User::ROLE_MUNICIPAL_LGU]);
        $person = $this->person($area);
        $this->event($person, '2025-12-01');
        $this->event($person, '2025-12-02');
        $this->event($person, '2025-11-01');
        Http::fake(fn () => throw new ConnectionException('offline'));
        $this->actingAs($user)->get(route('migration.dashboard', ['year' => 2025]))
            ->assertOk()->assertViewMissing('predictedOutMigration')->assertViewMissing('predictionMethod');
        Http::fake();
        foreach ([2024, 2027] as $year) {
            $this->get(route('migration.dashboard', ['year' => $year]))
                ->assertOk()->assertViewMissing('predictedOutMigration');
        }
        Http::assertNothingSent();
    }

    public function test_retired_model_adapter_never_calls_http_even_if_legacy_setting_is_enabled(): void
    {
        config(['services.migration_prediction.enabled' => true]);
        $features = ['out_migration_count' => 1, 'previous_month_out' => 1, 'out_3month_avg' => 1, 'year' => 2026, 'month' => 8];
        Http::fake(['*' => Http::sequence()->push([])
            ->push(['predicted_next_month_out_migration' => 'invalid'])
            ->push(['predicted_next_month_out_migration' => []])
            ->push([], 500)->push(['predicted_next_month_out_migration' => -2])]);
        for ($i = 0; $i < 4; $i++) {
            $this->assertNull(app(MigrationPredictionService::class)->predictOutMigration($features));
        }
        $this->assertNull(app(MigrationPredictionService::class)->predictOutMigration($features));
        Http::assertNothingSent();
    }

    public function test_demo_is_repeatable_and_removal_preserves_real_records_and_reused_profiles(): void
    {
        $area = Barangay::firstOrFail();
        $real = $this->person($area);
        $event = $this->event($real, '2026-01-01');
        $args = ['--barangay' => $area->id];
        $this->artisan('migration:demo', $args)->assertSuccessful();
        $count = MigrationRecord::count();
        $this->assertGreaterThan(50, $count);
        $this->artisan('migration:demo', $args)->assertSuccessful();
        $this->assertSame($count, MigrationRecord::count());
        $demo = Inhabitant::where('remarks', 'RBIM migration demo v1')->firstOrFail();
        $linked = $this->event($demo, '2026-02-01');
        $this->artisan('migration:demo', $args + ['--remove' => true])->assertSuccessful();
        $this->assertDatabaseCount('migration_records', 2);
        $this->assertDatabaseHas('migration_records', ['id' => $event->id]);
        $this->assertDatabaseHas('migration_records', ['id' => $linked->id]);
        $this->assertDatabaseHas('inhabitants', ['id' => $real->id]);
        $linked->delete();
        $this->artisan('migration:demo', $args + ['--remove' => true])->assertSuccessful();
        $this->assertDatabaseMissing('households', ['household_number' => 'DEMO-MIGRATION-V1']);
        $this->assertDatabaseCount('inhabitants', 1);
    }

    private function person(Barangay $area): Inhabitant
    {
        $household = Household::create(['barangay_id' => $area->id, 'household_number' => 'TEST']);

        return Inhabitant::create(['barangay_id' => $area->id, 'household_id' => $household->id,
            'first_name' => 'Test', 'last_name' => 'Resident', 'sex' => 'Female']);
    }

    private function event(Inhabitant $person, string $date): MigrationRecord
    {
        return MigrationRecord::create(['barangay_id' => $person->barangay_id, 'inhabitant_id' => $person->id,
            'type' => 'out', 'movement_date' => $date]);
    }
}
