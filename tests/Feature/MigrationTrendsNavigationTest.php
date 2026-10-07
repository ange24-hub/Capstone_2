<?php

namespace Tests\Feature;

use App\Models\Barangay;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MigrationTrendsNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_open_migration_trends_directly_and_old_bookmarks_redirect(): void
    {
        $area = Barangay::firstOrFail();
        $municipal = User::factory()->create(['role' => User::ROLE_MUNICIPAL_LGU]);
        $this->actingAs($municipal)->get(route('migration.dashboard'))
            ->assertOk()->assertSee('Migration Trends')->assertDontSee('Forecast Readiness');
        $this->get(route('analysis.forecast-readiness', ['barangay_id' => $area->id]))
            ->assertRedirect(route('migration.dashboard', ['barangay_id' => $area->id]));

        $secretary = User::factory()->create(['role' => User::ROLE_BARANGAY, 'barangay_id' => $area->id]);
        $this->actingAs($secretary)->get(route('migration.dashboard'))
            ->assertOk()->assertSee('Migration Trends')->assertDontSee('Forecast Readiness');
        $this->get(route('analysis.forecast-readiness', ['barangay_id' => $area->id + 1]))
            ->assertRedirect(route('migration.dashboard'));
    }

    public function test_old_link_keeps_role_access_and_assistant_points_to_migration_trends(): void
    {
        $this->get(route('analysis.forecast-readiness'))->assertRedirect(route('login'));
        $resident = User::factory()->create(['role' => User::ROLE_RESIDENT]);
        $this->actingAs($resident)->get(route('analysis.forecast-readiness'))->assertForbidden();

        $staff = User::factory()->create(['role' => User::ROLE_MUNICIPAL_LGU]);
        $this->actingAs($staff)->postJson(route('assistant.chat'), ['message' => 'Open forecast readiness'])
            ->assertOk()->assertJsonPath('actions.0.url', route('migration.dashboard'));
        $this->postJson(route('assistant.chat'), ['message' => 'Open migration trends'])
            ->assertOk()->assertJsonPath('actions.0.url', route('migration.dashboard'));
    }
}
