<?php

namespace Tests\Feature;

use App\Models\{Barangay, User};
use App\Services\{MigrationPredictionService, SemestralPopulationProjection};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ForecastSubpagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_population_subpage_keeps_scope_without_loading_migration_data(): void
    {
        $area = Barangay::where('name', 'Carnaga')->firstOrFail();
        $admin = User::factory()->create(['role' => User::ROLE_MUNICIPAL_LGU]);
        $this->mock(MigrationPredictionService::class)->shouldNotReceive('forecastNextMonth');
        $this->actingAs($admin)->get(route('prediction.population', ['barangay_id' => $area->id]))
            ->assertOk()->assertSee('Population forecast')->assertSee('Population outlook')
            ->assertDontSee('Monthly migration')->assertDontSee('Save in/out forecasts')
            ->assertViewMissing('forecasts')->assertViewHas('forecastPage', 'population');
    }

    public function test_migration_records_page_keeps_movement_workflows_without_forecasting(): void
    {
        $area = Barangay::where('name', 'Carnaga')->firstOrFail();
        $staff = User::factory()->create(['role' => User::ROLE_BARANGAY, 'barangay_id' => $area->id]);
        $this->mock(SemestralPopulationProjection::class)->shouldNotReceive('forBarangay');
        $this->actingAs($staff)->get(route('prediction.migration'))->assertOk()
            ->assertViewIs('barangay.dashboard.migration-records')->assertSee('Migration records')
            ->assertSee(route('prediction.population', ['barangay_id' => $area->id]))
            ->assertSee('Sync existing dated movement records')->assertSee('Save movement event')
            ->assertDontSee('Migration forecast')->assertDontSee('Save in/out forecasts')
            ->assertViewMissing('populationProjection');
    }

    public function test_municipal_population_forecast_defaults_to_carnaga(): void
    {
        $area = Barangay::where('name', 'Carnaga')->firstOrFail();
        $admin = User::factory()->create(['role' => User::ROLE_MUNICIPAL_LGU]);
        $this->actingAs($admin)->get(route('prediction.dashboard'))->assertOk()
            ->assertViewHas('barangay', fn ($selected) => $selected->id === $area->id)
            ->assertSee('Barangay Carnaga')->assertSee('Population forecast');
    }

    public function test_both_subpages_enforce_existing_account_and_barangay_permissions(): void
    {
        $area = Barangay::firstOrFail();
        $other = Barangay::whereKeyNot($area->id)->firstOrFail();
        $staff = User::factory()->create(['role' => User::ROLE_BARANGAY, 'barangay_id' => $area->id]);
        foreach (['prediction.population', 'prediction.migration'] as $route) {
            $this->actingAs($staff)->get(route($route, ['barangay_id' => $other->id]))->assertForbidden();
            foreach ([
                ['role' => User::ROLE_RESIDENT],
                ['role' => User::ROLE_MUNICIPAL_LGU, 'approval_status' => 'pending'],
            ] as $attributes) {
                $this->actingAs(User::factory()->create($attributes))->get(route($route))->assertForbidden();
            }
        }
    }

    public function test_legacy_migration_forecast_submit_redirects_to_population_without_saving_predictions(): void
    {
        $area = Barangay::firstOrFail();
        $staff = User::factory()->create(['role' => User::ROLE_BARANGAY, 'barangay_id' => $area->id]);
        $this->actingAs($staff)->post(route('prediction.store'))
            ->assertRedirect(route('prediction.dashboard', ['barangay_id' => $area->id]));
        $this->assertDatabaseCount('forecast_logs', 0);
    }
}
