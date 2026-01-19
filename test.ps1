# Synced Pattern Popups Test Script
# Runs PHP and JavaScript tests locally before pushing to GitHub
#
# Usage:
#   .\test.ps1              # Run all tests
#   .\test.ps1 -PHPOnly     # Run only PHP tests
#   .\test.ps1 -JSOnly      # Run only JavaScript tests

param(
    [Parameter(Mandatory=$false)]
    [switch]$PHPOnly = $false,
    
    [Parameter(Mandatory=$false)]
    [switch]$JSOnly = $false
)

$ErrorActionPreference = "Continue"

Write-Host ""
Write-Host "========================================" -ForegroundColor Cyan
Write-Host "  Synced Pattern Popups Test Suite" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

$allTestsPassed = $true

# Run PHP Tests
if (-not $JSOnly) {
    Write-Host "Running PHP Tests..." -ForegroundColor Yellow
    Write-Host "----------------------------------------" -ForegroundColor Gray
    
    # Always run composer install to match GitHub Actions behavior
    # This catches lock file issues and ensures dependencies are up to date
    Write-Host "Installing Composer dependencies (matching GitHub Actions)..." -ForegroundColor Yellow
    composer install --no-interaction --prefer-dist
    if ($LASTEXITCODE -ne 0) {
        Write-Host "ERROR: Failed to install Composer dependencies" -ForegroundColor Red
        Write-Host "This matches what GitHub Actions will do - fix this before committing!" -ForegroundColor Yellow
        $allTestsPassed = $false
        if ($PHPOnly) {
            exit 1
        }
    }
    
    # Run PHPUnit tests (using same command as GitHub Actions)
    Write-Host "Running PHPUnit (matching GitHub Actions command)..." -ForegroundColor Cyan
    php vendor/bin/phpunit
    
    if ($LASTEXITCODE -ne 0) {
        Write-Host ""
        Write-Host "PHP TESTS FAILED" -ForegroundColor Red
        $allTestsPassed = $false
        if ($PHPOnly) {
            exit 1
        }
    } else {
        Write-Host ""
        Write-Host "PHP Tests: PASSED" -ForegroundColor Green
    }
    Write-Host ""
}

# Run JavaScript Tests
if (-not $PHPOnly) {
    Write-Host "Running JavaScript Tests..." -ForegroundColor Yellow
    Write-Host "----------------------------------------" -ForegroundColor Gray
    
    # Always run npm install to match GitHub Actions behavior
    Write-Host "Installing npm dependencies (matching GitHub Actions)..." -ForegroundColor Yellow
    npm install
    if ($LASTEXITCODE -ne 0) {
        Write-Host "ERROR: Failed to install npm dependencies" -ForegroundColor Red
        Write-Host "This matches what GitHub Actions will do - fix this before committing!" -ForegroundColor Yellow
        $allTestsPassed = $false
        if ($JSOnly) {
            exit 1
        }
    }
    
    # Run Jest tests (using same command as GitHub Actions)
    Write-Host "Running Jest (matching GitHub Actions command)..." -ForegroundColor Cyan
    npm run test:js
    
    if ($LASTEXITCODE -ne 0) {
        Write-Host ""
        Write-Host "JAVASCRIPT TESTS FAILED" -ForegroundColor Red
        $allTestsPassed = $false
        if ($JSOnly) {
            exit 1
        }
    } else {
        Write-Host ""
        Write-Host "JavaScript Tests: PASSED" -ForegroundColor Green
    }
    Write-Host ""
}

# Summary
Write-Host "========================================" -ForegroundColor Cyan
if ($allTestsPassed) {
    Write-Host "  ALL TESTS PASSED" -ForegroundColor Green
    Write-Host "========================================" -ForegroundColor Cyan
    Write-Host ""
    Write-Host "Ready to commit and push!" -ForegroundColor Green
    exit 0
} else {
    Write-Host "  SOME TESTS FAILED" -ForegroundColor Red
    Write-Host "========================================" -ForegroundColor Cyan
    Write-Host ""
    Write-Host "Please fix the failing tests before pushing to GitHub." -ForegroundColor Yellow
    exit 1
}
