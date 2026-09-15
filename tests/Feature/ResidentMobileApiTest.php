<?php

namespace Tests\Feature;

use App\Models\{Barangay, DocumentRequest, ResidentConcern, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Hash, Storage};
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ResidentMobileApiTest extends TestCase
{
    use RefreshDatabase;

    private string $api = '/api/resident/v1';

    private function resident(array $attributes = []): User
    {
        return User::factory()->create(array_merge(['role' => User::ROLE_RESIDENT,
            'barangay_id' => Barangay::where('name', 'Looc')->firstOrFail()->id,
            'approval_status' => User::APPROVAL_APPROVED, 'password' => Hash::make('resident-password')], $attributes));
    }

    private function headers(User $user, array $abilities = ['resident:mobile']): array
    {
        return ['Authorization' => 'Bearer '.$user->createToken('test', $abilities, now()->addDays(7))->plainTextToken];
    }

    public function test_login_rejects_staff_and_bad_credentials_and_revokes_logout_token(): void
    {
        $staff = $this->resident(['role' => User::ROLE_BARANGAY]);
        $this->postJson("$this->api/login", ['email' => $staff->email, 'password' => 'resident-password'])->assertForbidden();
        $user = $this->resident();
        $this->postJson("$this->api/login", ['email' => $user->email, 'password' => 'wrong'])->assertUnprocessable();
        $response = $this->postJson("$this->api/login", ['email' => $user->email, 'password' => 'resident-password'])
            ->assertOk()->assertJsonPath('user.role', 'resident')->assertJsonMissingPath('user.password');
        $headers = ['Authorization' => 'Bearer '.$response->json('token')];
        $this->getJson("$this->api/me", $headers)->assertOk();
        $this->postJson("$this->api/logout", [], $headers)->assertOk();
        $this->app['auth']->forgetGuards();
        $this->getJson("$this->api/me", $headers)->assertUnauthorized();
    }

    public function test_registration_forces_resident_pending_and_denies_services_until_approved(): void
    {
        $barangay = Barangay::where('name', 'Looc')->firstOrFail();
        $response = $this->postJson("$this->api/register", ['name' => 'New Resident', 'email' => 'new@example.test',
            'password' => 'resident-password', 'password_confirmation' => 'resident-password', 'barangay_id' => $barangay->id,
            'role' => 'municipal_lgu', 'approval_status' => 'approved'])->assertCreated()->assertJsonPath('user.approval_status', 'pending');
        $headers = ['Authorization' => 'Bearer '.$response->json('token')];
        $this->getJson("$this->api/me", $headers)->assertOk()->assertJsonPath('user.role', 'resident');
        $this->getJson("$this->api/dashboard", $headers)->assertForbidden();
        $this->postJson("$this->api/documents", ['document_type' => DocumentRequest::TYPE_INDIGENCY, 'purpose' => 'School'], $headers)->assertForbidden();
        User::where('email', 'new@example.test')->update(['approval_status' => 'approved']);
        $this->app['auth']->forgetGuards();
        $this->getJson("$this->api/dashboard", $headers)->assertOk();
    }

    public function test_role_ability_expiry_and_revoked_approval_are_checked_on_every_request(): void
    {
        $user = $this->resident();
        $this->getJson("$this->api/dashboard")->assertUnauthorized();
        $this->getJson("$this->api/dashboard", $this->headers($user, ['other']))->assertForbidden();
        $this->app['auth']->forgetGuards();
        $expired = $user->createToken('expired', ['resident:mobile'], now()->subMinute())->plainTextToken;
        $this->getJson("$this->api/dashboard", ['Authorization' => 'Bearer '.$expired])->assertUnauthorized();
        $headers = $this->headers($user);
        $user->update(['role' => User::ROLE_MUNICIPAL_LGU]);
        $this->app['auth']->forgetGuards();
        $this->getJson("$this->api/me", $headers)->assertForbidden();
        $user->update(['role' => User::ROLE_RESIDENT, 'approval_status' => 'rejected']);
        $this->app['auth']->forgetGuards();
        $this->getJson("$this->api/dashboard", $headers)->assertForbidden();
    }

    public function test_documents_share_staff_records_but_never_expose_another_residents_data(): void
    {
        config(['rbim.document_fees.'.DocumentRequest::TYPE_INDIGENCY => 0]);
        $user = $this->resident(); $peer = $this->resident(); $headers = $this->headers($user);
        $id = $this->postJson("$this->api/documents", ['document_type' => DocumentRequest::TYPE_INDIGENCY,
            'purpose' => 'School scholarship', 'resident_id' => $peer->id, 'status' => 'completed', 'amount_due' => 999], $headers)
            ->assertCreated()->json('id');
        $document = DocumentRequest::findOrFail($id);
        $this->assertSame($user->id, $document->resident_id);
        $this->assertSame('pending', $document->status);
        $this->assertEquals(0, $document->amount_due);
        $document->update(['status' => 'ready', 'remarks' => 'Claim at the hall.']);
        $this->getJson("$this->api/documents/$id", $headers)->assertOk()->assertJsonPath('data.status', 'ready')->assertJsonPath('data.remarks', 'Claim at the hall.');
        $this->app['auth']->forgetGuards();
        $this->getJson("$this->api/documents/$id", $this->headers($peer))->assertNotFound();
        $this->app['auth']->forgetGuards();
        $this->getJson("$this->api/documents", $this->headers($peer))->assertJsonCount(0, 'data');
    }

    public function test_concerns_hide_internal_notes_and_support_public_replies(): void
    {
        $user = $this->resident(); $headers = $this->headers($user);
        $id = $this->postJson("$this->api/concerns", ['title' => 'Broken streetlight', 'description' => 'The streetlight near the hall has stopped working.',
            'category' => 'lighting', 'status' => 'resolved'], $headers)->assertCreated()->json('id');
        $concern = ResidentConcern::findOrFail($id);
        $concern->updates()->create(['author_id' => $user->id, 'message' => 'We will inspect it.', 'internal_note' => 'PRIVATE STAFF NOTE']);
        $this->getJson("$this->api/concerns/$id", $headers)->assertOk()->assertJsonPath('data.status', 'submitted')->assertDontSee('PRIVATE STAFF NOTE');
        $this->postJson("$this->api/concerns/$id/reply", ['message' => 'It is beside the school.'], $headers)->assertOk();
        $concern->update(['status' => 'closed']);
        $this->postJson("$this->api/concerns/$id/reply", ['message' => 'Another reply.'], $headers)->assertUnprocessable();
        $this->app['auth']->forgetGuards();
        $this->getJson("$this->api/concerns/$id", $this->headers($this->resident()))->assertNotFound();
    }

    public function test_profile_requires_current_password_and_cannot_change_role_or_verified_name(): void
    {
        $user = $this->resident(); $headers = $this->headers($user);
        $this->putJson("$this->api/profile", ['email' => 'changed@example.test', 'current_password' => 'wrong'], $headers)->assertUnprocessable();
        $this->putJson("$this->api/profile", ['email' => 'changed@example.test', 'current_password' => 'resident-password',
            'role' => 'barangay', 'name' => 'Spoofed', 'approval_status' => 'approved'], $headers)->assertOk();
        $this->assertSame($user->name, $user->fresh()->name);
        $this->assertSame('resident', $user->fresh()->role);
        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_paid_requests_keep_existing_payment_rules_and_private_receipts(): void
    {
        Storage::fake('local');
        $user = $this->resident(); $headers = $this->headers($user);
        config(['rbim.document_fees.'.DocumentRequest::TYPE_CLEARANCE => 50]);
        $payload = ['document_type' => DocumentRequest::TYPE_CLEARANCE, 'purpose' => 'Employment'];
        $this->postJson("$this->api/documents", $payload, $headers)->assertUnprocessable();
        $user->barangay->update(['gcash_enabled' => true, 'gcash_merchant_name' => 'Barangay Looc', 'gcash_account_identifier' => '09123456789', 'gcash_qr_path' => 'qr.png']);
        $this->app['auth']->forgetGuards();
        $id = $this->postJson("$this->api/documents", $payload, $headers)->assertCreated()->json('id');
        $this->postJson("$this->api/documents/$id/payment", ['payer_name' => $user->name, 'payer_mobile' => '09123456789',
            'payment_reference' => '1234567890123', 'payment_transaction_at' => now()->subMinute()->toDateTimeString(),
            'payment_proof' => UploadedFile::fake()->image('receipt.png')], $headers)->assertOk();
        $document = DocumentRequest::findOrFail($id);
        $this->assertSame('pending_verification', $document->payment_status);
        Storage::disk('local')->assertExists($document->payment_proof_path);
        $this->getJson("$this->api/documents/$id", $headers)->assertJsonMissingPath('data.payment_proof_path');
        $this->postJson("$this->api/documents/$id/payment", [], $headers)->assertUnprocessable();
    }
}
