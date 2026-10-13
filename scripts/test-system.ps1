param([switch]$SkipBuild)
$ErrorActionPreference = 'Stop'
$projectDirectory = Split-Path -Parent $PSScriptRoot
Push-Location -LiteralPath $projectDirectory
try {
    & php -d extension=pdo_sqlite -d extension=gd vendor/phpunit/phpunit/phpunit --log-junit storage/logs/capstone-phpunit.xml
    if ($LASTEXITCODE -ne 0) { throw 'PHP tests failed. See the test output.' }
    & node --test tests/js/offline-household.test.cjs
    if ($LASTEXITCODE -ne 0) { throw 'Offline household tests failed.' }
    $pythonPath = Join-Path $projectDirectory 'ml/.venv/Scripts/python.exe'
    if (Test-Path -LiteralPath $pythonPath) {
        $previousBytecode = $env:PYTHONDONTWRITEBYTECODE
        $env:PYTHONDONTWRITEBYTECODE = '1'
        try {
            & $pythonPath -m unittest ml.test_predictor -v
            if ($LASTEXITCODE -ne 0) { throw 'Migration service tests failed.' }
        } finally { $env:PYTHONDONTWRITEBYTECODE = $previousBytecode }
    } else { Write-Warning 'Migration runtime tests skipped: install ml/requirements.txt in ml/.venv to run them.' }
    if (-not $SkipBuild) {
        & npm.cmd run build
        if ($LASTEXITCODE -ne 0) { throw 'Production frontend build failed.' }
    }
} finally { Pop-Location }
