<?php

namespace Tests\Feature;

use App\Models\Barangay;
use App\Models\Household;
use App\Models\Inhabitant;
use App\Models\RegistryReportSubmission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistryReportSubmissionTest extends TestCase
{
    use RefreshDatabase;

    private function resident(Barangay $area, string $name, string $residence, string $status = Inhabitant::STATUS_ACTIVE): Inhabitant
    {
        $household = Household::create(['barangay_id' => $area->id, 'household_number' => $name, 'purok' => 'P-1']);

        return Inhabitant::create([
            'barangay_id' => $area->id, 'household_id' => $household->id,
            'first_name' => $name, 'last_name' => 'Snapshot', 'sex' => 'Female',
            'status' => $status, 'residence_status' => $residence,
        ]);
    }

    public function test_barangay_can_submit_immutable_consolidated_snapshot_and_municipal_can_review_and_download_it(): void
    {
        $area = Barangay::where('name', 'Looc')->firstOrFail();
        $secretary = User::factory()->create(['role' => User::ROLE_BARANGAY, 'barangay_id' => $area->id]);
        $here = $this->resident($area, 'Here', Inhabitant::RESIDENCE_HERE);
        $elsewhere = $this->resident($area, 'Elsewhere', Inhabitant::RESIDENCE_ELSEWHERE);
        $unknown = $this->resident($area, 'Unknown', Inhabitant::RESIDENCE_UNCONFIRMED);
        $this->resident($area, 'Inactive', Inhabitant::RESIDENCE_HERE, Inhabitant::STATUS_MIGRATED_OUT);
        $otherArea = Barangay::where('name', 'Hugpa')->firstOrFail();
        $this->resident($otherArea, 'Other Barangay', Inhabitant::RESIDENCE_HERE);

        $response = $this->actingAs($secretary)->post(route('barangay.registry-submissions.store'), ['scope' => 'registered'])
            ->assertRedirect()->assertSessionHasNoErrors()
            ->assertSessionHas('status');

        $submission = RegistryReportSubmission::firstOrFail();
        $this->assertSame(3, $submission->record_count);
        $this->assertSame($area->id, $submission->barangay_id);
        $this->assertSame(['Here', 'Elsewhere', 'Unknown'], array_column($submission->snapshot, 'first_name'));
        $this->assertSame(hash('sha256', json_encode($submission->snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)), $submission->snapshot_hash);

        $here->update(['first_name' => 'Changed Live Record']);
        $this->assertSame('Here', $submission->fresh()->snapshot[0]['first_name']);

        $admin = User::factory()->create(['role' => User::ROLE_MUNICIPAL_LGU, 'barangay_id' => null]);
        $this->actingAs($admin)->get(route('municipal.registry-submissions.index'))->assertOk()->assertSee($submission->reference());
        $this->get(route('municipal.registry-submissions.show', $submission))->assertOk()
            ->assertSee('Elsewhere')->assertSee('Residence needs confirmation');
        $csv = $this->get(route('municipal.registry-submissions.download', $submission))->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8')->streamedContent();
        $this->assertStringContainsString('Here', $csv);
        $this->assertStringContainsString('Elsewhere', $csv);
        $this->assertStringNotContainsString('Changed Live Record', $csv);
        $this->assertStringNotContainsString('Other Barangay', $csv);
    }

    public function test_scoped_submission_only_captures_selected_residence_status(): void
    {
        $area = Barangay::where('name', 'Looc')->firstOrFail();
        $secretary = User::factory()->create(['role' => User::ROLE_BARANGAY, 'barangay_id' => $area->id]);
        $this->resident($area, 'LivingHere', Inhabitant::RESIDENCE_HERE);
        $this->resident($area, 'LivingElsewhere', Inhabitant::RESIDENCE_ELSEWHERE);

        $this->actingAs($secretary)->post(route('barangay.registry-submissions.store'), ['scope' => Inhabitant::RESIDENCE_HERE])
            ->assertRedirect()->assertSessionHasNoErrors();

        $submission = RegistryReportSubmission::firstOrFail();
        $this->assertSame(Inhabitant::RESIDENCE_HERE, $submission->scope);
        $this->assertSame(['LivingHere'], array_column($submission->snapshot, 'first_name'));
    }

    public function test_residents_cannot_submit_and_barangay_cannot_read_municipal_snapshots(): void
    {
        $area = Barangay::where('name', 'Looc')->firstOrFail();
        $submission = RegistryReportSubmission::create([
            'barangay_id' => $area->id, 'submitted_by' => User::factory()->create(['role' => User::ROLE_BARANGAY, 'barangay_id' => $area->id])->id,
            'scope' => 'registered', 'barangay_name' => $area->name, 'as_of' => now(), 'record_count' => 0,
            'snapshot' => [], 'snapshot_hash' => hash('sha256', '[]'), 'submitted_at' => now(),
        ]);
        $resident = User::factory()->create(['role' => User::ROLE_RESIDENT]);
        $secretary = User::factory()->create(['role' => User::ROLE_BARANGAY, 'barangay_id' => $area->id]);

        $this->actingAs($resident)->post(route('barangay.registry-submissions.store'), ['scope' => 'registered'])->assertForbidden();
        $this->actingAs($secretary)->get(route('municipal.registry-submissions.index'))->assertForbidden();
        $this->get(route('municipal.registry-submissions.show', $submission))->assertForbidden();
        $this->get(route('municipal.registry-submissions.download', $submission))->assertForbidden();
    }

    public function test_empty_scope_is_not_submitted(): void
    {
        $area = Barangay::where('name', 'Looc')->firstOrFail();
        $secretary = User::factory()->create(['role' => User::ROLE_BARANGAY, 'barangay_id' => $area->id]);

        $this->actingAs($secretary)->from(route('barangay.registry.active'))
            ->post(route('barangay.registry-submissions.store'), ['scope' => 'registered'])
            ->assertRedirect(route('barangay.registry.active'))->assertSessionHasErrors('scope');
        $this->assertDatabaseCount('registry_report_submissions', 0);
    }
}

