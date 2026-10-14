param(
    [Parameter(Mandatory = $true)][string]$SourceDump
)

$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path -Parent $PSScriptRoot
$source = (Resolve-Path -LiteralPath $SourceDump).Path
if ([IO.Path]::GetExtension($source) -ne '.sql') { throw 'Select a .sql backup.' }
$lines = Get-Content -LiteralPath $source -Encoding UTF8
$counts = [ordered]@{}
$currentTable = $null
$dumpMigrations = @()

# This reviews the line-based phpMyAdmin export format without executing SQL
# or printing row contents, identities, password hashes, or reset tokens.
foreach ($line in $lines) {
    if ($line -match '^\s*(?:USE\s|CREATE\s+DATABASE|DROP\s|GRANT\s|REVOKE\s|LOAD\s|DELIMITER\s|CREATE\s+(?:TRIGGER|FUNCTION|PROCEDURE|VIEW)\s)') {
        throw 'This backup includes commands requiring a separate database review.'
    }
    if ($line -match '^CREATE TABLE `([A-Za-z0-9_]+)`') {
        $counts[$matches[1]] = 0
    }
    if ($line -match '^INSERT INTO `([A-Za-z0-9_]+)`') {
        $currentTable = $matches[1]
        if ($line -notmatch 'VALUES\s*$' -or -not $counts.Contains($currentTable)) {
            throw 'This backup uses an insert format not supported by this reviewer.'
        }
        continue
    }
    if ($currentTable) {
        if ($line -match '^\(') {
            $counts[$currentTable]++
            if ($currentTable -eq 'migrations' -and $line -match "^\(\d+, '([a-zA-Z0-9_]+)', \d+\)") {
                $dumpMigrations += $matches[1]
            }
            if ($line.TrimEnd().EndsWith(';')) { $currentTable = $null }
        } elseif ($line.Trim() -ne '') {
            throw 'Unexpected multiline insert. Review the backup before preparing it.'
        }
    }
}
if ($currentTable -or -not $counts.Contains('users') -or -not $counts.Contains('migrations')) {
    throw 'Incomplete application backup: users/migrations or the end of an insert is missing.'
}
if (-not ($lines | Where-Object { $_.Trim() -eq 'COMMIT;' })) { throw 'The SQL backup has no final COMMIT.' }

$transferRoot = Join-Path $projectRoot 'storage/app/hosting-transfer'
$bundle = Join-Path $transferRoot ('rbim-transfer-' + [guid]::NewGuid().ToString('N').Substring(0, 8))
New-Item -ItemType Directory -Path $bundle -Force | Out-Null
$preparedSql = Join-Path $bundle 'database.sql'
Copy-Item -LiteralPath $source -Destination $preparedSql
$originalHash = (Get-FileHash -LiteralPath $source -Algorithm SHA256).Hash
if ($originalHash -ne (Get-FileHash -LiteralPath $preparedSql -Algorithm SHA256).Hash) { throw 'Backup copy hash mismatch.' }

$uploadRoot = Join-Path $bundle 'storage-app'
New-Item -ItemType Directory -Path $uploadRoot -Force | Out-Null
$includedDirectories = @()
$uploadFiles = 0
$uploadBytes = 0L
foreach ($directory in @('public', 'rbi-signatures', 'barangay-gcash-qr', 'gcash-payment-proofs', 'concern-attachments')) {
    $path = Join-Path (Join-Path $projectRoot 'storage/app') $directory
    if (-not (Test-Path -LiteralPath $path -PathType Container)) { continue }
    $files = @(Get-ChildItem -LiteralPath $path -Recurse -File | Where-Object { $_.Name -ne '.gitignore' })
    if ($files.Count -eq 0) { continue }
    foreach ($file in $files) {
        if ($file.Attributes -band [IO.FileAttributes]::ReparsePoint) { throw 'Review symbolic links in uploads before transfer.' }
        $relative = $file.FullName.Substring($path.Length).TrimStart('\')
        $destination = Join-Path (Join-Path $uploadRoot $directory) $relative
        New-Item -ItemType Directory -Path (Split-Path -Parent $destination) -Force | Out-Null
        Copy-Item -LiteralPath $file.FullName -Destination $destination
        $uploadFiles++
        $uploadBytes += $file.Length
    }
    $includedDirectories += $directory
}

$currentMigrations = @(Get-ChildItem -LiteralPath (Join-Path $projectRoot 'database/migrations') -Filter '*.php' | ForEach-Object { $_.BaseName })
$pending = @($currentMigrations | Where-Object { $_ -notin $dumpMigrations })
$referencedUploads = @([regex]::Matches(($lines -join "`n"), '(?:rbi-signatures|barangay-gcash-qr|gcash-payment-proofs|concern-attachments)/[A-Za-z0-9_./-]+') | ForEach-Object { $_.Value } | Sort-Object -Unique)
$missingUploads = [ordered]@{}
foreach ($relative in $referencedUploads) {
    if (-not (Test-Path -LiteralPath (Join-Path (Join-Path $projectRoot 'storage/app') $relative) -PathType Leaf)) {
        $directory = $relative.Split('/')[0]
        if (-not $missingUploads.Contains($directory)) { $missingUploads[$directory] = 0 }
        $missingUploads[$directory]++
    }
}
$manifest = [ordered]@{
    database_file = 'database.sql'
    database_bytes = (Get-Item -LiteralPath $preparedSql).Length
    database_sha256 = $originalHash.ToLowerInvariant()
    table_row_counts = $counts
    migration_rows_reviewed = $dumpMigrations.Count
    migrations_not_in_backup = $pending
    uploads_included_directories = $includedDirectories
    uploads_included_files = $uploadFiles
    uploads_included_bytes = $uploadBytes
    referenced_upload_paths = $referencedUploads.Count
    missing_referenced_uploads_by_directory = $missingUploads
    verification = 'Static export review and copy checksum only; not yet imported or tested on MySQL or the hosting server.'
}
$manifest | ConvertTo-Json -Depth 6 | Set-Content -LiteralPath (Join-Path $bundle 'manifest.json') -Encoding UTF8

@'
RBIM HOSTING TRANSFER

database.sql is an unchanged copy of the supplied phpMyAdmin export.
manifest.json contains counts and its SHA-256 checksum, without user row data.
storage-app contains available signatures, QR images, receipts, concern attachments,
and public uploads. Browser profiles, local tooling, .env files and credentials
are excluded. Missing source files cannot be reconstructed from SQL alone.

Restore plan (requires access to your existing VPS/Dokploy deployment):
1. Transfer this package privately to the VPS, outside any public web directory.
2. Inspect the app/database container names, actual configured database, user count,
   and persistent volumes. Back up the existing hosted database and uploads.
3. Restore database.sql into a NEW, EMPTY staging database (for example
   sbispgm_restored). This export creates tables: importing into the already
   migrated/seeded live database will fail with "table already exists".
4. Verify row counts against manifest.json and grant the app database user access
   to the restored database. Keep the previous database for rollback.
5. Copy storage-app contents to the app's mounted /var/www/html/storage/app,
   preserving any existing files and restoring ownership to www-data.
6. Set Dokploy DB_DATABASE to the verified restored database, retain the source
   APP_KEY, and redeploy. Run pending migrations and deployment checks as www-data.
7. Check an existing user's login and approval status, record counts, and downloads.

This package has NOT been uploaded or imported into hosting yet.
Do not push this package to Git or place it under public/.
'@ | Set-Content -LiteralPath (Join-Path $bundle 'RESTORE.txt') -Encoding UTF8

$archive = $bundle + '.zip'
Add-Type -AssemblyName System.IO.Compression
Add-Type -AssemblyName System.IO.Compression.FileSystem
$zip = [IO.Compression.ZipFile]::Open($archive, [IO.Compression.ZipArchiveMode]::Create)
try {
    foreach ($file in Get-ChildItem -LiteralPath $bundle -Recurse -File) {
        # Forward slashes make this archive portable to the Linux VPS.
        $relative = $file.FullName.Substring($bundle.Length).TrimStart('\').Replace('\', '/')
        [IO.Compression.ZipFileExtensions]::CreateEntryFromFile($zip, $file.FullName, $relative, [IO.Compression.CompressionLevel]::Optimal) | Out-Null
    }
} finally { $zip.Dispose() }
$manifest | ConvertTo-Json -Depth 6
Write-Output ('Prepared private package: ' + $archive)
