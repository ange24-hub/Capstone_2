<?php

namespace Tests\Feature;

use Illuminate\Mail\Mailer;
use Illuminate\Support\Facades\Mail;
use Mockery;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Tests\TestCase;

class MailCheckTest extends TestCase
{
    public function test_non_delivery_mailer_fails_without_connecting(): void
    {
        config(['mail.default' => 'log']);
        Mail::shouldReceive('mailer')->never();

        $this->artisan('sbispgm:mail-check')
            ->expectsOutput('FAIL: Set MAIL_MAILER=smtp in the server environment and rebuild the configuration cache.')
            ->assertFailed();
    }

    public function test_smtp_check_authenticates_without_sending_a_message(): void
    {
        $transport = $this->mockTransport();
        $transport->shouldReceive('start')->once();
        $transport->shouldReceive('stop')->once();
        $transport->shouldReceive('send')->never();

        $this->artisan('sbispgm:mail-check')
            ->expectsOutput('PASS: SMTP connection and authentication succeeded. No email was sent.')
            ->assertSuccessful();
    }

    public function test_authentication_failure_is_reported_without_credentials(): void
    {
        $transport = $this->mockTransport();
        $transport->shouldReceive('start')->once()->andThrow(new \RuntimeException('535 private-smtp-password'));

        $this->artisan('sbispgm:mail-check')
            ->expectsOutput('FAIL: SMTP authentication failed. Check the server username and password. Gmail requires a valid Google App Password.')
            ->doesntExpectOutputToContain('private-smtp-password')
            ->assertFailed();
    }

    public function test_connection_failure_is_reported_without_a_transcript(): void
    {
        $transport = $this->mockTransport();
        $transport->shouldReceive('start')->once()->andThrow(new \RuntimeException('Connection timed out: private-host'));

        $this->artisan('sbispgm:mail-check')
            ->expectsOutput('FAIL: SMTP connection failed. Check the server mail configuration, DNS and outbound SMTP port access.')
            ->doesntExpectOutputToContain('private-host')
            ->assertFailed();
    }

    private function mockTransport(): EsmtpTransport
    {
        config(['mail.default' => 'smtp', 'mail.from.address' => 'sender@example.com']);
        $transport = Mockery::mock(EsmtpTransport::class);
        $transport->shouldReceive('getUsername')->andReturn('sender@example.com');
        $transport->shouldReceive('getPassword')->andReturn('private-smtp-password');
        $mailer = Mockery::mock(Mailer::class);
        $mailer->shouldReceive('getSymfonyTransport')->once()->andReturn($transport);
        Mail::shouldReceive('mailer')->once()->andReturn($mailer);

        return $transport;
    }
}
