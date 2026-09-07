<?php

namespace Tests\Feature;

use App\Models\Barangay;
use App\Models\BarangayRbiMember;
use App\Models\BarangayRbiUpdate;
use App\Models\DeceasedInhabitant;
use App\Models\Household;
use App\Models\Inhabitant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RbiReportRegistryTest extends TestCase
{
    use RefreshDatabase;

    private function setupReport(array $rows, string $month = '2026-09-01'): array
    {
        $barangay = Barangay::where('name', 'San Isidro')->firstOrFail();
        $staff = User::factory()->create(['role' => User::ROLE_BARANGAY, 'barangay_id' => $barangay->id]);
        $report = BarangayRbiUpdate::create(['barangay_user_id' => $staff->id, 'barangay_name' => $barangay->name,
            'reporting_month' => $month, 'status' => 'draft', 'rows' => $rows]);
        $this->actingAs($staff);
        return [$barangay, $staff, $report];
    }

    private function member(string $name = 'Example, Child, Middle', array $extra = []): array
    {
        return $extra + ['household_head' => 'Parent Example', 'inhabitant_name' => $name,
            'sex' => 'Female', 'birth_date' => '2020-01-01', 'relationship' => 'Daughter'];
    }

    private function add(BarangayRbiUpdate $report)
    {
        return $this->post(route('barangay.rbi-updates.add-to-registry', $report));
    }

    public function test_existing_household_repeated_clicks_and_relational_links(): void
    {
        [$barangay, $staff, $report] = $this->setupReport([$this->member()]);
        $household = Household::create(['barangay_id' => $barangay->id, 'household_number' => '15']);
        Inhabitant::create(['barangay_id' => $barangay->id, 'household_id' => $household->id,
            'first_name' => 'Parent', 'last_name' => 'Example', 'sex' => 'Male', 'status' => 'active', 'relationship_to_head' => 'Head']);
        $this->get(route('barangay.rbi-updates.index'))->assertOk()->assertSee('Add to Consolidated RBI');
        $this->get(route('dashboard.barangay'))->assertOk()->assertSee('Add to Consolidated RBI');
        $this->add($report)->assertSessionHasNoErrors();
        $this->add($report)->assertSessionHasNoErrors();
        $this->assertSame(1, Household::where('barangay_id', $barangay->id)->count());
        $child = Inhabitant::where('first_name', 'Child')->sole();
        $this->assertSame($household->id, $child->household_id);
        $this->assertSame('Middle', $child->middle_name);
        $this->assertSame('unconfirmed', $child->residence_status);
        $this->assertSame((string) $child->id, $report->fresh()->rows[0]['inhabitant_id']);
        $this->assertSame($child->id, BarangayRbiMember::sole()->inhabitant_id);
        $this->assertSame('draft', $report->fresh()->status);
        $this->assertNull($report->fresh()->submitted_at);
        $this->get(route('barangay.registry.active'))->assertOk()->assertSee('Child');
    }

    public function test_new_household_is_created_once_and_reused_in_later_reports(): void
    {
        [$barangay, $staff, $report] = $this->setupReport([$this->member(), $this->member('Example, Second')]);
        Household::create(['barangay_id' => $barangay->id, 'household_number' => '100']);
        $this->add($report)->assertSessionHasNoErrors();
        $created = Household::where('barangay_id', $barangay->id)->where('household_number', '101')->sole();
        $this->assertSame(2, $created->inhabitants()->count());
        $later = BarangayRbiUpdate::create(['barangay_user_id' => $staff->id, 'barangay_name' => $barangay->name,
            'reporting_month' => '2026-10-01', 'status' => 'submitted', 'submitted_at' => now(),
            'rows' => [$this->member('Example, Third')]]);
        $this->add($later)->assertSessionHasNoErrors();
        $this->assertSame(3, $created->inhabitants()->count());
        $this->assertSame(2, Household::where('barangay_id', $barangay->id)->count());
        $this->assertSame('submitted', $later->fresh()->status);
    }

    public function test_existing_resident_is_linked_without_overwriting_data(): void
    {
        [$barangay, , $report] = $this->setupReport([$this->member()]);
        $hh = Household::create(['barangay_id' => $barangay->id, 'household_number' => '7']);
        $person = Inhabitant::create(['barangay_id' => $barangay->id, 'household_id' => $hh->id,
            'last_name' => 'Example', 'first_name' => 'Child', 'middle_name' => 'Middle', 'sex' => 'Female',
            'birth_date' => '2020-01-01', 'occupation' => 'Preserve this', 'status' => 'active', 'residence_status' => 'living_elsewhere']);
        $report->update(['rows' => [$this->member(extra: ['household_id' => $hh->id])]]);
        $this->add($report)->assertSessionHasNoErrors();
        $this->assertSame(1, Inhabitant::where('barangay_id', $barangay->id)->count());
        $this->assertSame('Preserve this', $person->fresh()->occupation);
        $this->assertSame('living_elsewhere', $person->fresh()->residence_status);
    }

    public function test_failure_rolls_back_all_members_and_new_households(): void
    {
        [$barangay, , $report] = $this->setupReport([$this->member(), $this->member('MissingComma')]);
        $this->add($report)->assertSessionHasErrors('registry');
        $this->assertSame(0, Household::where('barangay_id', $barangay->id)->count());
        $this->assertSame(0, Inhabitant::where('barangay_id', $barangay->id)->count());
        $this->assertArrayNotHasKey('inhabitant_id', $report->fresh()->rows[0]);
    }

    public function test_foreign_report_and_foreign_household_are_rejected(): void
    {
        [$barangay, $staff, $report] = $this->setupReport([$this->member()]);
        $other = Barangay::where('name', 'San Antonio')->firstOrFail();
        $foreign = Household::create(['barangay_id' => $other->id, 'household_number' => '1']);
        $report->update(['rows' => [$this->member(extra: ['household_id' => $foreign->id])]]);
        $this->add($report)->assertSessionHasErrors('registry');
        $this->assertSame(0, Inhabitant::where('barangay_id', $barangay->id)->count());
        $this->actingAs(User::factory()->create(['role' => User::ROLE_BARANGAY, 'barangay_id' => $other->id]));
        $this->add($report)->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => User::ROLE_RESIDENT, 'barangay_id' => $barangay->id]));
        $this->add($report)->assertForbidden();
    }

    public function test_ambiguous_household_requires_explicit_selection(): void
    {
        [$barangay, , $report] = $this->setupReport([$this->member()]);
        foreach (['1', '2'] as $number) {
            $hh = Household::create(['barangay_id' => $barangay->id, 'household_number' => $number]);
            Inhabitant::create(['barangay_id' => $barangay->id, 'household_id' => $hh->id,
                'first_name' => 'Parent', 'last_name' => 'Example', 'sex' => 'Male', 'status' => 'active']);
        }
        $this->add($report)->assertSessionHasErrors('registry');
        $report->update(['rows' => [$this->member(extra: ['household_id' => $hh->id])]]);
        $this->add($report)->assertSessionHasNoErrors();
        $this->assertSame($hh->id, Inhabitant::where('first_name', 'Child')->sole()->household_id);
    }

    public function test_deceased_and_inactive_residents_are_not_reactivated(): void
    {
        [$barangay, , $report] = $this->setupReport([$this->member()]);
        DeceasedInhabitant::create(['barangay_id' => $barangay->id, 'household_number' => '1',
            'last_name' => 'Example', 'first_name' => 'Child', 'middle_name' => 'Middle', 'birth_date' => '2020-01-01']);
        $this->add($report)->assertSessionHasErrors('registry');
        $hh = Household::create(['barangay_id' => $barangay->id, 'household_number' => '1']);
        Inhabitant::create(['barangay_id' => $barangay->id, 'household_id' => $hh->id,
            'last_name' => 'Example', 'first_name' => 'Away', 'sex' => 'Female', 'status' => 'migrated_out']);
        $report->update(['rows' => [$this->member('Example, Away', ['household_id' => $hh->id])]]);
        $this->add($report)->assertSessionHasErrors('registry');
        $this->assertSame('migrated_out', Inhabitant::where('first_name', 'Away')->sole()->status);
    }

    public function test_appended_members_are_added_without_duplicating_previous_rows(): void
    {
        [$barangay, , $report] = $this->setupReport([$this->member()]);
        $this->add($report)->assertSessionHasNoErrors();
        $rows = $report->fresh()->rows;
        $rows[] = $this->member('Example, Another', ['household_id' => $rows[0]['household_id']]);
        $report->update(['rows' => $rows]);
        $this->add($report)->assertSessionHasNoErrors();
        $this->assertSame(2, Inhabitant::where('barangay_id', $barangay->id)->count());
        $this->assertSame(1, Household::where('barangay_id', $barangay->id)->count());
        $this->assertSame(2, BarangayRbiMember::whereNotNull('inhabitant_id')->count());
    }

    public function test_incomplete_draft_is_rejected_without_creating_a_household(): void
    {
        [$barangay, , $report] = $this->setupReport([$this->member(extra: ['sex' => ''])]);
        $this->add($report)->assertSessionHasErrors('rows.0.sex');
        $this->assertSame(0, Household::where('barangay_id', $barangay->id)->count());
    }
}
