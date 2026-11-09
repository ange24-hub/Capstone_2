<?php

namespace Tests\Feature;

use App\Models\{Barangay, User};
use App\Services\ExperimentalPooledSvrForecast;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RetiredMlForecastRoutesTest extends TestCase
{
    use RefreshDatabase;

    public function test_old_ml_pages_redirect_without_model_inference_and_old_writes_are_gone(): void
    {
        Http::preventStrayRequests();
        $this->mock(ExperimentalPooledSvrForecast::class)->shouldNotReceive('preview');
        $admin = User::factory()->create(['role' => User::ROLE_MUNICIPAL_LGU]);
        $area = Barangay::firstOrFail();
        $this->actingAs($admin);
        foreach (['municipal.prediction-pooled', 'municipal.prediction-pooled.evidence',
            'municipal.prediction-pooled.defense', 'municipal.prediction-forecast-evidence'] as $route) {
            $this->get(route($route, ['barangay_id' => $area->id]))
                ->assertRedirect(route('prediction.dashboard', ['barangay_id' => $area->id]));
        }
        $this->post(route('municipal.prediction-pooled.future.store'), ['barangay_id' => $area->id])->assertGone();
        $this->assertDatabaseCount('forecast_logs', 0);
        Http::assertNothingSent();
    }

    public function test_old_forecast_tab_redirects_to_scoped_wma_screen(): void
    {
        $area = Barangay::firstOrFail();
        $staff = User::factory()->create(['role' => User::ROLE_BARANGAY, 'barangay_id' => $area->id]);
        $this->actingAs($staff)->get(route('migration.dashboard', ['section' => 'forecast']))
            ->assertRedirect(route('prediction.dashboard'));
        $this->get(route('prediction.dashboard'))->assertOk()->assertViewHas('barangay', fn ($b) => $b->id === $area->id);
    }
}
