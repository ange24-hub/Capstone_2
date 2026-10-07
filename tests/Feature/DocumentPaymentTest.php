<?php

namespace Tests\Feature;

use App\Models\Barangay;
use App\Models\DocumentRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentPaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_request_waits_for_secretary_fee_assessment(): void
    {
        config(['rbim.document_fees.'.DocumentRequest::TYPE_CLEARANCE => 75]);
        $barangay = $this->barangayFixture([
            'name' => 'Banday',
            'municipality' => Barangay::MUNICIPALITY,
            'gcash_enabled' => true,
            'gcash_merchant_name' => 'Barangay Banday',
            'gcash_account_identifier' => 'BANDAY-BRANCH',
            'gcash_qr_path' => 'barangay-gcash-qr/1/qr.png',
        ]);
        $resident = User::factory()->create(['barangay_id' => $barangay->id]);

        $this->actingAs($resident)->post(route('resident.document-requests.store'), [
            'document_type' => DocumentRequest::TYPE_CLEARANCE,
            'purpose' => 'Employment',
        ])->assertRedirect();

        $this->assertDatabaseHas('document_requests', [
            'resident_id' => $resident->id,
            'amount_due' => 0,
            'payment_method' => null,
            'payment_status' => DocumentRequest::PAYMENT_AWAITING_ASSESSMENT,
        ]);
    }

    public function test_resident_can_submit_a_gcash_transaction_for_their_request(): void
    {
        Storage::fake('local');
        [$request, $resident] = $this->paidRequestFixture();

        $this->actingAs($resident)->post(route('resident.document-payments.submit', $request), [
            'payer_name' => 'Juan Resident',
            'payer_mobile' => '09171234567',
            'payment_reference' => '1234567890123',
            'payment_transaction_at' => now()->subMinute()->format('Y-m-d\TH:i'),
            'payment_proof' => UploadedFile::fake()->create('receipt.png', 100, 'image/png'),
        ])->assertRedirect();

        $request->refresh();
        $this->assertSame(DocumentRequest::PAYMENT_PENDING, $request->payment_status);
        $this->assertSame('1234567890123', $request->payment_reference);
        Storage::disk('local')->assertExists($request->payment_proof_path);
    }

    public function test_barangay_cannot_release_a_document_before_payment_verification(): void
    {
        [$request, , $secretary] = $this->paidRequestFixture();

        $this->actingAs($secretary)->put(route('barangay.document-requests.update', $request), [
            'status' => DocumentRequest::STATUS_READY,
        ])->assertSessionHasErrors('status');

        $this->assertSame(DocumentRequest::STATUS_PENDING, $request->fresh()->status);
    }

    public function test_assigned_barangay_can_verify_then_process_the_paid_document(): void
    {
        [$request, , $secretary] = $this->paidRequestFixture([
            'payment_status' => DocumentRequest::PAYMENT_PENDING,
            'payment_reference' => '1234567890123',
        ]);

        $this->actingAs($secretary)->post(route('barangay.document-payments.verify', $request), [
            'decision' => 'verify',
        ])->assertRedirect();

        $this->assertSame(DocumentRequest::PAYMENT_PAID, $request->fresh()->payment_status);

        $this->actingAs($secretary)->put(route('barangay.document-requests.update', $request), [
            'status' => DocumentRequest::STATUS_PROCESSING,
        ])->assertRedirect();

        $this->assertSame(DocumentRequest::STATUS_PROCESSING, $request->fresh()->status);
    }

    public function test_resident_cannot_view_another_barangays_gcash_qr(): void
    {
        $residentBarangay = $this->barangayFixture(['name' => 'Banday', 'municipality' => Barangay::MUNICIPALITY]);
        $otherBarangay = $this->barangayFixture([
            'name' => 'Bogo',
            'municipality' => Barangay::MUNICIPALITY,
            'gcash_qr_path' => 'barangay-gcash-qr/2/qr.png',
        ]);
        $resident = User::factory()->create(['barangay_id' => $residentBarangay->id]);

        $this->actingAs($resident)
            ->get(route('barangays.gcash.qr', $otherBarangay))
            ->assertForbidden();
    }

    public function test_municipal_user_can_configure_only_the_selected_barangays_gcash_profile(): void
    {
        Storage::fake('local');
        $municipal = User::factory()->create(['role' => User::ROLE_MUNICIPAL_LGU]);
        $banday = $this->barangayFixture(['name' => 'Banday', 'municipality' => Barangay::MUNICIPALITY]);
        $bogo = $this->barangayFixture(['name' => 'Bogo', 'municipality' => Barangay::MUNICIPALITY]);

        $this->actingAs($municipal)->put(route('municipal.barangays.gcash.update', $banday), [
            'gcash_enabled' => '1',
            'gcash_merchant_name' => 'Barangay Banday Official',
            'gcash_account_identifier' => 'BANDAY-BRANCH',
            'gcash_qr' => UploadedFile::fake()->create('banday-qr.png', 100, 'image/png'),
        ])->assertRedirect();

        $banday->refresh();
        $this->assertTrue($banday->gcashIsReady());
        $this->assertSame($municipal->id, $banday->gcash_approved_by);
        $this->assertFalse($bogo->fresh()->gcash_enabled);
        Storage::disk('local')->assertExists($banday->gcash_qr_path);
    }

    public function test_assessment_updates_resident_and_in_person_payment_must_be_confirmed_before_release(): void
    {
        [$document, $resident, $secretary] = $this->paidRequestFixture([
            'amount_due' => 0, 'payment_status' => DocumentRequest::PAYMENT_AWAITING_ASSESSMENT, 'payment_method' => null,
        ]);
        $this->actingAs($resident)->get(route('resident.document-requests.index'))->assertOk()
            ->assertSee('To be set by secretary')->assertDontSee('I will pay in person');
        $this->actingAs($secretary)->post(route('barangay.document-payments.assess', $document), [
            'amount_due' => '80.50', 'payment_remarks' => 'Pay before collection.',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('80.50', $document->fresh()->amount_due);
        $this->assertSame(DocumentRequest::STATUS_PROCESSING, $document->fresh()->status);
        $this->actingAs($resident)->get(route('resident.document-requests.index'))->assertOk()
            ->assertSee('Payment required before release')->assertSee('80.50')->assertSee('I will pay in person');
        $this->post(route('resident.document-payments.in-person', $document))->assertRedirect();
        $this->assertFalse($document->fresh()->isPaid());
        $this->actingAs($secretary)->put(route('barangay.document-requests.update', $document), ['status' => DocumentRequest::STATUS_COMPLETED])
            ->assertSessionHasErrors('status');
        $this->post(route('barangay.document-payments.receive-in-person', $document), ['confirm_received' => 1])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertTrue($document->fresh()->isPaid());
        $this->assertSame($secretary->id, $document->fresh()->payment_reviewed_by);
        $this->put(route('barangay.document-requests.update', $document), ['status' => DocumentRequest::STATUS_READY])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(DocumentRequest::STATUS_READY, $document->fresh()->status);
        $this->actingAs($resident)->get(route('resident.document-requests.index'))->assertOk()->assertSee('Paid in person at the barangay office');
    }

    public function test_only_indigency_is_free_and_requests_do_not_require_gcash_setup(): void
    {
        $area = Barangay::where('name', 'Banday')->firstOrFail();
        $area->update(['gcash_enabled' => false]);
        $resident = User::factory()->create(['barangay_id' => $area->id]);
        $this->actingAs($resident)->get(route('resident.document-requests.create'))->assertOk()
            ->assertSee('Barangay Indigency — No fee')->assertSee('Barangay Clearance — Fee to be set by secretary')
            ->assertSee('Certificate of Residency — Fee to be set by secretary')->assertSee('Business Clearance — Fee to be set by secretary');
        foreach (array_keys(DocumentRequest::typeLabels()) as $type) {
            $this->post(route('resident.document-requests.store'), ['document_type' => $type, 'purpose' => 'Test'])
                ->assertRedirect()->assertSessionHasNoErrors();
            $document = DocumentRequest::where('resident_id', $resident->id)->where('document_type', $type)->firstOrFail();
            $this->assertSame($type !== DocumentRequest::TYPE_INDIGENCY, $document->requiresPayment());
            $this->assertSame($type === DocumentRequest::TYPE_INDIGENCY, $document->isPaid());
        }
    }

    public function test_payment_actions_enforce_scope_and_reject_unassessed_and_closed_requests(): void
    {
        [$document, $resident, $secretary] = $this->paidRequestFixture(['amount_due' => 0, 'payment_status' => DocumentRequest::PAYMENT_AWAITING_ASSESSMENT]);
        $other = User::factory()->create(['role' => User::ROLE_BARANGAY, 'barangay_id' => Barangay::where('name', 'Bogo')->value('id')]);
        $this->actingAs($other)->post(route('barangay.document-payments.assess', $document), ['amount_due' => 20])->assertForbidden();
        $this->post(route('barangay.document-payments.receive-in-person', $document), ['confirm_received' => 1])->assertForbidden();
        $otherResident = User::factory()->create(['barangay_id' => $resident->barangay_id]);
        $this->actingAs($otherResident)->post(route('resident.document-payments.in-person', $document))->assertForbidden();
        $this->actingAs($resident)->post(route('barangay.document-payments.assess', $document), ['amount_due' => 20])->assertForbidden();
        $this->post(route('resident.document-payments.in-person', $document))->assertUnprocessable();
        $this->post(route('resident.document-payments.submit', $document))->assertUnprocessable();
        $this->actingAs($secretary)->post(route('barangay.document-payments.assess', $document), ['amount_due' => 0])->assertSessionHasErrors('amount_due');
        $document->update(['document_type' => DocumentRequest::TYPE_INDIGENCY]);
        $this->post(route('barangay.document-payments.assess', $document), ['amount_due' => 20])->assertUnprocessable();
        $document->update(['document_type' => DocumentRequest::TYPE_CLEARANCE, 'amount_due' => 20, 'status' => DocumentRequest::STATUS_REJECTED]);
        $this->post(route('barangay.document-payments.assess', $document), ['amount_due' => 30])->assertUnprocessable();
        $this->actingAs($resident)->post(route('resident.document-payments.in-person', $document))->assertUnprocessable();
    }

    public function test_submitted_and_verified_payments_cannot_be_repriced_or_paid_again(): void
    {
        [$document, $resident, $secretary] = $this->paidRequestFixture();
        foreach ([DocumentRequest::PAYMENT_PENDING, DocumentRequest::PAYMENT_PAID] as $status) {
            $document->update(['payment_status' => $status]);
            $this->actingAs($secretary)->post(route('barangay.document-payments.assess', $document), ['amount_due' => 500])->assertUnprocessable();
            $this->actingAs($resident)->post(route('resident.document-payments.in-person', $document))->assertUnprocessable();
            $this->assertSame('75.00', $document->fresh()->amount_due);
        }
    }

    public function test_gcash_can_be_activated_after_secretary_assesses_a_fee(): void
    {
        [$document] = $this->paidRequestFixture(['payment_method' => null]);
        $document->barangay->update(['gcash_enabled' => false]);
        $municipal = User::factory()->create(['role' => User::ROLE_MUNICIPAL_LGU]);
        $this->actingAs($municipal)->put(route('municipal.barangays.gcash.update', $document->barangay), [
            'gcash_enabled' => '1', 'gcash_merchant_name' => 'Barangay Banday', 'gcash_account_identifier' => 'BANDAY-BRANCH',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertTrue($document->barangay->fresh()->gcashIsReady());
    }

    public function test_history_migration_keeps_existing_paid_and_pending_records(): void
    {
        [$document, $resident] = $this->paidRequestFixture([
            'payment_status' => DocumentRequest::PAYMENT_PAID,
            'payment_reference' => '1234567890123',
            'paid_at' => now()->subDay(),
        ]);
        $pending = $document->replicate();
        $pending->reference_number = 'REQ-OLD-PENDING';
        $pending->payment_reference = '2234567890123';
        $pending->payment_status = DocumentRequest::PAYMENT_PENDING;
        $pending->paid_at = null;
        $pending->save();
        $unpaid = $document->replicate();
        $unpaid->reference_number = 'REQ-OLD-UNPAID';
        $unpaid->payment_reference = null;
        $unpaid->payment_status = DocumentRequest::PAYMENT_UNPAID;
        $unpaid->paid_at = null;
        $unpaid->save();
        $migration = require database_path('migrations/2026_10_07_000000_create_document_payment_histories_table.php');
        $migration->down();
        $migration->up();
        $this->assertDatabaseCount('document_payment_histories', 2);
        $this->assertDatabaseHas('document_payment_histories', ['document_request_id' => $document->id, 'event' => 'imported']);
        $this->actingAs($resident)->get(route('resident.document-payments.history'))->assertOk()
            ->assertSee('1234567890123')->assertSee('2234567890123')->assertDontSee('REQ-OLD-UNPAID');
    }

    public function test_payment_history_is_visible_to_owner_and_assigned_secretary_only(): void
    {
        [$document, $resident, $secretary] = $this->paidRequestFixture(['payment_method' => null]);
        $this->actingAs($secretary)->post(route('barangay.document-payments.receive-in-person', $document), ['confirm_received' => 1])->assertRedirect();
        $this->assertDatabaseHas('document_payment_histories', ['document_request_id' => $document->id, 'event' => 'received', 'actor_id' => $secretary->id]);
        $document->update(['status' => DocumentRequest::STATUS_COMPLETED]);
        $this->get(route('barangay.document-payments.history'))->assertOk()->assertSee($document->reference_number)->assertSee($resident->name)->assertSee('In-person payment received');
        $this->actingAs($resident)->get(route('resident.document-payments.history'))->assertOk()->assertSee($document->reference_number)->assertSee('75.00');
        $other = User::factory()->create(['barangay_id' => $resident->barangay_id]);
        $this->actingAs($other)->get(route('resident.document-payments.history'))->assertOk()->assertDontSee($document->reference_number);
        $this->get(route('barangay.document-payments.history'))->assertForbidden();
        $foreign = User::factory()->create(['role' => User::ROLE_BARANGAY, 'barangay_id' => Barangay::where('name', 'Bogo')->value('id')]);
        $this->actingAs($foreign)->get(route('barangay.document-payments.history'))->assertOk()->assertDontSee($document->reference_number);
    }

    public function test_gcash_resubmission_preserves_payment_history_and_private_old_receipt(): void
    {
        Storage::fake('local');
        [$document, $resident, $secretary] = $this->paidRequestFixture();
        $submit = function (string $reference) use ($document, $resident) {
            $this->actingAs($resident)->post(route('resident.document-payments.submit', $document), [
                'payer_name' => 'Juan Resident', 'payer_mobile' => '09171234567',
                'payment_reference' => $reference, 'payment_transaction_at' => now()->subMinute()->format('Y-m-d\\TH:i'),
                'payment_proof' => UploadedFile::fake()->image('receipt.png'),
            ])->assertRedirect()->assertSessionHasNoErrors();
        };
        $submit('1234567890123');
        $first = \App\Models\DocumentPaymentHistory::firstOrFail();
        $oldPath = $first->snapshot['payment_proof_path'];
        $this->actingAs($secretary)->post(route('barangay.document-payments.verify', $document), ['decision' => 'reject', 'payment_remarks' => 'Please send a clearer receipt.'])->assertRedirect();
        $submit('2234567890123');
        $this->actingAs($secretary)->post(route('barangay.document-payments.verify', $document), ['decision' => 'verify'])->assertRedirect();
        $this->assertDatabaseCount('document_payment_histories', 4);
        Storage::disk('local')->assertExists($oldPath);
        $this->assertSame('1234567890123', $first->fresh()->snapshot['payment_reference']);
        $this->get(route('barangay.document-payments.history'))->assertOk()->assertSee('1234567890123')->assertSee('2234567890123')->assertSee('Please send a clearer receipt.');
        $this->get(route('document-payments.history.proof', $first))->assertOk();
        $this->actingAs($resident)->get(route('document-payments.history.proof', $first))->assertOk();
        $other = User::factory()->create(['barangay_id' => $resident->barangay_id]);
        $this->actingAs($other)->get(route('document-payments.history.proof', $first))->assertForbidden();
        $foreign = User::factory()->create(['role' => User::ROLE_BARANGAY, 'barangay_id' => Barangay::where('name', 'Bogo')->value('id')]);
        $this->actingAs($foreign)->get(route('document-payments.history.proof', $first))->assertForbidden();
    }

    public function test_secretary_can_record_payment_received_directly_at_the_counter(): void
    {
        [$document, $resident, $secretary] = $this->paidRequestFixture(['payment_method' => null]);
        $this->actingAs($resident)->post(route('barangay.document-payments.receive-in-person', $document), ['confirm_received' => 1])->assertForbidden();
        $this->actingAs($secretary)->post(route('barangay.document-payments.receive-in-person', $document))->assertSessionHasErrors('confirm_received');
        $this->post(route('barangay.document-payments.receive-in-person', $document), ['confirm_received' => 1])->assertRedirect();
        $this->assertSame(DocumentRequest::PAYMENT_METHOD_IN_PERSON, $document->fresh()->payment_method);
        $this->assertTrue($document->fresh()->isPaid());
    }

    /**
     * @param  array<string, mixed>  $requestOverrides
     * @return array{DocumentRequest, User, User}
     */
    private function paidRequestFixture(array $requestOverrides = []): array
    {
        $barangay = $this->barangayFixture([
            'name' => 'Banday',
            'municipality' => Barangay::MUNICIPALITY,
            'gcash_enabled' => true,
            'gcash_merchant_name' => 'Barangay Banday',
            'gcash_account_identifier' => 'BANDAY-BRANCH',
            'gcash_qr_path' => 'barangay-gcash-qr/1/qr.png',
        ]);
        $resident = User::factory()->create(['barangay_id' => $barangay->id]);
        $secretary = User::factory()->create([
            'role' => User::ROLE_BARANGAY,
            'barangay_id' => $barangay->id,
        ]);
        $request = DocumentRequest::create(array_merge([
            'resident_id' => $resident->id,
            'barangay_id' => $barangay->id,
            'reference_number' => 'REQ-GCASH-001',
            'document_type' => DocumentRequest::TYPE_CLEARANCE,
            'purpose' => 'Employment',
            'status' => DocumentRequest::STATUS_PENDING,
            'amount_due' => 75,
            'payment_method' => DocumentRequest::PAYMENT_METHOD_GCASH,
            'payment_status' => DocumentRequest::PAYMENT_UNPAID,
        ], $requestOverrides));

        return [$request, $resident, $secretary];
    }

    private function barangayFixture(array $attributes): Barangay
    {
        // Migrations already seed the municipality's barangays in the test DB.
        $barangay = Barangay::where('name', $attributes['name'])->firstOrFail();
        $barangay->update($attributes);

        return $barangay;
    }
}
