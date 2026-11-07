<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;

class MailCheck extends Command
{
    protected $signature = 'sbispgm:mail-check';

    protected $description = 'Check the active SMTP connection and authentication without sending email or printing credentials';

    public function handle(): int
    {
        if (config('mail.default') !== 'smtp') {
            $this->error('FAIL: Set MAIL_MAILER=smtp in the server environment and rebuild the configuration cache.');

            return self::FAILURE;
        }

        if (! filter_var(config('mail.from.address'), FILTER_VALIDATE_EMAIL)) {
            $this->error('FAIL: Configure a valid MAIL_FROM_ADDRESS.');

            return self::FAILURE;
        }

        try {
            $transport = Mail::mailer()->getSymfonyTransport();
            if (! $transport instanceof EsmtpTransport || $transport->getUsername() === '' || $transport->getPassword() === '') {
                $this->error('FAIL: Configure SMTP transport, MAIL_USERNAME and MAIL_PASSWORD on this server.');

                return self::FAILURE;
            }

            // start() performs EHLO, STARTTLS (when offered), and authentication only.
            $transport->start();
            $transport->stop();
        } catch (\Throwable $exception) {
            // Classify privately; never print the exception or SMTP transcript.
            $message = strtolower($exception->getMessage());
            if (str_contains($message, 'authenticate') || str_contains($message, '535') || str_contains($message, '534')) {
                $this->error('FAIL: SMTP authentication failed. Check the server username and password. Gmail requires a valid Google App Password.');
            } elseif (str_contains($message, 'certificate') || str_contains($message, 'crypto') || str_contains($message, 'starttls')) {
                $this->error('FAIL: SMTP TLS negotiation failed. Check the SMTP port, TLS settings and server CA certificates.');
            } else {
                $this->error('FAIL: SMTP connection failed. Check the server mail configuration, DNS and outbound SMTP port access.');
            }

            return self::FAILURE;
        }

        $this->info('PASS: SMTP connection and authentication succeeded. No email was sent.');
        $this->warn('This does not confirm inbox delivery. Verify a password reset for an account registered on this deployment.');

        return self::SUCCESS;
    }
}
