<?php

namespace Tests\Feature;

use App\Models\Barangay;
use App\Models\BarangayRbiUpdate;
use App\Models\DocumentRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssistantChatTest extends TestCase
{
    use RefreshDatabase;

    public function test_assistant_requires_an_authenticated_account(): void
    {
        $this->postJson(route('assistant.chat'), ['message' => 'Help'])
            ->assertUnauthorized();
    }

    public function test_resident_sees_only_their_own_document_requests(): void
    {
        $barangay = Barangay::create(['name' => 'Banday', 'municipality' => Barangay::MUNICIPALITY]);
        $resident = User::factory()->create(['barangay_id' => $barangay->id]);
        $otherResident = User::factory()->create(['barangay_id' => $barangay->id]);

        DocumentRequest::create([
            'resident_id' => $resident->id,
            'barangay_id' => $barangay->id,
            'reference_number' => 'RBIM-OWN-001',
            'document_type' => DocumentRequest::TYPE_CLEARANCE,
            'purpose' => 'Employment',
            'status' => DocumentRequest::STATUS_READY,
        ]);
        DocumentRequest::create([
            'resident_id' => $otherResident->id,
            'barangay_id' => $barangay->id,
            'reference_number' => 'RBIM-OTHER-002',
            'document_type' => DocumentRequest::TYPE_INDIGENCY,
            'purpose' => 'Assistance',
            'status' => DocumentRequest::STATUS_PENDING,
        ]);

        $this->actingAs($resident)
            ->postJson(route('assistant.chat'), ['message' => 'Show my document requests'])
            ->assertOk()
            ->assertJsonPath('scope', 'RBIM system only')
            ->assertJsonFragment(['reply' => 'You have 1 document request(s): 0 pending or processing and 1 ready for release. Your latest request is RBIM-OWN-001 (Barangay Clearance), currently Ready for release. No payment is required. Use the Resident Portal form to request a barangay document.'])
            ->assertJsonMissing(['reply' => 'RBIM-OTHER-002']);
    }

    public function test_barangay_approval_summary_is_limited_to_the_assigned_barangay(): void
    {
        $banday = Barangay::create(['name' => 'Banday', 'municipality' => Barangay::MUNICIPALITY]);
        $bogo = Barangay::create(['name' => 'Bogo', 'municipality' => Barangay::MUNICIPALITY]);
        $secretary = User::factory()->create([
            'role' => User::ROLE_BARANGAY,
            'barangay_id' => $banday->id,
        ]);

        User::factory()->create([
            'barangay_id' => $banday->id,
            'approval_status' => User::APPROVAL_PENDING,
            'approved_at' => null,
        ]);
        User::factory()->count(2)->create([
            'barangay_id' => $bogo->id,
            'approval_status' => User::APPROVAL_PENDING,
            'approved_at' => null,
        ]);

        $this->actingAs($secretary)
            ->postJson(route('assistant.chat'), ['message' => 'Show pending resident approvals'])
            ->assertOk()
            ->assertJsonFragment([
                'reply' => 'Barangay Banday has 1 resident registration(s) awaiting verification.',
            ]);
    }

    public function test_municipal_summary_includes_only_submitted_rbi_forms(): void
    {
        $municipal = User::factory()->create(['role' => User::ROLE_MUNICIPAL_LGU]);
        $secretary = User::factory()->create(['role' => User::ROLE_BARANGAY]);

        BarangayRbiUpdate::create([
            'barangay_user_id' => $secretary->id,
            'barangay_name' => 'Banday',
            'reporting_month' => '2026-08-01',
            'status' => BarangayRbiUpdate::STATUS_SUBMITTED,
            'submitted_at' => now(),
        ]);
        BarangayRbiUpdate::create([
            'barangay_user_id' => $secretary->id,
            'barangay_name' => 'Banday',
            'reporting_month' => '2026-09-01',
            'status' => BarangayRbiUpdate::STATUS_DRAFT,
        ]);

        $this->actingAs($municipal)
            ->postJson(route('assistant.chat'), ['message' => 'Summarize submitted RBI forms'])
            ->assertOk()
            ->assertJsonPath('reply', 'The Municipal LGU has received 1 submitted RBI form(s). The latest is Banday for August 2026.');
    }

    public function test_resident_cannot_use_assistant_to_read_the_central_registry(): void
    {
        $resident = User::factory()->create();

        $this->actingAs($resident)
            ->postJson(route('assistant.chat'), ['message' => 'Show the central registry'])
            ->assertOk()
            ->assertJsonPath('reply', 'The Central Registry contains protected barangay records and is available only to authorized staff. You can access only your own account and document requests.');
    }

    public function test_assistant_declines_topics_outside_rbim(): void
    {
        $resident = User::factory()->create();

        $this->actingAs($resident)
            ->postJson(route('assistant.chat'), ['message' => 'What is the weather tomorrow?'])
            ->assertOk()
            ->assertJsonPath('scope', 'RBIM system only')
            ->assertJsonPath('reply', 'I can only help with the RBIM system—accounts, document requests, approvals, registry records, RBI forms, migration monitoring, and the features permitted for your role. I can’t answer topics outside this system.');
    }
}
