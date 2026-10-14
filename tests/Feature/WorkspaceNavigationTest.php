<?php

namespace Tests\Feature;

use App\Models\Barangay;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkspaceNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_barangay_task_pages_show_only_the_selected_queue(): void
    {
        $secretary = User::factory()->create([
            'role' => User::ROLE_BARANGAY,
            'barangay_id' => Barangay::where('name', 'Maslog')->firstOrFail()->id,
        ]);

        $this->actingAs($secretary)->get(route('barangay.resident-approvals.index'))
            ->assertOk()
            ->assertSee('id="resident-approvals-title"', false)
            ->assertDontSee('id="document-requests-title"', false)
            ->assertDontSee('id="community-summary-title"', false)
            ->assertDontSee('id="rbi-history-title"', false)
            ->assertDontSee('Ready for your review');

        $this->get(route('barangay.document-requests.index'))
            ->assertOk()
            ->assertSee('id="document-requests-title"', false)
            ->assertDontSee('id="resident-approvals-title"', false)
            ->assertDontSee('id="community-summary-title"', false)
            ->assertDontSee('id="rbi-history-title"', false);

        $this->get(route('dashboard.barangay'))->assertOk()
            ->assertSeeInOrder(['id="brgy-reporting-title"', 'id="brgy-pending-title"', 'id="community-summary-title"'], false)
            ->assertSee(route('barangay.rbi-updates.residents', ['new' => 1]))
            ->assertDontSee('id="resident-approvals-title"', false)
            ->assertDontSee('id="document-requests-title"', false);
    }

    public function test_resident_screens_separate_overview_request_form_and_history(): void
    {
        $resident = User::factory()->create(['barangay_id' => Barangay::firstOrFail()->id]);

        $this->actingAs($resident)->get(route('dashboard.resident'))->assertOk()
            ->assertSee('aria-label="Request summary"', false)
            ->assertDontSee('id="request-form-title"', false)
            ->assertDontSee('id="request-history"', false);

        $this->get(route('resident.document-requests.create'))->assertOk()
            ->assertSee('id="request-form-title"', false)
            ->assertDontSee('id="request-history"', false)
            ->assertDontSee('aria-label="Request summary"', false)
            ->assertDontSee('aria-label="Resident service shortcuts"', false);

        $this->get(route('resident.document-requests.index'))->assertOk()
            ->assertSee('id="request-history"', false)
            ->assertDontSee('id="request-form-title"', false)
            ->assertDontSee('aria-label="Request summary"', false);
    }

    public function test_navigation_is_grouped_by_role_with_one_current_destination(): void
    {
        foreach ([User::ROLE_BARANGAY, User::ROLE_MUNICIPAL_LGU, User::ROLE_RESIDENT] as $role) {
            $user = User::factory()->create([
                'role' => $role,
                'barangay_id' => $role === User::ROLE_MUNICIPAL_LGU ? null : Barangay::firstOrFail()->id,
            ]);
            $route = match ($role) {
                User::ROLE_BARANGAY => 'barangay.resident-approvals.index',
                User::ROLE_MUNICIPAL_LGU => 'municipal.approvals.index',
                default => 'resident.document-requests.index',
            };
            $response = $this->actingAs($user)->get(route($route))->assertOk();
            preg_match('/<nav class="side-nav"[^>]*>(.*?)<\/nav>/s', $response->getContent(), $matches);
            $this->assertNotEmpty($matches);
            $navigation = $matches[1];
            $this->assertSame(1, substr_count($navigation, 'aria-current="page"'));
            $this->assertStringContainsString('href="'.route($route).'"', $navigation);
            if ($role === User::ROLE_BARANGAY) {
                $this->assertStringContainsString('RBI Forms', $navigation);
                $this->assertLessThan(strpos($navigation, '>Services<'), strpos($navigation, '>RBI Forms<'));
                $this->assertStringContainsString('Resident records', $navigation);
                $this->assertStringContainsString(route('offline.household'), $navigation);
                $this->assertStringNotContainsString(route('municipal.approvals.index'), $navigation);
            } elseif ($role === User::ROLE_MUNICIPAL_LGU) {
                $this->assertStringContainsString('Administration', $navigation);
                $this->assertStringNotContainsString(route('barangay.rbi-updates.index'), $navigation);
            } else {
                $this->assertStringContainsString('My services', $navigation);
                $this->assertStringNotContainsString(route('barangay.registry.active'), $navigation);
            }
        }
    }
}
