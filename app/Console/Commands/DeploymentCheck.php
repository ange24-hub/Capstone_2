<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DeploymentCheck extends Command
{
    protected $signature = 'sbispgm:deployment-check {--skip-database : Check files and configuration only}';

    protected $description = 'Read-only checks for an HTTPS production deployment; never prints credentials';

    public function handle(): int
    {
        $failures = 0;
        $check = function (bool $ok, string $message) use (&$failures): void {
            $ok ? $this->info('PASS: '.$message) : $this->error('FAIL: '.$message);
            if (! $ok) $failures++;
        };
        $check(PHP_VERSION_ID >= 80200, 'PHP 8.2 or newer');
        foreach (['ctype', 'curl', 'dom', 'fileinfo', 'filter', 'gd', 'hash', 'mbstring', 'openssl', 'pdo', 'pdo_mysql', 'session', 'tokenizer', 'xml', 'zip'] as $extension) {
            $check(extension_loaded($extension), 'PHP extension '.$extension);
        }
        $check(app()->environment('production'), 'APP_ENV is production');
        $check(config('app.debug') === false, 'APP_DEBUG is false');
        try {
            app('encrypter');
            $check(true, 'APP_KEY is a valid encryption key');
        } catch (\Throwable) {
            $check(false, 'APP_KEY is a valid encryption key');
        }
        $url = parse_url((string) config('app.url'));
        $host = strtolower($url['host'] ?? '');
        $check(($url['scheme'] ?? '') === 'https' && str_contains($host, '.') && ! str_contains($host, '_')
            && ! in_array($host, ['localhost', '127.0.0.1', 'example.com'], true)
            && empty($url['path']) && empty($url['user']) && empty($url['pass']), 'APP_URL is the real HTTPS domain, hosted at its root');
        $check(config('session.secure') === true && config('session.http_only') === true, 'Secure HTTP-only session cookies');
        $code = (string) config('auth.registration_codes.barangay');
        $check(strlen($code) >= 24 && ! in_array($code, ['barangay-secret', 'CHANGE_ME'], true), 'Private barangay registration code with at least 24 characters');
        $check(config('database.default') === 'mysql', 'Production database uses MySQL');
        $connection = config('database.connections.mysql');
        $check(filled($connection['password'] ?? null) && ! in_array($connection['username'] ?? '', ['', 'root'], true), 'Dedicated database account with a password');
        $check(! in_array(config('mail.default'), ['log', 'array', null], true), 'Delivery mailer configured for password reset messages');
        $check(filled(config('mail.from.address')) && ! str_ends_with((string) config('mail.from.address'), '@example.com'), 'Real password reset sender address');
        if (config('mail.default') === 'smtp') {
            $check(filled(config('mail.mailers.smtp.host')) && filled(config('mail.mailers.smtp.username'))
                && filled(config('mail.mailers.smtp.password')), 'SMTP host and account are configured');
        }
        $manifest = public_path('build/manifest.json');
        $assets = is_file($manifest) ? json_decode(file_get_contents($manifest), true) : null;
        $complete = is_array($assets) && isset($assets['resources/css/app.css'], $assets['resources/js/app.js']);
        foreach (is_array($assets) ? $assets : [] as $entry) {
            if (! is_array($entry)) { $complete = false; continue; }
            foreach (array_merge([$entry['file'] ?? ''], $entry['css'] ?? []) as $file) {
                $complete = $complete && $file !== '' && is_file(public_path('build/'.$file));
            }
        }
        $check($complete, 'Production frontend manifest and compiled files exist');
        $check(! is_file(public_path('hot')), 'Vite development server marker is absent');
        foreach (['storage', 'storage/app', 'storage/framework/cache', 'storage/framework/sessions', 'storage/framework/views', 'storage/logs', 'bootstrap/cache'] as $path) {
            $check(is_dir(base_path($path)) && is_writable(base_path($path)), 'Writable '.$path);
        }
        foreach (['.env', 'composer.json', 'composer.lock', 'database', 'storage/app'] as $path) {
            $check(! file_exists(public_path($path)), 'Private '.$path.' is outside the web root');
        }

        if ($this->option('skip-database')) {
            $this->warn('Database checks skipped. This is an incomplete deployment check.');
        } else {
            try {
                DB::connection()->getPdo();
                $check(true, 'Database connection works');
                $migrator = app('migrator');
                $exists = $migrator->repositoryExists();
                $pending = $exists ? array_diff(array_keys($migrator->getMigrationFiles(database_path('migrations'))), $migrator->getRepository()->getRan()) : ['initial'];
                $check($exists && ! $pending, 'All database migrations have been applied');
                if ($exists && ! $pending) {
                    $check(User::where('role', User::ROLE_MUNICIPAL_LGU)->exists(), 'Municipal administrator exists');
                    $demoPassword = User::whereIn('email', ['municipal@example.com', 'barangay@example.com', 'resident@example.com'])
                        ->get()->contains(fn ($user) => Hash::check('Password123!', $user->password));
                    $check(! $demoPassword, 'Bundled demo credentials are not active');
                }
            } catch (\Throwable) {
                $check(false, 'Database checks could not complete; review connection and migrations privately');
            }
        }
        if (config('local_ai.enabled') || config('services.migration_prediction.enabled')) {
            $this->warn('Local model services require runtimes on this server; verify host support before enabling them.');
        }
        $this->warn('Also verify domain TLS, private URL denial, password-reset delivery, backup restoration, and real-device GPS/offline behavior on the host.');
        return $failures === 0 && ! $this->option('skip-database') ? self::SUCCESS : self::FAILURE;
    }
}
