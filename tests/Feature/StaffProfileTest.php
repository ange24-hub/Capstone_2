<?php
namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class StaffProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_edit_own_profile_without_changing_privileges(): void
    {
        foreach ([User::ROLE_BARANGAY, User::ROLE_MUNICIPAL_LGU] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $this->actingAs($user)->get(route('profile.edit'))->assertOk()->assertSee('My Profile');
            $this->put(route('profile.update'), ['name' => 'Updated Name', 'email' => $user->email,
                'role' => User::ROLE_RESIDENT, 'barangay_id' => 999, 'approval_status' => 'rejected'])
                ->assertSessionHasNoErrors()->assertRedirect(route('profile.edit'));
            $saved = $user->fresh();
            $this->assertSame('Updated Name', $saved->name);
            $this->assertSame($role, $saved->role);
            $this->assertSame($user->barangay_id, $saved->barangay_id);
            $this->assertSame($user->approval_status, $saved->approval_status);
        }
    }

    public function test_residents_and_guests_cannot_edit_profiles(): void
    {
        $this->get(route('profile.edit'))->assertRedirect(route('login'));
        $user = User::factory()->create(['role' => User::ROLE_RESIDENT]);
        $this->actingAs($user)->get(route('profile.edit'))->assertForbidden();
        $this->put(route('profile.update'), ['name' => 'Unauthorized', 'email' => $user->email])->assertForbidden();
        $this->assertNotSame('Unauthorized', $user->fresh()->name);
    }

    public function test_sensitive_changes_require_password_and_unique_email(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_MUNICIPAL_LGU, 'password' => Hash::make('current-password')]);
        $other = User::factory()->create();
        $this->actingAs($user)->put(route('profile.update'), ['name' => $user->name, 'email' => 'new@example.test'])
            ->assertSessionHasErrors('current_password');
        $this->put(route('profile.update'), ['name' => $user->name, 'email' => $other->email, 'current_password' => 'current-password'])
            ->assertSessionHasErrors('email');
        $this->put(route('profile.update'), ['name' => $user->name, 'email' => 'new@example.test', 'current_password' => 'current-password',
            'password' => 'new-password', 'password_confirmation' => 'new-password'])->assertSessionHasNoErrors();
        $this->assertSame('new@example.test', $user->fresh()->email);
        $this->assertTrue(Hash::check('new-password', $user->fresh()->password));
    }
}
