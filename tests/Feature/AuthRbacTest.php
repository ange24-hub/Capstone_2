<?php

namespace Tests\Feature;

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

    public function test_resident_can_register_and_reaches_resident_dashboard(): void
    {
        $response = $this->post('/register', [
            'name' => 'Juan Resident',
            'email' => 'juan@example.com',
            'role' => User::ROLE_RESIDENT,
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'email' => 'juan@example.com',
            'role' => User::ROLE_RESIDENT,
        ]);

        $this->get('/dashboard')->assertRedirect(route('dashboard.resident'));
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

        $this->actingAs($municipal)
            ->get('/dashboard/municipal')
            ->assertOk();

        $this->actingAs($municipal)
            ->get('/dashboard/resident')
            ->assertOk();
    }

    public function test_resident_can_request_a_document_from_dashboard(): void
    {
        $resident = User::factory()->create(['role' => User::ROLE_RESIDENT]);

        $this->actingAs($resident)
            ->post(route('resident.document-requests.store'), [
                'document_type' => DocumentRequest::TYPE_INDIGENCY,
                'purpose' => 'Scholarship requirement',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('document_requests', [
            'resident_id' => $resident->id,
            'document_type' => DocumentRequest::TYPE_INDIGENCY,
            'purpose' => 'Scholarship requirement',
            'status' => DocumentRequest::STATUS_PENDING,
        ]);

        $this->actingAs($resident)
            ->get(route('dashboard.resident'))
            ->assertOk()
            ->assertSee('Barangay Indigency')
            ->assertSee('Scholarship requirement')
            ->assertSee('Pending');
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
