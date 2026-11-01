# =============================================================================
# Windows Native Database Backup Script (scripts/backup/backup-db.ps1)
# Employee HRIS & Leave Management System
# =============================================================================

$ErrorActionPreference = "Stop"

$scriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$projectRoot = (Resolve-Path "$scriptDir\..\..").Path
Set-Location $projectRoot

$backupDir = "$projectRoot\backups"
$logDir = "$projectRoot\storage\logs"

if (-not (Test-Path $backupDir)) {
    New-Item -ItemType Directory -Path $backupDir -Force | Out-Null
}
if (-not (Test-Path $logDir)) {
    New-Item -ItemType Directory -Path $logDir -Force | Out-Null
}

function Write-Log {
    param([string]$message, [bool]$isError = $false)
    $ts = [DateTime]::UtcNow.ToString("yyyy-MM-dd HH:mm:ssZ")
    $logLine = "[$ts] $message"
    if ($isError) {
        [Console]::Error.WriteLine($logLine)
    } else {
        [Console]::Out.WriteLine($logLine)
    }
}

Write-Log "Starting HRIS database backup routine (Windows host)..."

$dbContainer = if ($env:DB_CONTAINER) { $env:DB_CONTAINER } else { "hris_db" }
$dbName = if ($env:DB_NAME) { $env:DB_NAME } else { "hris_db" }
$retentionDays = if ($env:RETENTION_DAYS) { [int]$env:RETENTION_DAYS } else { 14 }

# Check container health
$running = docker ps --filter "name=^/$dbContainer$" --format "{{.Names}}"
if ($running -ne $dbContainer) {
    Write-Log "ERROR: Target database container '$dbContainer' is not running!" -isError $true
    exit 1
}

# Fetch root password securely
$rootPass = docker exec $dbContainer printenv MYSQL_ROOT_PASSWORD
if (-not $rootPass) {
    Write-Log "ERROR: MYSQL_ROOT_PASSWORD is not set or accessible in container." -isError $true
    exit 1
}

$timestamp = [DateTime]::UtcNow.ToString("yyyyMMdd_HHmmss")
$sqlFile = "$backupDir\hris_db_$timestamp.sql"
$gzFile = "$backupDir\hris_db_$timestamp.sql.gz"
$shaFile = "$gzFile.sha256"

try {
    Write-Log "Executing mysqldump on $dbName inside container $dbContainer..."
    
    # Execute mysqldump
    $dumpCmd = "docker exec $dbContainer mysqldump --single-transaction --quick --routines --triggers -u root -p$rootPass $dbName"
    Invoke-Expression $dumpCmd | Out-File -FilePath $sqlFile -Encoding utf8

    if (-not (Test-Path $sqlFile) -or (Get-Item $sqlFile).Length -eq 0) {
        throw "Dump failed or generated 0-byte file."
    }

    Write-Log "Compressing dump to GZIP archive..."
    $inStream = [System.IO.File]::OpenRead($sqlFile)
    $outStream = [System.IO.File]::Create($gzFile)
    $gzipStream = New-Object System.IO.Compression.GZipStream($outStream, [System.IO.Compression.CompressionMode]::Compress)
    $inStream.CopyTo($gzipStream)
    $gzipStream.Close()
    $outStream.Close()
    $inStream.Close()

    # Remove uncompressed raw SQL
    Remove-Item -Path $sqlFile -Force

    # Generate SHA-256 Checksum using native .NET (works across all PowerShell versions)
    $hasher = [System.Security.Cryptography.SHA256]::Create()
    $fileStream = [System.IO.File]::OpenRead($gzFile)
    $hashBytes = $hasher.ComputeHash($fileStream)
    $fileStream.Close()
    $hash = [System.BitConverter]::ToString($hashBytes).Replace("-", "").ToLower()
    $fileName = Split-Path -Leaf $gzFile
    "$hash  $fileName" | Out-File -FilePath $shaFile -Encoding utf8

    $sizeMb = [Math]::Round((Get-Item $gzFile).Length / 1MB, 2)
    Write-Log "Backup completed successfully: $fileName (${sizeMb} MB)"
    Write-Log "SHA-256 Checksum: $hash"

    # Prune old backups past retention
    Write-Log "Pruning backups older than $retentionDays days..."
    $cutoff = (Get-Date).AddDays(-$retentionDays)
    Get-ChildItem -Path $backupDir -Filter "hris_db_*.sql.gz*" | Where-Object { $_.LastWriteTime -lt $cutoff } | ForEach-Object {
        Remove-Item $_.FullName -Force
        Write-Log "Pruned expired backup: $($_.Name)"
    }

    Write-Log "Backup routine finished successfully."
    exit 0
} catch {
    Write-Log "ERROR during backup: $_" -isError $true
    if (Test-Path $sqlFile) { Remove-Item $sqlFile -Force }
    exit 1
}
