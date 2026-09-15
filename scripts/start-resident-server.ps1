param([ValidateRange(1024,65535)][int]$Port = 8000)
$ErrorActionPreference = 'Stop'
$projectDirectory = Split-Path -Parent $PSScriptRoot
Set-Location -LiteralPath $projectDirectory
Write-Host 'Keep Laragon/MySQL running and connect the phone to the same trusted Wi-Fi.'
Write-Host ('In the app, use http://YOUR-PC-WIFI-IP:' + $Port)
Write-Host 'Find your Wi-Fi IPv4 address below. Press Ctrl+C to stop the server.'
ipconfig.exe
Push-Location -LiteralPath (Join-Path $projectDirectory 'public')
try {
    php -d upload_max_filesize=6M -d post_max_size=8M -S "0.0.0.0:$Port" ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php
} finally { Pop-Location }
