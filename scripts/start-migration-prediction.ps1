param([switch]$CheckOnly)

$ErrorActionPreference = 'Stop'
$predictionRoot = Split-Path -Parent $PSScriptRoot
$predictionUrl = 'http://127.0.0.1:5001/health'
$predictionLogs = Join-Path $predictionRoot 'storage/app/migration-prediction'

function Test-PredictionReady {
    try {
        $health = Invoke-RestMethod -Uri $predictionUrl -TimeoutSec 2
        return $health.status -eq 'online' -and $health.model -eq 'RBIM Out-Migration Gradient Boosting'
    } catch { return $false }
}

if (Test-PredictionReady) {
    Write-Output 'Migration prediction ready at http://127.0.0.1:5001'
    exit 0
}
if ($CheckOnly) { throw 'Migration prediction is offline. Run scripts/start-migration-prediction.ps1.' }

$predictionPython = Join-Path $predictionRoot 'ml/.venv/Scripts/python.exe'
if ($env:RBIM_PREDICTION_PYTHON) { $predictionPython = $env:RBIM_PREDICTION_PYTHON }
if (!(Test-Path -LiteralPath $predictionPython)) {
    $predictionPython = (Get-Command python -ErrorAction Stop).Source
}

# Check compatibility before opening the serialized model.
& $predictionPython -c "import flask, pandas, joblib, sklearn; assert sklearn.__version__ == '1.6.1', 'The bundled model requires scikit-learn 1.6.1'"
if ($LASTEXITCODE -ne 0) {
    throw 'Install prediction dependencies: python -m venv ml/.venv, then ml/.venv/Scripts/python.exe -m pip install -r ml/requirements.txt.'
}

$predictionPort = New-Object System.Net.Sockets.TcpListener([System.Net.IPAddress]::Loopback, 5001)
try { $predictionPort.Start() }
catch { throw 'Port 5001 is occupied by another service. Review it before starting migration prediction.' }
finally { $predictionPort.Stop() }

New-Item -ItemType Directory -Path $predictionLogs -Force | Out-Null
$predictionScript = Join-Path $predictionRoot 'ml/predictor.py'
$predictionProcess = Start-Process -FilePath $predictionPython -ArgumentList ('-u "' + $predictionScript + '"') `
    -WorkingDirectory $predictionRoot -WindowStyle Hidden -PassThru `
    -RedirectStandardOutput (Join-Path $predictionLogs 'server.stdout.log') `
    -RedirectStandardError (Join-Path $predictionLogs 'server.stderr.log')

for ($predictionAttempt = 0; $predictionAttempt -lt 15; $predictionAttempt++) {
    if (Test-PredictionReady) {
        Write-Output 'Migration prediction ready at http://127.0.0.1:5001'
        exit 0
    }
    $predictionProcess.Refresh()
    if ($predictionProcess.HasExited) { break }
    Start-Sleep -Seconds 1
}
throw 'Migration prediction failed to start. Inspect storage/app/migration-prediction/server.stderr.log.'
