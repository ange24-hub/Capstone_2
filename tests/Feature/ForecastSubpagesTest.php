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
            ->assertOk()->assertSee('Provisional Population Estimate')->assertSee('Population outlook')
            ->assertSee(route('prediction.migration', ['barangay_id' => $area->id]))
            ->assertDontSee('Monthly migration')->assertDontSee('Save in/out forecasts')
            ->assertViewMissing('forecasts')->assertViewHas('forecastPage', 'population');
    }

    public function test_migration_subpage_keeps_scope_without_loading_population_reports(): void
    {
        $area = Barangay::where('name', 'Carnaga')->firstOrFail();
        $staff = User::factory()->create(['role' => User::ROLE_BARANGAY, 'barangay_id' => $area->id]);
        $this->mock(SemestralPopulationProjection::class)->shouldNotReceive('forBarangay');
        $this->actingAs($staff)->get(route('prediction.migration'))->assertOk()
            ->assertSee('Migration forecast')->assertSee('Monthly migration')
            ->assertSee(route('prediction.population', ['barangay_id' => $area->id]))
            ->assertDontSee('Population outlook')->assertDontSee('population-trend-title')
            ->assertViewMissing('populationProjection')->assertViewHas('forecastPage', 'migration');
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

    public function test_saving_a_migration_forecast_returns_to_the_migration_subpage(): void
    {
        $area = Barangay::firstOrFail();
        $staff = User::factory()->create(['role' => User::ROLE_BARANGAY, 'barangay_id' => $area->id]);
        $target = app(MigrationPredictionService::class)->calendar()->startOfMonth()->addMonth()->format('Y-m');
        $this->actingAs($staff)->post(route('prediction.store'), ['target_month' => $target])
            ->assertRedirect(route('prediction.migration', ['barangay_id' => $area->id]));
    }
}
