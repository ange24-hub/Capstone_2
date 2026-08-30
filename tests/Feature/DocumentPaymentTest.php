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

    public function test_new_request_captures_configured_fee_and_requires_gcash(): void
    {
        config(['rbim.document_fees.'.DocumentRequest::TYPE_CLEARANCE => 75]);
        $barangay = Barangay::create([
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
            'amount_due' => 75,
            'payment_method' => DocumentRequest::PAYMENT_METHOD_GCASH,
            'payment_status' => DocumentRequest::PAYMENT_UNPAID,
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

    public function test_barangay_cannot_process_a_document_before_payment_verification(): void
    {
        [$request, , $secretary] = $this->paidRequestFixture();

        $this->actingAs($secretary)->put(route('barangay.document-requests.update', $request), [
            'status' => DocumentRequest::STATUS_PROCESSING,
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
        $residentBarangay = Barangay::create(['name' => 'Banday', 'municipality' => Barangay::MUNICIPALITY]);
        $otherBarangay = Barangay::create([
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
        $banday = Barangay::create(['name' => 'Banday', 'municipality' => Barangay::MUNICIPALITY]);
        $bogo = Barangay::create(['name' => 'Bogo', 'municipality' => Barangay::MUNICIPALITY]);

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

    /**
     * @param  array<string, mixed>  $requestOverrides
     * @return array{DocumentRequest, User, User}
     */
    private function paidRequestFixture(array $requestOverrides = []): array
    {
        $barangay = Barangay::create([
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
}
