$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path $PSScriptRoot -Parent
$aiDirectory = Join-Path $projectRoot 'storage/app/local-ai'
$pidPath = Join-Path $aiDirectory 'server.pid'
$runtimePath = Join-Path $aiDirectory 'runtime/ollama.exe'
if (Test-Path -LiteralPath $runtimePath) { $runtimePath = (Resolve-Path -LiteralPath $runtimePath).Path }
if (!(Test-Path -LiteralPath $pidPath)) { Write-Output 'No project AI server PID recorded.'; exit }
$serverId = [int](Get-Content -LiteralPath $pidPath -Raw)
$candidate = Get-Process -Id $serverId -ErrorAction SilentlyContinue
if ($candidate) {
    if ($candidate.Path -ne $runtimePath) { throw 'Recorded PID belongs to a different process; it was not stopped.' }
    $env:OLLAMA_HOST = '127.0.0.1:11434'
    $loadedModels = Invoke-RestMethod -Uri 'http://127.0.0.1:11434/api/ps' -TimeoutSec 3
    foreach ($loaded in $loadedModels.models) { & $runtimePath stop $loaded.name }
    Stop-Process -Id $serverId
}
Remove-Item -LiteralPath $pidPath
Write-Output 'Project local AI server stopped. RBI records are unchanged.'
