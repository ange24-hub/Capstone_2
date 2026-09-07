<?php
namespace Tests\Feature;

use App\Models\{Barangay, BarangayRbiUpdate, NewInhabitant, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MunicipalReportReceiptTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_dashboard_directory_and_approvals_work_without_a_barangay_assignment(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_MUNICIPAL_LGU, 'barangay_id' => null]);
        foreach (['dashboard.municipal', 'municipal.barangays.index', 'municipal.approvals.index'] as $route) {
            $this->actingAs($admin)->get(route($route))->assertOk();
        }
    }

    public function test_barangay_submission_reaches_admin_but_drafts_remain_private(): void
    {
        $barangay = Barangay::where('name', 'Canlupao')->firstOrFail();
        $staff = User::factory()->create(['role' => 'barangay', 'barangay_id' => $barangay->id]);
        $admin = User::factory()->create(['role' => 'municipal_lgu', 'barangay_id' => null]);
        $report = BarangayRbiUpdate::create(['barangay_user_id' => $staff->id, 'barangay_name' => $barangay->name,
            'reporting_month' => '2026-09-01', 'status' => 'draft', 'prepared_by' => 'Secretary', 'attested_by' => 'Captain',
            'prepared_signature_path' => 'signatures/secretary.png', 'attested_signature_path' => 'signatures/captain.png',
            'rows' => [['household_head' => 'Example Parent', 'inhabitant_name' => 'Example, Child', 'sex' => 'Female', 'relationship' => 'Daughter']]]);
        $this->actingAs($admin)->get(route('dashboard.municipal'))->assertOk()->assertViewHas('rbiUpdates', fn ($reports) => $reports->isEmpty());
        $this->get(route('rbi-updates.show', $report))->assertForbidden();
        $this->actingAs($staff)->post(route('barangay.rbi-updates.submit', $report))->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame('submitted', $report->fresh()->status);
        $this->assertNotNull($report->fresh()->submitted_at);
        $this->actingAs($admin)->get(route('dashboard.municipal'))->assertOk()->assertViewHas('rbiUpdates', fn ($reports) => $reports->contains('id', $report->id));
        $this->get(route('municipal.barangays.index'))->assertOk()->assertSee(route('rbi-updates.show', $report));
        $this->get(route('rbi-updates.show', $report))->assertOk()->assertSee('Example, Child');
        $other = User::factory()->create(['role' => 'barangay', 'barangay_id' => Barangay::where('name', 'San Roque')->firstOrFail()->id]);
        $this->actingAs($other)->get(route('rbi-updates.show', $report))->assertForbidden();
    }

    public function test_saved_new_inhabitant_submission_also_reaches_the_admin(): void
    {
        $b = Barangay::where('name', 'San Roque')->firstOrFail();
        $staff = User::factory()->create(['role' => 'barangay', 'barangay_id' => $b->id]);
        $member = NewInhabitant::create(['barangay_id' => $b->id, 'household_number' => '1', 'first_name' => 'Child', 'last_name' => 'Example', 'sex' => 'Male', 'reporting_month' => '2021-05-01']);
        $this->actingAs($staff)->post(route('registry.new-inhabitant-monthly-reports.submit', '2021-05'))->assertSessionHasNoErrors();
        $report = BarangayRbiUpdate::findOrFail($member->fresh()->submitted_rbi_update_id);
        $admin = User::factory()->create(['role' => 'municipal_lgu', 'barangay_id' => null]);
        $this->actingAs($admin)->get(route('dashboard.municipal'))->assertOk()->assertViewHas('rbiUpdates', fn ($reports) => $reports->contains('id', $report->id));
        $this->get(route('rbi-updates.show', $report))->assertOk()->assertSee('Example, Child');
    }
}
