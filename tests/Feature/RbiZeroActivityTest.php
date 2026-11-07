<?php

namespace Tests\Feature;

use App\Models\Barangay;
use App\Models\BarangayRbiUpdate;
use App\Models\User;
use App\Services\RbiArrivalReview;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RbiZeroActivityTest extends TestCase
{
    use RefreshDatabase;

    public function test_zero_activity_draft_requires_explicit_confirmation_and_never_replaces_an_existing_form(): void
    {
        $staff = User::factory()->create(['role' => 'barangay', 'barangay_id' => Barangay::firstOrFail()->id]);
        $this->actingAs($staff)->post(route('barangay.rbi-updates.zero-activity'), ['reporting_month' => '2026-02'])->assertSessionHasErrors('confirmed');
        $this->post(route('barangay.rbi-updates.zero-activity'), ['reporting_month' => '2026-02', 'confirmed' => 1])->assertSessionHasNoErrors()->assertRedirect();
        $report = BarangayRbiUpdate::firstOrFail();
        $this->assertSame('draft', $report->status);
        $this->assertSame([], app(RbiArrivalReview::class)->periods($staff->barangay));
        $this->post(route('barangay.rbi-updates.zero-activity'), ['reporting_month' => '2026-02', 'confirmed' => 1])->assertSessionHasErrors('reporting_month');
        $this->assertDatabaseCount('barangay_rbi_updates', 1);
    }

    public function test_empty_report_can_be_signed_as_zero_activity_but_is_not_automatically_a_reviewed_arrival_period(): void
    {
        Storage::fake('local');
        $staff = User::factory()->create(['role' => 'barangay', 'barangay_id' => Barangay::firstOrFail()->id]);
        $report = BarangayRbiUpdate::create(['barangay_user_id' => $staff->id, 'barangay_name' => $staff->barangay->name, 'reporting_month' => '2026-02-01', 'rows' => [], 'deceased_rows' => [], 'status' => 'draft',
            'prepared_by' => 'Encoder', 'prepared_signature_path' => 'signed/encoder.png', 'attested_by' => 'Captain', 'attested_signature_path' => 'signed/captain.png']);
        Storage::put('signed/encoder.png', 'fixture');
        Storage::put('signed/captain.png', 'fixture');
        $this->actingAs($staff)->get(route('barangay.rbi-updates.certification', [$report, 'action' => 'submit']))->assertOk()->assertSee('confirm_no_activity');
        $this->post(route('barangay.rbi-updates.submit', $report), [])->assertSessionHasErrors('rows');
        $this->post(route('barangay.rbi-updates.submit', $report), ['confirm_no_activity' => 1])->assertSessionHasNoErrors();
        $this->assertSame('submitted', $report->fresh()->status);
        $period = app(RbiArrivalReview::class)->periods($staff->barangay)[0];
        $this->assertFalse($period['verified']);
        $this->assertFalse($period['complete']);
        $this->assertSame(0, $period['months'][0]['eligible']);
    }
}
