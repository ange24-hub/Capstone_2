param([switch]$CheckOnly)

$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path $PSScriptRoot -Parent
$aiDirectory = Join-Path $projectRoot 'storage/app/local-ai'
$runtimePath = Join-Path $aiDirectory 'runtime/ollama.exe'
$pidPath = Join-Path $aiDirectory 'server.pid'

if (!(Test-Path -LiteralPath $runtimePath)) {
    throw 'Local AI runtime is not installed in storage/app/local-ai/runtime yet.'
}
$runtimePath = (Resolve-Path -LiteralPath $runtimePath).Path

# Keep resident report processing on this machine. Never expose Ollama to the LAN.
$env:OLLAMA_HOST = '127.0.0.1:11434'
$env:OLLAMA_NO_CLOUD = '1'
$env:OLLAMA_MODELS = Join-Path $aiDirectory 'models'
$env:OLLAMA_NUM_PARALLEL = '1'
$env:OLLAMA_MAX_LOADED_MODELS = '1'
$env:OLLAMA_CONTEXT_LENGTH = '2048'
$env:OLLAMA_KEEP_ALIVE = '1m'
$env:OLLAMA_DEBUG_LOG_REQUESTS = 'false'

$ownedProcess = $null
if (Test-Path -LiteralPath $pidPath) {
    $serverId = [int](Get-Content -LiteralPath $pidPath -Raw)
    $candidate = Get-Process -Id $serverId -ErrorAction SilentlyContinue
    if ($candidate -and $candidate.Path -eq $runtimePath) { $ownedProcess = $candidate }
}
if (!$ownedProcess) {
    if ($CheckOnly) { throw 'The project local AI server is not running.' }
    $portProbe = New-Object System.Net.Sockets.TcpListener([System.Net.IPAddress]::Loopback, 11434)
    try { $portProbe.Start() }
    catch { throw 'Port 11434 is unavailable. Review that port before starting this project server.' }
    finally { $portProbe.Stop() }
    $ownedProcess = Start-Process -FilePath $runtimePath -ArgumentList 'serve' -WorkingDirectory $aiDirectory -WindowStyle Hidden -PassThru `
        -RedirectStandardOutput (Join-Path $aiDirectory 'server.stdout.log') `
        -RedirectStandardError (Join-Path $aiDirectory 'server.stderr.log')
    Set-Content -LiteralPath $pidPath -Value $ownedProcess.Id
}

$ready = $false
for ($attempt = 0; $attempt -lt 10; $attempt++) {
    try {
        $tags = Invoke-RestMethod -Uri 'http://127.0.0.1:11434/api/tags' -TimeoutSec 2
        $ready = $true
        break
    } catch { Start-Sleep -Seconds 1 }
}
if (!$ready) { throw 'Local AI did not become ready. Inspect storage/app/local-ai/server.stderr.log.' }
Write-Output 'Local-only AI server ready at http://127.0.0.1:11434'
$tags.models | Select-Object name, size
