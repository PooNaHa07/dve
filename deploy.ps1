# ═══════════════════════════════════════════════════════
#  DVE System — Deployment Script (Windows XAMPP)
#  Usage: .\deploy.ps1 [-DryRun] [-SkipDB] [-SkipFiles]
# ═══════════════════════════════════════════════════════

param(
    [switch]$DryRun = $false,
    [switch]$SkipDB = $false,
    [switch]$SkipFiles = $false,
    [string]$ServerPath = "",
    [string]$ServerUrl = ""
)

# Configuration settings
$SOURCE      = $PSScriptRoot
$DESTINATION = if ($ServerPath) { $ServerPath } else { "\\Win-9b8qj41p598\dve_data_full" }
$DB_SYNC_URL = if ($ServerUrl)  { $ServerUrl }  else { "http://202.29.236.132/dve_data_full/db/sync_db_structure.php" }

# List of folders/files to exclude from syncing
$EXCLUDES = @(
    "db\backups",
    "uploads\temp",
    "logs",
    "scratch",
    "tests",
    ".git",
    ".agents",
    "*.bak",
    "deploy.ps1",
    "includes\configdb.local.php"
)

Write-Host ""
Write-Host "=========================================================" -ForegroundColor Cyan
Write-Host "        DVE System -- Automated Deploy Engine            " -ForegroundColor Cyan
Write-Host "=========================================================" -ForegroundColor Cyan
Write-Host ""

if ($DryRun) {
    Write-Host "[WARNING] DRY-RUN MODE: Previewing changes only (No files will be copied)" -ForegroundColor Yellow
    Write-Host ""
}

# Step 1: Git Commit (Optional)
if (Test-Path "$SOURCE\.git") {
    Write-Host "[ 1/3 ] Git Commit Record..." -ForegroundColor Blue
    $commitMsg = ""
    try {
        if (-not $env:NON_INTERACTIVE) {
            $commitMsg = Read-Host "Enter Git commit message (Press Enter to skip)"
        }
    } catch {}

    if ($commitMsg -and $commitMsg.Trim() -ne "") {
        git -C $SOURCE add -A
        git -C $SOURCE commit -m $commitMsg
        Write-Host "[SUCCESS] Committed: $commitMsg" -ForegroundColor Green
    } else {
        Write-Host "[SKIP] Skipping Git Commit step" -ForegroundColor Gray
    }
} else {
    Write-Host "[ 1/3 ] [SKIP] Git repository not initialized" -ForegroundColor Gray
}

# Step 2: Robocopy Sync
if (-not $SkipFiles) {
    Write-Host ""
    Write-Host "[ 2/3 ] Syncing updated files to Server..." -ForegroundColor Blue
    Write-Host "        Source:      $SOURCE" -ForegroundColor Gray
    Write-Host "        Destination: $DESTINATION" -ForegroundColor Gray

    $robocopyArgs = @($SOURCE, $DESTINATION, "/MIR", "/Z", "/W:2", "/R:2", "/NP")
    foreach ($ex in $EXCLUDES) {
        $robocopyArgs += "/XD"
        $robocopyArgs += $ex
    }

    if ($DryRun) {
        $robocopyArgs += "/L"
    }

    $robocopyOutput = & robocopy @robocopyArgs

    if ($LASTEXITCODE -le 7) {
        Write-Host "[SUCCESS] File sync to server completed!" -ForegroundColor Green
    } else {
        Write-Host "[ERROR] Robocopy sync error (Exit Code: $LASTEXITCODE)" -ForegroundColor Red
        if ($robocopyOutput) {
            $robocopyOutput | Where-Object { $_ -match "ERROR" -or $_ -match "Access" -or $_ -match "System error" -or $_ -match "0x" } | ForEach-Object { Write-Host "   $_" -ForegroundColor Yellow }
        }
        Write-Host ""
        Write-Host "[TIP] Run this command once in PowerShell to log into Server share:" -ForegroundColor Cyan
        Write-Host "      net use $DESTINATION /user:Administrator" -ForegroundColor Yellow
    }
} else {
    Write-Host "[SKIP] Skipping file sync step" -ForegroundColor Gray
}

# Step 3: Trigger Smart Database Engine
if (-not $SkipDB) {
    Write-Host ""
    Write-Host "[ 3/3 ] Syncing Database structure..." -ForegroundColor Blue

    if (-not $DryRun) {
        try {
            $syncUri = "$DB_SYNC_URL?ajax=1&action=schema_sync&dry_run=0"
            $res = Invoke-WebRequest -Uri $syncUri -UseBasicParsing -TimeoutSec 30
            Write-Host "[SUCCESS] Database structure sync complete!" -ForegroundColor Green
        } catch {
            Write-Host "[WARNING] Unable to reach DB Sync URL ($DB_SYNC_URL)" -ForegroundColor Yellow
            Write-Host "[TIP] You can open sync_db_structure.php directly in browser to sync DB" -ForegroundColor Gray
        }
    } else {
        Write-Host "[SKIP] Skipping DB trigger in Dry-Run mode" -ForegroundColor Gray
    }
}

Write-Host ""
Write-Host "=========================================================" -ForegroundColor Cyan
Write-Host " [FINISHED] Deployment process complete!" -ForegroundColor Green
Write-Host "=========================================================" -ForegroundColor Cyan
Write-Host ""