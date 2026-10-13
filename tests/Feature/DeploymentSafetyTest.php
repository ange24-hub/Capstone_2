<?php

namespace Tests\Feature;

use App\Models\Barangay;
use App\Models\User;
use App\Services\MigrationPredictionService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DeploymentSafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_production_seeding_creates_barangays_without_demo_accounts(): void
    {
        $this->app['env'] = 'production';
        $this->app->make(DatabaseSeeder::class)->run();
        $this->assertDatabaseCount('users', 0);
        $this->assertSame(29, Barangay::where('municipality', Barangay::MUNICIPALITY)->count());
    }

    public function test_admin_provisioning_hashes_password_and_does_not_overwrite_existing_accounts(): void
    {
        $answers = ['Administrator name' => 'Pilot Admin', 'Administrator email' => 'admin@example.test',
            'Password (at least 12 characters, letters and numbers)' => 'PrivatePilot987!', 'Confirm password' => 'PrivatePilot987!'];
        $command = $this->artisan('sbispgm:create-admin');
        foreach ($answers as $prompt => $answer) $command->expectsQuestion($prompt, $answer);
        $command->assertSuccessful()->run();
        $user = User::where('email', 'admin@example.test')->sole();
        $this->assertSame(User::ROLE_MUNICIPAL_LGU, $user->role);
        $this->assertTrue(Hash::check('PrivatePilot987!', $user->password));
        $command = $this->artisan('sbispgm:create-admin');
        foreach ($answers as $prompt => $answer) $command->expectsQuestion($prompt, $answer);
        $command->assertFailed()->run();
        $this->assertDatabaseCount('users', 1);
    }

    public function test_private_pages_cannot_be_cached_by_shared_browser_or_proxy(): void
    {
        $this->get(route('login'))->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $staff = User::factory()->create(['role' => User::ROLE_MUNICIPAL_LGU]);
        $response = $this->actingAs($staff)->get(route('reports.population'))->assertOk();
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
    }

    public function test_web_registration_rejects_barangays_outside_the_municipality(): void
    {
        $outside = Barangay::create(['name' => 'Outside Office', 'municipality' => 'Other Municipality']);
        $this->post(route('register.store'), ['name' => 'Test Resident', 'email' => 'outside@example.test',
            'role' => User::ROLE_RESIDENT, 'barangay_id' => $outside->id,
            'password' => 'PrivatePilot987!', 'password_confirmation' => 'PrivatePilot987!'])
            ->assertSessionHasErrors('barangay_id');
        $this->assertDatabaseCount('users', 0);
    }

    public function test_disabled_or_remote_prediction_service_never_receives_data(): void
    {
        Http::fake();
        config(['services.migration_prediction.enabled' => false]);
        $this->assertNull(app(MigrationPredictionService::class)->predictOutMigration([]));
        config(['services.migration_prediction.enabled' => true, 'services.migration_prediction.url' => 'https://external.example.test']);
        $this->assertNull(app(MigrationPredictionService::class)->predictOutMigration([]));
        Http::assertNothingSent();
    }

    public function test_deployment_check_fails_on_development_settings_even_when_database_is_skipped(): void
    {
        $this->artisan('sbispgm:deployment-check', ['--skip-database' => true])
            ->expectsOutput('FAIL: APP_ENV is production')->assertFailed();
    }

    public function test_sessions_from_before_a_password_change_are_rejected(): void
    {
        $staff = User::factory()->create(['role' => User::ROLE_MUNICIPAL_LGU]);
        $previousHash = $staff->password;
        $staff->update(['password' => 'ChangedPrivate987!']);
        $this->actingAs($staff->fresh())->withSession(['password_hash_web' => $previousHash])
            ->get(route('profile.edit'))->assertRedirect(route('login'));
        $this->assertGuest();
    }
}
