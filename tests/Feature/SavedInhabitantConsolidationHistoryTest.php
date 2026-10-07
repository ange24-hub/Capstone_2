<?php

namespace Tests\Feature;

use App\Models\Barangay;
use App\Models\NewInhabitant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SavedInhabitantConsolidationHistoryTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();
        $this->staff = User::factory()->create([
            'role' => User::ROLE_BARANGAY,
            'barangay_id' => Barangay::where('name', 'San Roque')->value('id'),
        ]);
        $this->actingAs($this->staff);
    }

    private function member(string $name, string $household = '135', ?string $month = '2026-09-01'): NewInhabitant
    {
        return NewInhabitant::create([
            'barangay_id' => $this->staff->barangay_id, 'household_number' => $household,
            'reporting_month' => $month, 'first_name' => $name, 'last_name' => 'Example', 'sex' => 'Female',
        ]);
    }

    public function test_added_member_moves_out_of_pending_and_into_history_without_losing_saved_records(): void
    {
        $added = $this->member('AddedMember');
        $pending = $this->member('PendingMember');
        $this->post(route('registry.new-inhabitants.add-to-active', $added))
            ->assertSessionHasNoErrors()->assertRedirect(route('barangay.rbi-updates.history'));

        $response = $this->get(route('barangay.rbi-updates.history'))->assertOk();
        $data = $response->original->getData();
        $pendingHtml = view('rbi-updates._saved-inhabitants', $data)->render();
        $historyHtml = view('rbi-updates._consolidated-inhabitants', $data)->render();
        $this->assertStringContainsString('PendingMember Example', $pendingHtml);
        $this->assertStringNotContainsString('AddedMember Example', $pendingHtml);
        $this->assertStringContainsString('AddedMember Example', $historyHtml);
        $this->assertStringNotContainsString('PendingMember Example', $historyHtml);
        foreach (['edit', 'destroy', 'add-to-active'] as $action) {
            $this->assertStringNotContainsString(route('registry.new-inhabitants.'.$action, $added), $historyHtml);
        }
        $this->assertCount(1, $data['savedHouseholdPage']->items()[0]['members']);
        $this->assertSame(2, $data['completeReportMonths']->get('September 2026')->count());
        $this->assertDatabaseCount('new_inhabitants', 2);
        $this->assertDatabaseHas('inhabitants', ['id' => $added->fresh()->active_inhabitant_id]);
        $this->assertNull($pending->fresh()->active_inhabitant_id);
        $this->assertNotNull($added->fresh()->added_to_active_at);
    }

    public function test_whole_household_leaves_pending_and_monthly_report_actions_remain_in_history(): void
    {
        $first = $this->member('FamilyFirst');
        $second = $this->member('FamilySecond');
        $this->post(route('registry.new-inhabitant-families.add-to-active'), [
            'household_number' => '135', 'reporting_month' => '2026-09',
        ])->assertSessionHasNoErrors()->assertRedirect();

        $response = $this->get(route('barangay.rbi-updates.history', ['household_page' => 999]))
            ->assertOk()->assertDontSee('id="saved-inhabitant-records"', false)
            ->assertSee('Consolidation history')->assertSee('FamilyFirst Example')->assertSee('FamilySecond Example')
            ->assertSee(route('registry.new-inhabitant-monthly-reports.pdf', '2026-09'))
            ->assertSee(route('registry.new-inhabitant-monthly-reports.submit', '2026-09'));
        $this->assertSame(0, $response->viewData('savedHouseholdPage')->total());
        $this->assertSame(1, $response->viewData('savedHouseholdPage')->currentPage());
        $this->assertSame(1, $response->viewData('consolidatedHouseholdPage')->total());
        $this->assertNotNull($first->fresh()->active_inhabitant_id);
        $this->assertNotNull($second->fresh()->active_inhabitant_id);
        $this->assertDatabaseCount('new_inhabitants', 2);
        $this->assertDatabaseCount('inhabitants', 2);
    }

    public function test_history_pagination_is_independent_and_scoped_to_the_staff_barangay(): void
    {
        foreach (range(1, 3) as $household) {
            $member = $this->member('Archived'.$household, (string) $household, null);
            $this->post(route('registry.new-inhabitants.add-to-active', $member))->assertSessionHasNoErrors();
        }
        $pending = $this->member('StillPending', '4', null);
        $foreignArea = Barangay::where('id', '!=', $this->staff->barangay_id)->firstOrFail();
        $foreignStaff = User::factory()->create(['role' => User::ROLE_BARANGAY, 'barangay_id' => $foreignArea->id]);
        $foreign = NewInhabitant::create([
            'barangay_id' => $foreignArea->id, 'household_number' => '1',
            'first_name' => 'ForeignHistory', 'last_name' => 'Example', 'sex' => 'Female',
        ]);
        $this->actingAs($foreignStaff)->post(route('registry.new-inhabitants.add-to-active', $foreign))->assertSessionHasNoErrors();
        $response = $this->actingAs($this->staff)->get(route('barangay.rbi-updates.history', [
            'consolidated_page' => 2, 'household_page' => 999,
        ]))->assertOk()->assertSee('Archived2 Example')->assertSee('StillPending Example')
            ->assertDontSee('ForeignHistory');
        $historyHtml = view('rbi-updates._consolidated-inhabitants', $response->original->getData())->render();
        $this->assertStringContainsString('Archived2 Example', $historyHtml);
        $this->assertStringNotContainsString('Archived1 Example', $historyHtml);
        $this->assertStringNotContainsString('Archived3 Example', $historyHtml);
        $page = $response->viewData('consolidatedHouseholdPage');
        $this->assertSame(3, $page->total());
        $this->assertSame(2, $page->currentPage());
        $this->assertStringContainsString('household_page=999', $page->nextPageUrl());
        $this->assertStringContainsString('#consolidated-inhabitant-history', $page->nextPageUrl());
        $this->assertSame($pending->id, $response->viewData('savedHouseholdPage')->items()[0]['members']->first()->id);
        $this->get(route('barangay.rbi-updates.history', ['consolidated_page' => 999]))->assertOk()
            ->assertSee('Archived3 Example')->assertViewHas('consolidatedHouseholdPage', fn ($page) => $page->currentPage() === 3);
    }

    public function test_unsuccessful_add_keeps_member_pending(): void
    {
        $member = $this->member('IncompleteMember');
        $member->update(['sex' => null]);
        $this->post(route('registry.new-inhabitants.add-to-active', $member))->assertSessionHasErrors('member');
        $this->get(route('barangay.rbi-updates.history'))->assertOk()->assertSee('IncompleteMember Example')
            ->assertViewHas('consolidatedHouseholdPage', fn ($page) => $page->total() === 0)
            ->assertViewHas('savedHouseholdPage', fn ($page) => $page->total() === 1);
        $this->assertDatabaseCount('inhabitants', 0);
        $this->assertNull($member->fresh()->active_inhabitant_id);
    }
}
