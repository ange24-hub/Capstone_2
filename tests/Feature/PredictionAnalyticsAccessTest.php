<?php

namespace Tests\Feature;

use App\Models\Barangay;
use App\Models\User;
use App\Services\MigrationForecastReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PredictionAnalyticsAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_barangay_keeps_actual_trends_without_computing_or_receiving_predictions(): void
    {
        $area = Barangay::firstOrFail();
        $staff = User::factory()->create(['role' => User::ROLE_BARANGAY, 'barangay_id' => $area->id]);
        $this->mock(MigrationForecastReport::class)->shouldNotReceive('build');
        foreach (['trends', 'events'] as $section) {
            $this->actingAs($staff)->get(route('migration.dashboard', ['section' => $section]))
                ->assertOk()->assertSee('Migration Trends')
                ->assertDontSee('Prediction analytics')->assertDontSee('Verified forecast')
                ->assertDontSee(route('municipal.prediction-forecast'))
                ->assertViewMissing('forecastEvaluation')->assertViewMissing('forecastArrivalEvaluation')
                ->assertViewMissing('predictedInMigration')->assertViewMissing('predictedOutMigration')
                ->assertViewMissing('experimentalArrivalForecast')
                ->assertViewMissing('forecastMonths');
        }
        $this->get(route('barangay.rbi-updates.history'))->assertOk()->assertDontSee('Prediction analytics');
        $this->get(route('rbi.arrivals.index'))->assertOk()->assertDontSee('Prediction analytics');
    }

    public function test_barangay_and_resident_cannot_open_any_prediction_url_or_download(): void
    {
        $area = Barangay::firstOrFail();
        $urls = [route('municipal.prediction-forecast'), route('municipal.prediction-forecast-evidence'),
            route('municipal.prediction-svr'), route('municipal.prediction-analytics'),
            route('municipal.rbi-report-history'), route('municipal.prediction-history', $area),
            route('migration.history-experiment'), route('migration.demo'), route('analysis.forecast-readiness'),
            route('migration.dashboard', ['section' => 'forecast']),
            route('migration.dashboard', ['section' => 'forecast', 'barangay_id' => $area->id, 'review' => 1])];
        $this->mock(MigrationForecastReport::class)->shouldNotReceive('build');
        foreach ([User::ROLE_BARANGAY, User::ROLE_RESIDENT] as $role) {
            $user = User::factory()->create(['role' => $role, 'barangay_id' => $area->id]);
            foreach ($urls as $url) $this->actingAs($user)->get($url)->assertForbidden();
        }
    }

    public function test_approved_municipal_admin_keeps_prediction_navigation_and_page(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_MUNICIPAL_LGU]);
        $this->actingAs($admin)->get(route('dashboard.municipal'))->assertOk()
            ->assertSee('Prediction analytics')->assertSee(route('municipal.prediction-forecast'));
        $this->get(route('municipal.prediction-forecast'))->assertOk()->assertSee('Prediction analytics')
            ->assertViewHas('forecastEvaluation')->assertSee('Download forecast evidence');
    }

    public function test_pending_municipal_account_has_no_prediction_access(): void
    {
        $pending = User::factory()->create(['role' => User::ROLE_MUNICIPAL_LGU, 'approval_status' => User::APPROVAL_PENDING]);
        $this->actingAs($pending)->get(route('municipal.prediction-forecast'))->assertForbidden();
        $this->get(route('municipal.prediction-forecast-evidence'))->assertForbidden();
        $this->get(route('migration.dashboard', ['section' => 'forecast']))->assertForbidden();
    }
}
