# Synced Pattern Popups Release Script
# Automates version bumping, changelog updates, POT generation, and Git tagging
#
# Usage:
#   .\release.ps1 -NewVersion "1.2.1"
#   .\release.ps1 -NewVersion "1.2.1" -ChangelogEntries "* Fixed: Issue description"
#   .\release.ps1 -NewVersion "1.2.1" -SkipPot
#   .\release.ps1 -NewVersion "1.2.1" -SkipTag

param(
    [Parameter(Mandatory=$true)]
    [string]$NewVersion,
    
    [Parameter(Mandatory=$false)]
    [string]$ChangelogEntries = "",
    
    [Parameter(Mandatory=$false)]
    [switch]$SkipPot = $false,
    
    [Parameter(Mandatory=$false)]
    [switch]$SkipTag = $false
)

$ErrorActionPreference = "Continue"

# Get current version from main plugin file
$pluginFile = "sppopups.php"
$currentVersionMatch = Select-String -Path $pluginFile -Pattern "Version:\s*(\d+\.\d+\.\d+)" | Select-Object -First 1
if ($currentVersionMatch) {
    $currentVersion = $currentVersionMatch.Matches.Groups[1].Value
} else {
    Write-Host "Error: Could not find current version in $pluginFile" -ForegroundColor Red
    exit 1
}

Write-Host ""
Write-Host "========================================" -ForegroundColor Cyan
Write-Host "  Synced Pattern Popups Release Script" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""
Write-Host "Current version: $currentVersion" -ForegroundColor Yellow
Write-Host "New version:     $NewVersion" -ForegroundColor Green
Write-Host ""

# Validate version format
if ($NewVersion -notmatch '^\d+\.\d+\.\d+$') {
    Write-Host "Error: Version must be in format X.X.X (e.g., 1.2.1)" -ForegroundColor Red
    exit 1
}

# Confirm before proceeding
$confirmation = Read-Host "Continue with release? (y/N)"
if ($confirmation -ne 'y' -and $confirmation -ne 'Y') {
    Write-Host "Release cancelled." -ForegroundColor Yellow
    exit 0
}

Write-Host ""

# 1. Update version in sppopups.php (header and constant)
Write-Host "1. Updating version in $pluginFile..." -ForegroundColor Yellow
$content = Get-Content $pluginFile -Raw -Encoding UTF8
$content = $content -replace "Version:\s*$currentVersion", "Version: $NewVersion"
$content = $content -replace "define\( 'SPPOPUPS_VERSION', '$currentVersion' \)", "define( 'SPPOPUPS_VERSION', '$NewVersion' )"
Set-Content -Path $pluginFile -Value $content -Encoding UTF8 -NoNewline
Write-Host "   [OK] Updated version in plugin header and constant" -ForegroundColor Green

# 2. Update version in readme.txt (Stable tag)
Write-Host "2. Updating version in readme.txt..." -ForegroundColor Yellow
$readmeFile = "readme.txt"
$readmeContent = Get-Content $readmeFile -Raw -Encoding UTF8
$readmeContent = $readmeContent -replace "Stable tag:\s*$currentVersion", "Stable tag: $NewVersion"
Set-Content -Path $readmeFile -Value $readmeContent -Encoding UTF8 -NoNewline
Write-Host "   [OK] Updated stable tag in readme.txt" -ForegroundColor Green

# 3. Update changelog in readme.txt
Write-Host "3. Updating changelog..." -ForegroundColor Yellow

if ([string]::IsNullOrWhiteSpace($ChangelogEntries)) {
    Write-Host "   No changelog entries provided via -ChangelogEntries parameter." -ForegroundColor Yellow
    Write-Host "   You can:" -ForegroundColor Cyan
    Write-Host "   a) Edit readme.txt manually after this script completes" -ForegroundColor Cyan
    Write-Host "   b) Re-run with -ChangelogEntries parameter" -ForegroundColor Cyan
    Write-Host ""
    Write-Host "   Changelog format example:" -ForegroundColor Cyan
    Write-Host '   -ChangelogEntries "* Fixed: Issue description' -ForegroundColor Gray
    Write-Host '                      * Improved: Feature description"' -ForegroundColor Gray
} else {
    # Insert new changelog entry at the top (after the = NewVersion = line)
    # Find the position after "== Description ==" section
    $newline = [Environment]::NewLine
    $changelogSection = "= $NewVersion =$newline$ChangelogEntries$newline$newline"
    
    # Insert after "== Description ==" section ends (before first changelog entry)
    $replacement = '$1' + $newline + $newline + $changelogSection + '$2'
    if ($readmeContent -match '(== Description ==.*?)(= \d+\.\d+\.\d+ =)') {
        $readmeContent = $readmeContent -replace '(== Description ==.*?)(= \d+\.\d+\.\d+ =)', $replacement
    } else {
        # Fallback: insert after Description section
        $fallbackReplacement = '$1' + $changelogSection
        $readmeContent = $readmeContent -replace '(== Description ==.*?\n\n)', $fallbackReplacement
    }
    
    # Also add to Upgrade Notice section
    $upgradeNotice = "= $NewVersion =$newline" + "Release notes for version $NewVersion.$newline$newline"
    $upgradeReplacement = '$1' + $newline + $newline + $upgradeNotice
    if ($readmeContent -match '(== Upgrade Notice ==)') {
        $readmeContent = $readmeContent -replace '(== Upgrade Notice ==)', $upgradeReplacement
    }
    
    Set-Content -Path $readmeFile -Value $readmeContent -Encoding UTF8 -NoNewline
    Write-Host "   [OK] Added changelog entry" -ForegroundColor Green
}

# 4. Generate POT file
if ($SkipPot) {
    Write-Host "4. Skipping POT generation (--SkipPot flag)" -ForegroundColor Yellow
} else {
    Write-Host "4. Generating POT file..." -ForegroundColor Yellow
    # Try to find wp-cli
    $wpCli = "wp"
    if (Test-Path "..\..\..\wp.ps1") {
        $wpCli = "..\..\..\wp.ps1"
    }
    if (Test-Path "wp.ps1") {
        $wpCli = ".\wp.ps1"
    }
    
    # Run from plugin directory (we're already here)
    $potExitCode = 1
    $null = & $wpCli i18n make-pot . languages/synced-pattern-popups.pot --domain=synced-pattern-popups 2>&1 | Out-Null
    $potExitCode = $LASTEXITCODE
    
    if ($potExitCode -eq 0) {
        Write-Host "   [OK] POT file generated successfully" -ForegroundColor Green
    } else {
        Write-Host "   [WARNING] POT generation may have failed (exit code: $potExitCode)" -ForegroundColor Yellow
        Write-Host "   Please verify: wp i18n make-pot . languages/synced-pattern-popups.pot" -ForegroundColor Cyan
    }
}

# 5. Git operations
Write-Host "5. Preparing Git operations..." -ForegroundColor Yellow

# Check if we're in a git repo
if (-not (Test-Path ".git")) {
    Write-Host "   [WARNING] Not a git repository. Skipping git operations." -ForegroundColor Yellow
    Write-Host ""
    Write-Host "Release preparation complete!" -ForegroundColor Green
    Write-Host "Files modified: $pluginFile, $readmeFile" -ForegroundColor Cyan
    exit 0
}

# Show what will be committed
Write-Host ""
Write-Host "Modified files:" -ForegroundColor Cyan
git status --short sppopups.php readme.txt languages/synced-pattern-popups.pot 2>$null

Write-Host ""
Write-Host "========================================" -ForegroundColor Green
Write-Host "  Release Preparation Complete!" -ForegroundColor Green
Write-Host "========================================" -ForegroundColor Green
Write-Host ""
Write-Host "Next steps:" -ForegroundColor Yellow
Write-Host ""
Write-Host "1. Review the changes:" -ForegroundColor White
Write-Host "   git diff sppopups.php readme.txt languages/synced-pattern-popups.pot" -ForegroundColor Cyan
Write-Host ""
Write-Host "2. Stage and commit the changes:" -ForegroundColor White
Write-Host "   git add sppopups.php readme.txt languages/synced-pattern-popups.pot" -ForegroundColor Cyan
Write-Host "   git commit -m 'Release version $NewVersion'" -ForegroundColor Cyan
Write-Host ""

if (-not $SkipTag) {
    Write-Host "3. Create and push the tag:" -ForegroundColor White
    Write-Host "   git tag -a v$NewVersion -m 'Release version $NewVersion'" -ForegroundColor Cyan
    Write-Host "   git push origin v$NewVersion" -ForegroundColor Cyan
    Write-Host ""
    Write-Host "4. Push everything with ONE command:" -ForegroundColor White
    Write-Host "   git push origin main && git push origin v$NewVersion" -ForegroundColor Green
    Write-Host ""
    Write-Host "   Note: This pushes only the new tag, not all tags." -ForegroundColor Gray
} else {
    Write-Host "3. Create tag manually (--SkipTag flag was used):" -ForegroundColor White
    Write-Host "   git tag -a v$NewVersion -m 'Release version $NewVersion'" -ForegroundColor Cyan
    Write-Host ""
    Write-Host "4. Push everything with ONE command:" -ForegroundColor White
    Write-Host "   git push origin main && git push origin v$NewVersion" -ForegroundColor Green
    Write-Host ""
    Write-Host "   Note: This pushes only the new tag, not all tags." -ForegroundColor Gray
}

Write-Host ""
