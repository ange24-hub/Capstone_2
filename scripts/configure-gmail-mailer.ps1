$ErrorActionPreference = 'Stop'

$projectRoot = Split-Path -Parent $PSScriptRoot
$envPath = Join-Path $projectRoot '.env'

if (-not (Test-Path -LiteralPath $envPath)) {
    throw "The .env file was not found at $envPath"
}

$gmail = (Read-Host 'Gmail address used to send RBIM password reset emails').Trim()
if ($gmail -notmatch '^[^@\s]+@gmail\.com$') {
    throw 'Enter a valid Gmail address ending in @gmail.com.'
}

$securePassword = Read-Host '16-character Google App Password (input is hidden)' -AsSecureString
$passwordPointer = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($securePassword)

try {
    $appPassword = [Runtime.InteropServices.Marshal]::PtrToStringBSTR($passwordPointer) -replace '\s', ''
} finally {
    [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($passwordPointer)
}

if ($appPassword -notmatch '^[A-Za-z0-9]{16}$') {
    throw 'Google App Passwords contain 16 characters. Use an App Password, not the normal Gmail password.'
}

$settings = [ordered]@{
    MAIL_MAILER = 'smtp'
    MAIL_HOST = 'smtp.gmail.com'
    MAIL_PORT = '587'
    MAIL_USERNAME = $gmail
    MAIL_PASSWORD = '"' + $appPassword + '"'
    MAIL_ENCRYPTION = 'tls'
    MAIL_FROM_ADDRESS = $gmail
    MAIL_FROM_NAME = '"RBIM Tomas Oppus"'
}

$lines = [System.Collections.Generic.List[string]](Get-Content -LiteralPath $envPath)
foreach ($setting in $settings.GetEnumerator()) {
    $found = $false
    for ($index = 0; $index -lt $lines.Count; $index++) {
        if ($lines[$index] -match ('^' + [regex]::Escape($setting.Key) + '=')) {
            $lines[$index] = $setting.Key + '=' + $setting.Value
            $found = $true
            break
        }
    }
    if (-not $found) {
        $lines.Add($setting.Key + '=' + $setting.Value)
    }
}

[System.IO.File]::WriteAllLines($envPath, $lines, [System.Text.UTF8Encoding]::new($false))
$appPassword = $null

Push-Location $projectRoot
try {
    & php artisan optimize:clear
    if ($LASTEXITCODE -ne 0) {
        throw 'Laravel could not clear its cached configuration.'
    }
} finally {
    Pop-Location
}

Write-Host 'Gmail SMTP is configured. Submit Forgot Password once, then check Inbox and Spam.' -ForegroundColor Green
