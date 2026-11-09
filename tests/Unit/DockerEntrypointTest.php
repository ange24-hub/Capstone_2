<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

class DockerEntrypointTest extends TestCase
{
    public static function startupCases(): array
    {
        return [
            'normal startup' => ['', false, true, true],
            'optional forecast storage failure' => ['migration:prepare-statistical-storage', false, true, true],
            'optional submission storage failure' => ['population:prepare-submission-storage', false, true, true],
            'optional one-time import failure' => ['brbi:sync-release', false, true, true],
            'required config failure' => ['config:cache', false, true, false],
            'required base storage failure' => ['migration:prepare-forecast-storage', false, true, false],
            'explicit full migration failure' => ['migrate --force', true, true, false],
            'missing application key' => ['', false, false, false],
        ];
    }

    #[DataProvider('startupCases')]
    public function test_startup_preserves_required_checks_without_blocking_on_optional_features(
        string $failure, bool $migrations, bool $hasKey, bool $startsServer
    ): void {
        $shell = PHP_OS_FAMILY === 'Windows' ? 'C:/Program Files/Git/bin/bash.exe' : '/bin/sh';
        if (! is_file($shell)) {
            $this->markTestSkipped('A POSIX shell is required to execute the container entrypoint harness.');
        }
        $root = dirname(__DIR__, 2).'/storage/framework/testing';
        $directory = $root.'/entrypoint-'.bin2hex(random_bytes(8));
        $files = new Filesystem;
        $files->makeDirectory($directory.'/bin', 0755, true, true);
        try {
            $script = file_get_contents(dirname(__DIR__, 2).'/docker/entrypoint.sh');
            // Only relocate the container filesystem and replace external processes.
            // The production shell control flow is executed, not simulated in PHP.
            $script = str_replace("cd /var/www/html", 'cd "$(dirname "$0")"'."\n".'PATH="$PWD/bin:$PATH"'."\nexport PATH", $script);
            file_put_contents($directory.'/entrypoint.sh', str_replace("\r\n", "\n", $script));
            $stubs = [
                'chown' => '#!/bin/sh'."\nexit 0\n",
                'gosu' => '#!/bin/sh'."\nshift\n".'exec "$@"'."\n",
                'php' => <<<'SH'
#!/bin/sh
printf '%s\n' "$*" >> startup.log
if [ -n "${FAIL_COMMAND:-}" ]; then
    case "$*" in *"$FAIL_COMMAND"*) exit 42 ;; esac
fi
exit 0
SH,
                'docker-php-entrypoint' => '#!/bin/sh'."\nprintf '%s\\n' web-server-started >> startup.log\nexit 0\n",
            ];
            foreach ($stubs as $name => $stub) {
                file_put_contents($directory.'/bin/'.$name, $stub);
                chmod($directory.'/bin/'.$name, 0755);
            }
            $process = new Process([$shell, './entrypoint.sh', 'apache2-foreground'], $directory, [
                'APP_KEY' => $hasKey ? 'test-placeholder-key' : '',
                'RUN_MIGRATIONS' => $migrations ? 'true' : 'false', 'FAIL_COMMAND' => $failure,
            ]);
            $process->setTimeout(20);
            $process->run();
            $log = is_file($directory.'/startup.log') ? file_get_contents($directory.'/startup.log') : '';
            $this->assertSame($startsServer, str_contains($log, 'web-server-started'), $process->getErrorOutput());
            $this->assertSame($startsServer, $process->isSuccessful(), $process->getErrorOutput());
        } finally {
            $resolved = realpath($directory);
            $expected = realpath($root).DIRECTORY_SEPARATOR.'entrypoint-';
            if ($resolved !== false && str_starts_with($resolved, $expected)) {
                $files->deleteDirectory($resolved);
            }
        }
    }
}
