<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    private const REQUEST_STATUS = 'Request received. If this email is registered and email delivery is available, you should receive a reset link. Check Spam/Junk. If it does not arrive, wait one minute before retrying or contact your administrator.';

    protected function setUp(): void
    {
        if (! extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('The pdo_sqlite extension is required for isolated password reset tests.');
        }

        parent::setUp();
    }

    public function test_user_can_request_and_complete_a_password_reset(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'reset@example.com', 'password' => 'OldPassword123!']);

        $this->post(route('password.email'), ['email' => ' RESET@example.com '])
            ->assertRedirect()
            ->assertSessionHas('status', self::REQUEST_STATUS);

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user): bool {
            $this->post(route('password.update'), [
                'token' => $notification->token,
                'email' => 'RESET@example.com',
                'password' => 'NewPassword123!',
                'password_confirmation' => 'NewPassword123!',
            ])->assertRedirect(route('login'));

            return true;
        });

        $this->assertTrue(Hash::check('NewPassword123!', $user->fresh()->password));
    }

    public function test_reset_request_does_not_reveal_unknown_email_addresses(): void
    {
        Notification::fake();

        $this->post(route('password.email'), ['email' => 'unknown@example.com'])
            ->assertRedirect()
            ->assertSessionHas('status', self::REQUEST_STATUS);

        Notification::assertNothingSent();
    }

    public function test_mail_failure_keeps_response_private_and_does_not_log_smtp_secrets(): void
    {
        $user = User::factory()->create(['email' => 'reset@example.com']);
        Notification::shouldReceive('send')->once()->andThrow(new \RuntimeException('private-smtp-transcript'));
        Log::shouldReceive('warning')->once()->with('Password reset notification could not be delivered.', [
            'exception' => \RuntimeException::class,
            'diagnostic' => 'Run php artisan sbispgm:mail-check on the application server.',
        ]);

        $this->post(route('password.email'), ['email' => $user->email])
            ->assertRedirect()
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', self::REQUEST_STATUS);
    }

    public function test_repeated_reset_requests_do_not_send_duplicate_notifications(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        foreach (range(1, 2) as $attempt) {
            $this->post(route('password.email'), ['email' => $user->email])
                ->assertSessionHas('status', self::REQUEST_STATUS);
        }

        Notification::assertSentToTimes($user, ResetPassword::class, 1);
    }

    public function test_web_login_is_rate_limited(): void
    {
        foreach (range(1, 5) as $attempt) {
            $this->post(route('login.store'), ['login' => 'missing@example.com', 'password' => 'wrong-password'])
                ->assertSessionHasErrors('login');
        }

        $this->post(route('login.store'), ['login' => 'missing@example.com', 'password' => 'wrong-password'])
            ->assertStatus(429);
    }
}
