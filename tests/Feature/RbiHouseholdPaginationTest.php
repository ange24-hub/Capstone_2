<?php

namespace Tests\Feature;

use App\Models\Barangay;
use App\Models\BarangayRbiUpdate;
use App\Models\NewInhabitant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RbiHouseholdPaginationTest extends TestCase
{
    use RefreshDatabase;

    public function test_monthly_form_history_pages_are_scoped_and_keep_other_pagination_links(): void
    {
        $area = Barangay::where('name', 'Maslog')->firstOrFail();
        $staff = User::factory()->create(['role' => User::ROLE_BARANGAY, 'barangay_id' => $area->id]);
        foreach (range(1, 7) as $month) {
            BarangayRbiUpdate::create([
                'barangay_user_id' => $staff->id, 'barangay_name' => $area->name,
                'reporting_month' => sprintf('2026-%02d-01', $month),
                'status' => BarangayRbiUpdate::STATUS_DRAFT,
                'rows' => [['household_head' => 'Family '.$month, 'inhabitant_name' => 'Member '.$month]],
            ]);
        }
        $other = User::factory()->create(['role' => User::ROLE_BARANGAY, 'barangay_id' => $area->id]);
        BarangayRbiUpdate::create([
            'barangay_user_id' => $other->id, 'barangay_name' => $area->name,
            'reporting_month' => '2026-08-01', 'status' => BarangayRbiUpdate::STATUS_DRAFT,
        ]);

        $first = $this->actingAs($staff)->get(route('barangay.rbi-updates.history', ['household_page' => 2]))
            ->assertOk()->assertSee('July 2026')->assertSee('March 2026')
            ->assertDontSee('February 2026')->assertDontSee('January 2026')->assertDontSee('August 2026')
            ->assertSee('Showing <strong>1–5</strong> of 7 reports', false);
        $page = $first->viewData('reportHistoryPage');
        $this->assertSame(7, $page->total());
        $this->assertCount(5, $page->items());
        $this->assertStringContainsString('household_page=2', $page->nextPageUrl());
        $this->assertStringContainsString('#report-history', $page->nextPageUrl());

        $this->get($page->nextPageUrl())->assertOk()
            ->assertSee('February 2026')->assertSee('January 2026')
            ->assertDontSee('March 2026')->assertDontSee('August 2026')
            ->assertSee('Showing <strong>6–7</strong> of 7 reports', false);
        $this->get(route('barangay.rbi-updates.history', ['report_page' => 999]))->assertOk()
            ->assertViewHas('reportHistoryPage', fn ($history) => $history->currentPage() === 2);
    }

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
