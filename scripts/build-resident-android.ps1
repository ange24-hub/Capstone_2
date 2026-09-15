$ErrorActionPreference = 'Stop'
$projectDirectory = Split-Path -Parent $PSScriptRoot
$toolchainDirectory = Join-Path $projectDirectory 'storage/app/android-toolchain'
$portableJava = Get-ChildItem -LiteralPath (Join-Path $toolchainDirectory 'jdk') -Directory -ErrorAction SilentlyContinue | Select-Object -First 1
if ($portableJava) { $env:JAVA_HOME = $portableJava.FullName }
if (-not $env:JAVA_HOME) { throw 'Install JDK 17+ or set JAVA_HOME before building.' }
$env:GRADLE_USER_HOME = Join-Path $toolchainDirectory 'gradle'
$env:ANDROID_USER_HOME = Join-Path $toolchainDirectory 'user'
Push-Location -LiteralPath (Join-Path $projectDirectory 'android-resident')
try {
    $ErrorActionPreference = 'Continue'
    & .\gradlew.bat assembleDebug lintDebug testDebugUnitTest --no-daemon --console=plain
    $buildExit = $LASTEXITCODE
    $ErrorActionPreference = 'Stop'
    if ($buildExit -ne 0) { throw 'Android build or checks failed. Review the Gradle output.' }
    $downloadDirectory = Join-Path $projectDirectory 'public/downloads'
    New-Item -ItemType Directory -Force -Path $downloadDirectory | Out-Null
    Copy-Item -LiteralPath 'app/build/outputs/apk/debug/app-debug.apk' -Destination (Join-Path $downloadDirectory 'rbim-resident-debug.apk')
    Write-Host ('APK ready: ' + (Join-Path $downloadDirectory 'rbim-resident-debug.apk'))
} finally { Pop-Location }
