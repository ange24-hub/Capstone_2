param(
    [string]$LaragonRoot = 'C:\laragon',
    [string]$PhpSource = 'C:\php-8.5.0'
)
$ErrorActionPreference = 'Stop'
$projectDirectory = Split-Path -Parent $PSScriptRoot
$phpDirectory = Join-Path $LaragonRoot 'bin/php/php-8.5.0-Win32-vs17-x64'
$apacheDirectory = Join-Path $LaragonRoot 'bin/apache/httpd-2.4.54-win64-VS16'
$apacheBinary = Join-Path $apacheDirectory 'bin/httpd.exe'
$apacheConfig = Join-Path $apacheDirectory 'conf/httpd.conf'
$fastCgiModule = Join-Path $LaragonRoot 'etc/apache2/modules/mod_fcgid-2.3.10-win64-VS16.so'
$projectConfig = Join-Path $LaragonRoot 'etc/apache2/sites-enabled/zz-capstone-php.conf'
$utf8 = New-Object System.Text.UTF8Encoding($false)

foreach ($required in @($apacheBinary, $apacheConfig, $fastCgiModule,
    (Join-Path $PhpSource 'php.exe'), (Join-Path $PhpSource 'php8apache2_4.dll'))) {
    if (-not (Test-Path -LiteralPath $required -PathType Leaf)) {
        throw "Required file is missing: $required"
    }
}
$sourceVersion = & (Join-Path $PhpSource 'php.exe') -r 'echo PHP_VERSION;'
if ($LASTEXITCODE -ne 0 -or $sourceVersion -ne '8.5.0') { throw 'Expected the existing PHP 8.5.0 runtime.' }

# Capture only this Laragon Apache instance; leave database processes running.
$apacheProcesses = @(Get-CimInstance Win32_Process -Filter "Name = 'httpd.exe'" |
    Where-Object { $_.ExecutablePath -ieq $apacheBinary })
if ($apacheProcesses | Where-Object { $_.CommandLine -match '\s-n\s|\s-k\s+runservice' }) {
    throw 'Apache is running as a Windows service; use its service manager to switch PHP.'
}

$backupDirectory = Join-Path $projectDirectory ('storage/logs/laragon-php-backup-' + [DateTime]::Now.ToString('yyyyMMdd-HHmmss'))
New-Item -ItemType Directory -Path $backupDirectory | Out-Null
$hadProjectConfig = Test-Path -LiteralPath $projectConfig
if ($hadProjectConfig) {
    Copy-Item -LiteralPath $projectConfig -Destination (Join-Path $backupDirectory 'zz-capstone-php.conf')
}
if (-not (Test-Path -LiteralPath $phpDirectory)) {
    Copy-Item -LiteralPath $PhpSource -Destination $phpDirectory -Recurse
}
$phpBinary = Join-Path $phpDirectory 'php.exe'
$targetVersion = & $phpBinary -r 'echo PHP_VERSION;'
if ($LASTEXITCODE -ne 0 -or $targetVersion -ne '8.5.0') { throw 'Copied runtime verification failed.' }

$phpIniPath = Join-Path $phpDirectory 'php.ini'
$phpIni = [IO.File]::ReadAllText($phpIniPath)
$extensionDirectory = (Join-Path $phpDirectory 'ext').Replace('\', '/')
$phpIni = [regex]::Replace($phpIni, '(?m)^extension_dir\s*=.*$', ('extension_dir = "' + $extensionDirectory + '"'))
foreach ($extension in @('gd', 'pdo_sqlite', 'sqlite3')) {
    $phpIni = [regex]::Replace($phpIni, ('(?m)^;extension=' + $extension + '\s*$'), ('extension=' + $extension))
}
[IO.File]::WriteAllText($phpIniPath, $phpIni, $utf8)
$extensionCheck = Join-Path $backupDirectory 'extensions.php'
[IO.File]::WriteAllText($extensionCheck, @'
<?php
foreach (['pdo_mysql', 'gd', 'pdo_sqlite', 'mbstring', 'openssl', 'curl', 'zip'] as $extension) {
    if (!extension_loaded($extension)) {
        fwrite(STDERR, 'Missing extension: '.$extension.PHP_EOL);
        exit(1);
    }
}
echo 'PHP extensions verified'.PHP_EOL;
'@, $utf8)
& $phpBinary $extensionCheck
if ($LASTEXITCODE -ne 0) { throw 'Required PHP extensions failed to load.' }

$phpPath = $phpDirectory.Replace('\', '/')
$publicDirectory = (Join-Path $projectDirectory 'public').Replace('\', '/')
$fastCgiPath = $fastCgiModule.Replace('\', '/')
$projectText = @"
# Capstone_2 uses a separate PHP process to avoid Apache/PHP DLL collisions.
# This file is deliberately outside Laragon's auto.* virtual host files.
<IfModule !fcgid_module>
    LoadModule fcgid_module "$fastCgiPath"
</IfModule>
<IfModule fcgid_module>
    FcgidInitialEnv PHPRC "$phpPath"
    FcgidMaxRequestLen 16777216
    FcgidIOTimeout 120
    <Directory "$publicDirectory">
        Options +ExecCGI
        <FilesMatch "\.php$">
            SetHandler fcgid-script
        </FilesMatch>
        FcgidWrapper "$phpPath/php-cgi.exe" .php
    </Directory>
</IfModule>
"@

function Start-LaragonApache {
    Start-Process -FilePath $apacheBinary -ArgumentList @('-d', ('"' + $apacheDirectory + '"'), '-f', ('"' + $apacheConfig + '"')) -WorkingDirectory $apacheDirectory -WindowStyle Hidden | Out-Null
}

try {
    [IO.File]::WriteAllText($projectConfig, $projectText, $utf8)
    & $apacheBinary -d $apacheDirectory -f $apacheConfig -t
    if ($LASTEXITCODE -ne 0) { throw 'Apache configuration check failed.' }
    foreach ($apacheProcess in $apacheProcesses) {
        Stop-Process -Id $apacheProcess.ProcessId -Force -ErrorAction SilentlyContinue
    }
    if ($apacheProcesses.Count -gt 0) { Start-Sleep -Seconds 2 }
    Start-LaragonApache
    Start-Sleep -Seconds 3
    $response = Invoke-WebRequest -UseBasicParsing -Uri 'http://127.0.0.1/login' -Headers @{ Host = 'Capstone_2.test' } -TimeoutSec 20
    if ($response.StatusCode -ne 200 -or $response.Content -match 'Composer detected issues|Fatal error') {
        throw 'The application login check failed after the PHP switch.'
    }
    Write-Output 'PASS: Apache serves the application login through PHP 8.5.0 FastCGI.'
    Write-Output "Project configuration backup directory: $backupDirectory"
} catch {
    if ($hadProjectConfig) {
        Copy-Item -LiteralPath (Join-Path $backupDirectory 'zz-capstone-php.conf') -Destination $projectConfig -Force
    } else {
        # Leave an empty disabled file rather than deleting an external path.
        [IO.File]::WriteAllText($projectConfig, '# Disabled after failed project PHP verification.', $utf8)
    }
    Get-CimInstance Win32_Process -Filter "Name = 'httpd.exe'" |
        Where-Object { $_.ExecutablePath -ieq $apacheBinary } |
        ForEach-Object { Stop-Process -Id $_.ProcessId -Force -ErrorAction SilentlyContinue }
    if ($apacheProcesses.Count -gt 0) { Start-LaragonApache }
    throw
}
