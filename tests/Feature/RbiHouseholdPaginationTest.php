<?php

namespace Tests\Feature;

use App\Models\Barangay;
use App\Models\NewInhabitant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RbiHouseholdPaginationTest extends TestCase
{
    use RefreshDatabase;

    public function test_history_has_its_own_staff_subpage_and_is_not_rendered_inside_entry_forms(): void
    {
        $staff = User::factory()->create(['role' => User::ROLE_BARANGAY,
            'barangay_id' => Barangay::where('name', 'Maslog')->value('id')]);
        $this->actingAs($staff);
        foreach (['barangay.rbi-updates.residents', 'barangay.rbi-updates.deceased'] as $route) {
            $this->get(route($route))->assertOk()->assertSee(route('barangay.rbi-updates.history'))
                ->assertDontSee('id="report-history"', false)->assertSee('id="rbi-monthly-form"', false);
        }
        $this->get(route('barangay.rbi-updates.history'))->assertOk()
            ->assertSee('id="report-history"', false)->assertDontSee('id="rbi-monthly-form"', false);
        $this->actingAs(User::factory()->create(['role' => User::ROLE_RESIDENT]))
            ->get(route('barangay.rbi-updates.history'))->assertForbidden();
    }

    public function test_pages_keep_families_together_and_preserve_complete_month_totals_and_actions(): void
    {
        $area = Barangay::where('name', 'Maslog')->firstOrFail();
        $staff = User::factory()->create(['role' => User::ROLE_BARANGAY, 'barangay_id' => $area->id]);
        foreach (range(1, 5) as $family) {
            foreach (range(1, 3) as $member) {
                NewInhabitant::create(['barangay_id' => $area->id, 'reporting_month' => '2026-09-01',
                    'household_number' => (string) $family, 'first_name' => "Family{$family}Member{$member}", 'last_name' => 'Example']);
            }
        }
        NewInhabitant::create(['barangay_id' => Barangay::where('name', 'Looc')->value('id'),
            'reporting_month' => '2026-09-01', 'household_number' => 'OTHER', 'first_name' => 'OtherBarangay', 'last_name' => 'Example']);
        $url = route('barangay.rbi-updates.history', ['new' => 1]);
        $first = $this->actingAs($staff)->get($url)->assertOk()
            ->assertSee('Family1Member1')->assertSee('Family1Member3')->assertDontSee('Family2Member3')
            ->assertDontSee('Family3Member1')->assertDontSee('OtherBarangay')
            ->assertSee('15 members')->assertSee('Showing 1 of 5 households')
            ->assertSee(route('registry.new-inhabitant-monthly-reports.pdf', '2026-09'))
            ->assertSee(route('registry.new-inhabitant-monthly-reports.submit', '2026-09'));
        $paginator = $first->viewData('savedHouseholdPage');
        $this->assertSame(5, $paginator->total());
        $this->assertSame(5, $paginator->lastPage());
        $this->assertCount(1, $paginator->items());
        $this->assertStringContainsString('new=1', $paginator->nextPageUrl());
        $this->assertStringContainsString('#saved-inhabitant-records', $paginator->nextPageUrl());
        $this->get($paginator->nextPageUrl())->assertOk()->assertSee('Family2Member1')->assertSee('Family2Member3')
            ->assertDontSee('Family1Member1')->assertDontSee('Family3Member1')->assertDontSee('Family5Member1');
        $this->get(route('barangay.rbi-updates.history', ['household_page' => 999]))->assertOk()
            ->assertSee('Family5Member3')->assertViewHas('savedHouseholdPage', fn ($page) => $page->currentPage() === 5);
    }

    public function test_undated_entries_and_month_boundaries_remain_accessible(): void
    {
        $area = Barangay::where('name', 'Maslog')->firstOrFail();
        $staff = User::factory()->create(['role' => User::ROLE_BARANGAY, 'barangay_id' => $area->id]);
        foreach (['2026-09-01', '2026-08-01', null] as $index => $month) {
            NewInhabitant::create(['barangay_id' => $area->id, 'reporting_month' => $month,
                'household_number' => '1', 'first_name' => 'Period'.$index, 'last_name' => 'Example']);
        }
        $this->actingAs($staff)->get(route('barangay.rbi-updates.history'))->assertOk()
            ->assertSee('September 2026')->assertDontSee('Period1')->assertDontSee('Period2');
        $this->get(route('barangay.rbi-updates.history', ['household_page' => 2]))->assertOk()
            ->assertSee('August 2026')->assertSee('Period1')->assertDontSee('Period0')->assertDontSee('Period2');
        $this->get(route('barangay.rbi-updates.history', ['household_page' => 3]))->assertOk()
            ->assertSee('Month not set')->assertSee('Period2')->assertDontSee('Period0');
    }
}
