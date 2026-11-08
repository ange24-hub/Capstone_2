<?php

namespace Tests\Feature;

use App\Models\{Barangay,User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PredictionCleanupTest extends TestCase
{
    use RefreshDatabase;

    public function test_main_screen_and_navigation_do_not_advertise_retired_experiments(): void
    {
        $this->actingAs(User::factory()->create(['role'=>User::ROLE_MUNICIPAL_LGU]));
        foreach(['dashboard.municipal','municipal.prediction-forecast','migration.dashboard']as$route){
            $this->get(route($route))->assertOk()->assertDontSee('Scenario projections')
                ->assertDontSee('Pooled SVR evidence')->assertDontSee('Open uploaded SVR preview')
                ->assertSee('href="'.route('municipal.prediction-forecast').'"',false);
        }
    }

    public function test_retired_bookmarks_redirect_with_filters_and_no_longer_run_experiments(): void
    {
        $this->actingAs(User::factory()->create(['role'=>User::ROLE_MUNICIPAL_LGU]));
        $area=Barangay::where('name','Cabascan')->firstOrFail();
        $filters=['barangay_id'=>$area->id,'year'=>2026];
        foreach(['migration.demo','migration.history-experiment','municipal.prediction-svr','municipal.prediction-scenarios','municipal.prediction-analytics']as$route)
            $this->get(route($route,$filters))->assertRedirect(route('municipal.prediction-forecast',$filters));
        $this->post('/municipal/prediction-analytics/scenarios',['in_rate'=>1])->assertStatus(405);
    }
}
