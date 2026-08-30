<?php

namespace Tests\Feature;

use App\Models\Barangay;
use App\Models\DocumentRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class AuthRbacTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        if (! extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('The pdo_sqlite extension is required for isolated auth feature tests.');
        }

        parent::setUp();
    }

    public function test_resident_registers_for_a_barangay_and_waits_for_approval(): void
    {
        $barangay = Barangay::where('name', 'Anahawan')->firstOrFail();

        $response = $this->post('/register', [
            'name' => 'Juan Resident',
            'email' => 'juan@example.com',
            'role' => User::ROLE_RESIDENT,
            'barangay_id' => $barangay->id,
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'email' => 'juan@example.com',
            'role' => User::ROLE_RESIDENT,
            'barangay_id' => $barangay->id,
            'approval_status' => User::APPROVAL_PENDING,
        ]);

        $this->get('/dashboard')->assertRedirect(route('approval.pending'));
        $this->get(route('dashboard.resident'))->assertRedirect(route('approval.pending'));
        $this->post(route('resident.document-requests.store'), [
            'document_type' => DocumentRequest::TYPE_CLEARANCE,
            'purpose' => 'Employment requirement',
        ])->assertRedirect(route('approval.pending'));
        $this->assertDatabaseCount('document_requests', 0);

        $this->get(route('approval.pending'))
            ->assertOk()
            ->assertSee('Awaiting secretary approval')
            ->assertSee('Anahawan');
    }

    public function test_barangay_secretary_can_approve_only_their_resident(): void
    {
        $barangay = Barangay::where('name', 'Looc')->firstOrFail();
        $secretary = User::factory()->create([
            'role' => User::ROLE_BARANGAY,
            'barangay_id' => $barangay->id,
        ]);
        $resident = User::factory()->create([
            'role' => User::ROLE_RESIDENT,
            'barangay_id' => $barangay->id,
            'approval_status' => User::APPROVAL_PENDING,
        ]);

        $this->actingAs($secretary)
            ->get(route('dashboard.barangay'))
            ->assertOk()
            ->assertSee($resident->name)
            ->assertSee('Pending Resident Registrations');

        $this->actingAs($secretary)
            ->post(route('barangay.residents.approve', $resident))
            ->assertRedirect();

        $this->assertDatabaseHas('users', [
            'id' => $resident->id,
            'approval_status' => User::APPROVAL_APPROVED,
            'approved_by' => $secretary->id,
        ]);

        $this->actingAs($resident->refresh())
            ->get(route('dashboard.resident'))
            ->assertOk();
    }

    public function test_secretary_cannot_approve_a_resident_from_another_barangay(): void
    {
        $secretary = User::factory()->create([
            'role' => User::ROLE_BARANGAY,
            'barangay_id' => Barangay::where('name', 'Looc')->firstOrFail()->id,
        ]);
        $resident = User::factory()->create([
            'role' => User::ROLE_RESIDENT,
            'barangay_id' => Barangay::where('name', 'Luan')->firstOrFail()->id,
            'approval_status' => User::APPROVAL_PENDING,
        ]);

        $this->actingAs($secretary)
            ->post(route('barangay.residents.approve', $resident))
            ->assertForbidden();

        $this->assertDatabaseHas('users', [
            'id' => $resident->id,
            'approval_status' => User::APPROVAL_PENDING,
        ]);
    }

    public function test_staff_registration_requires_matching_access_code(): void
    {
        Config::set('auth.registration_codes.barangay', 'barangay-secret');

        $this->post('/register', [
            'name' => 'Barangay Staff',
            'email' => 'staff@example.com',
            'role' => User::ROLE_BARANGAY,
            'access_code' => 'wrong-code',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ])->assertSessionHasErrors('access_code');

        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'staff@example.com']);
    }

    public function test_barangay_secretary_registers_and_waits_for_municipal_approval(): void
    {
        Config::set('auth.registration_codes.barangay', 'barangay-secret');
        $barangay = Barangay::where('name', 'Banday')->firstOrFail();

        $this->post('/register', [
            'name' => 'Maria Secretary',
            'email' => 'maria.banday@example.com',
            'user_id' => 'TO-BANDAY-001',
            'role' => User::ROLE_BARANGAY,
            'barangay_id' => $barangay->id,
            'access_code' => 'barangay-secret',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'email' => 'maria.banday@example.com',
            'staff_id' => 'TO-BANDAY-001',
            'role' => User::ROLE_BARANGAY,
            'barangay_id' => $barangay->id,
            'approval_status' => User::APPROVAL_PENDING,
        ]);

        $this->get(route('dashboard'))
            ->assertRedirect(route('approval.pending'));

        $this->get(route('approval.pending'))
            ->assertOk()
            ->assertSee('Awaiting Municipal LGU approval')
            ->assertSee('Barangay Banday')
            ->assertSee('Maria Secretary');
    }

    public function test_municipal_admin_can_approve_a_barangay_secretary(): void
    {
        $barangay = Barangay::where('name', 'Banday')->firstOrFail();
        $municipal = User::factory()->create(['role' => User::ROLE_MUNICIPAL_LGU]);
        $secretary = User::factory()->create([
            'role' => User::ROLE_BARANGAY,
            'barangay_id' => $barangay->id,
            'staff_id' => 'TO-BANDAY-002',
            'approval_status' => User::APPROVAL_PENDING,
            'approved_at' => null,
        ]);

        $this->actingAs($municipal)
            ->get(route('dashboard.municipal'))
            ->assertOk()
            ->assertSee($secretary->name)
            ->assertSee('Pending Barangay Secretary Accounts');

        $this->actingAs($municipal)
            ->post(route('municipal.secretaries.approve', $secretary))
            ->assertRedirect();

        $this->assertDatabaseHas('users', [
            'id' => $secretary->id,
            'approval_status' => User::APPROVAL_APPROVED,
            'approved_by' => $municipal->id,
        ]);

        $this->actingAs($secretary->refresh())
            ->get(route('dashboard.barangay'))
            ->assertOk();
    }

    public function test_secretary_can_login_with_user_id(): void
    {
        $secretary = User::factory()->create([
            'role' => User::ROLE_BARANGAY,
            'staff_id' => 'TO-LOOC-001',
            'password' => 'Password123!',
        ]);

        $this->post('/login', [
            'login' => $secretary->staff_id,
            'password' => 'Password123!',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($secretary);
    }

    public function test_registration_page_lists_every_tomas_oppus_barangay(): void
    {
        $response = $this->get(route('register'))
            ->assertOk();

        foreach (Barangay::TOMAS_OPPUS_BARANGAYS as $barangay) {
            $response->assertSee($barangay);
        }

        $this->assertDatabaseCount('barangays', count(Barangay::TOMAS_OPPUS_BARANGAYS));
    }

    public function test_public_registration_only_offers_resident_and_barangay_accounts(): void
    {
        $this->get(route('register'))
            ->assertOk()
            ->assertSee('Create Resident Account')
            ->assertSee('Barangay Secretary')
            ->assertDontSee('value="municipal_lgu"', false);
    }

    public function test_public_registration_rejects_a_municipal_account_role(): void
    {
        $this->post(route('register.store'), [
            'name' => 'Unauthorized Municipal User',
            'email' => 'unauthorized-municipal@example.com',
            'role' => User::ROLE_MUNICIPAL_LGU,
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ])->assertSessionHasErrors('role');

        $this->assertDatabaseMissing('users', ['email' => 'unauthorized-municipal@example.com']);
    }

    public function test_user_can_login_and_logout(): void
    {
        $user = User::factory()->create([
            'email' => 'login@example.com',
            'password' => 'Password123!',
        ]);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'Password123!',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);

        $this->post('/logout')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_role_hierarchy_limits_dashboard_access(): void
    {
        $resident = User::factory()->create(['role' => User::ROLE_RESIDENT]);
        $barangay = User::factory()->create(['role' => User::ROLE_BARANGAY]);
        $municipal = User::factory()->create(['role' => User::ROLE_MUNICIPAL_LGU]);

        $this->actingAs($resident)
            ->get('/dashboard/barangay')
            ->assertForbidden();

        $this->actingAs($barangay)
            ->get('/dashboard/barangay')
            ->assertOk();

        $this->actingAs($barangay)
            ->get('/dashboard/municipal')
            ->assertForbidden();

        $this->actingAs($barangay)
            ->get(route('municipal.barangays.index'))
            ->assertForbidden();

        $this->actingAs($municipal)
            ->get('/dashboard/municipal')
            ->assertOk();

        $this->actingAs($municipal)
            ->get(route('municipal.barangays.index'))
            ->assertOk()
            ->assertSee('Tomas Oppus Barangay Directory')
            ->assertSee('All Barangays')
            ->assertSee('Carnaga');

        $this->actingAs($municipal)
            ->get('/dashboard/resident')
            ->assertForbidden();

        $this->actingAs($municipal)
            ->get('/dashboard/barangay')
            ->assertForbidden();

        $this->actingAs($municipal)
            ->get('/registry')
            ->assertForbidden();

        $this->actingAs($municipal)
            ->get('/migration-monitoring')
            ->assertOk();
    }

    public function test_resident_can_request_a_document_from_dashboard(): void
    {
        $barangay = Barangay::where('name', 'San Isidro')->firstOrFail();
        $resident = User::factory()->create([
            'role' => User::ROLE_RESIDENT,
            'barangay_id' => $barangay->id,
        ]);

        $this->actingAs($resident)
            ->post(route('resident.document-requests.store'), [
                'document_type' => DocumentRequest::TYPE_INDIGENCY,
                'purpose' => 'Scholarship requirement',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('document_requests', [
            'resident_id' => $resident->id,
            'barangay_id' => $barangay->id,
            'document_type' => DocumentRequest::TYPE_INDIGENCY,
            'purpose' => 'Scholarship requirement',
            'status' => DocumentRequest::STATUS_PENDING,
        ]);

        $documentRequest = DocumentRequest::where('resident_id', $resident->id)->firstOrFail();
        $secretary = User::factory()->create([
            'role' => User::ROLE_BARANGAY,
            'barangay_id' => $barangay->id,
        ]);

        $this->actingAs($secretary)
            ->get(route('dashboard.barangay'))
            ->assertOk()
            ->assertSee($documentRequest->reference_number)
            ->assertSee($resident->name);

        $this->actingAs($secretary)
            ->put(route('barangay.document-requests.update', $documentRequest), [
                'status' => DocumentRequest::STATUS_READY,
                'remarks' => 'Ready for pickup at the barangay office.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('document_requests', [
            'id' => $documentRequest->id,
            'barangay_id' => $barangay->id,
            'status' => DocumentRequest::STATUS_READY,
            'reviewed_by' => $secretary->id,
        ]);

        $this->actingAs($resident->refresh())
            ->get(route('dashboard.resident'))
            ->assertOk()
            ->assertSee('Barangay Indigency')
            ->assertSee('Scholarship requirement')
            ->assertSee('Ready for release')
            ->assertSee('Ready for pickup at the barangay office.');
    }

    public function test_only_residents_can_submit_document_requests(): void
    {
        $barangay = User::factory()->create(['role' => User::ROLE_BARANGAY]);

        $this->actingAs($barangay)
            ->post(route('resident.document-requests.store'), [
                'document_type' => DocumentRequest::TYPE_CLEARANCE,
                'purpose' => 'Local requirement',
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('document_requests', 0);
    }
}
