# Synced Pattern Popups Build ZIP Script
# Creates a deployable zip file locally for testing
#
# Usage:
#   .\build-zip.ps1
#   .\build-zip.ps1 -Version "1.3.0"

param(
    [Parameter(Mandatory=$false)]
    [string]$Version = ""
)

$ErrorActionPreference = "Stop"

# Get current version from main plugin file if not provided
$pluginFile = "sppopups.php"
if ([string]::IsNullOrWhiteSpace($Version)) {
    $versionMatch = Select-String -Path $pluginFile -Pattern "Version:\s*(\d+\.\d+\.\d+)" | Select-Object -First 1
    if ($versionMatch) {
        $Version = $versionMatch.Matches.Groups[1].Value
    } else {
        Write-Host "Error: Could not find version in $pluginFile and no version provided" -ForegroundColor Red
        exit 1
    }
}

Write-Host ""
Write-Host "========================================" -ForegroundColor Cyan
Write-Host "  Synced Pattern Popups Build ZIP" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""
Write-Host "Version: $Version" -ForegroundColor Green
Write-Host ""

# Validate version format
if ($Version -notmatch '^\d+\.\d+\.\d+$') {
    Write-Host "Error: Version must be in format X.X.X (e.g., 1.3.0)" -ForegroundColor Red
    exit 1
}

# Set up paths
$pluginDir = Get-Location
$zipName = "sppopups-${Version}.zip"
$zipPath = Join-Path $pluginDir $zipName
$tempDir = Join-Path $env:TEMP "sppopups-release-$(Get-Random)"

# Check if zip already exists
if (Test-Path $zipPath) {
    $response = Read-Host "ZIP file already exists: $zipName. Overwrite? (y/N)"
    if ($response -ne 'y' -and $response -ne 'Y') {
        Write-Host "Build cancelled." -ForegroundColor Yellow
        exit 0
    }
    Remove-Item $zipPath -Force
}

Write-Host "Creating temporary directory..." -ForegroundColor Yellow
$tempPluginDir = Join-Path $tempDir "sppopups"
New-Item -ItemType Directory -Path $tempPluginDir -Force | Out-Null

# Read .distignore file to get exclusion patterns
$distignoreFile = ".distignore"
$excludePatterns = @()

if (Test-Path $distignoreFile) {
    Write-Host "Reading exclusion patterns from .distignore..." -ForegroundColor Yellow
    $distignoreContent = Get-Content $distignoreFile
    
    foreach ($line in $distignoreContent) {
        # Skip comments and empty lines
        $line = $line.Trim()
        if ($line -and -not $line.StartsWith("#")) {
            # Remove leading slash if present (PowerShell paths don't need it)
            $pattern = $line.TrimStart('/')
            $excludePatterns += $pattern
        }
    }
    
    Write-Host "   Found $($excludePatterns.Count) exclusion patterns" -ForegroundColor Green
} else {
    Write-Host "Warning: .distignore file not found. No files will be excluded." -ForegroundColor Yellow
}

# Function to check if a path should be excluded
function Should-ExcludePath {
    param(
        [string]$Path,
        [string[]]$ExcludePatterns
    )
    
    $relativePath = $Path.Replace($pluginDir, "").TrimStart('\', '/')
    
    foreach ($pattern in $ExcludePatterns) {
        # Handle wildcard patterns
        if ($pattern -like "*/*") {
            # Directory pattern
            if ($relativePath -like $pattern -or $relativePath.StartsWith($pattern.TrimEnd('/'))) {
                return $true
            }
        } elseif ($pattern -like "*") {
            # Wildcard file pattern
            $fileName = Split-Path -Leaf $relativePath
            if ($fileName -like $pattern) {
                return $true
            }
        } else {
            # Exact match
            if ($relativePath -eq $pattern -or $relativePath.StartsWith($pattern + "\") -or $relativePath.StartsWith($pattern + "/")) {
                return $true
            }
        }
    }
    
    return $false
}

# Copy files to temp directory, excluding patterns from .distignore
Write-Host "Copying files (excluding patterns from .distignore)..." -ForegroundColor Yellow
$copiedCount = 0
$excludedCount = 0

Get-ChildItem -Path $pluginDir -Recurse -File | ForEach-Object {
    $relativePath = $_.FullName.Replace($pluginDir, "").TrimStart('\', '/')
    $destPath = Join-Path $tempPluginDir $relativePath
    
    if (Should-ExcludePath -Path $_.FullName -ExcludePatterns $excludePatterns) {
        $excludedCount++
        return
    }
    
    # Create destination directory if it doesn't exist
    $destDir = Split-Path -Parent $destPath
    if (-not (Test-Path $destDir)) {
        New-Item -ItemType Directory -Path $destDir -Force | Out-Null
    }
    
    # Copy file
    Copy-Item -Path $_.FullName -Destination $destPath -Force
    $copiedCount++
}

Write-Host "   Copied $copiedCount files" -ForegroundColor Green
Write-Host "   Excluded $excludedCount files" -ForegroundColor Gray

# Verify sppopups.php was copied
$tempPluginFile = Join-Path $tempPluginDir "sppopups.php"
if (-not (Test-Path $tempPluginFile)) {
    Write-Host "Error: sppopups.php was not copied. Build failed." -ForegroundColor Red
    Remove-Item -Path $tempDir -Recurse -Force
    exit 1
}

# Create ZIP file
Write-Host "Creating ZIP file..." -ForegroundColor Yellow
try {
    # Remove existing zip if it exists
    if (Test-Path $zipPath) {
        Remove-Item $zipPath -Force
    }
    
    # Create zip from temp directory (this will include the sppopups/ folder)
    Compress-Archive -Path "$tempDir\*" -DestinationPath $zipPath -Force
    
    # Verify ZIP was created
    if (Test-Path $zipPath) {
        $zipSize = (Get-Item $zipPath).Length
        $zipSizeMB = [math]::Round($zipSize / 1MB, 2)
        Write-Host "   [OK] ZIP file created: $zipName ($zipSizeMB MB)" -ForegroundColor Green
        
        # Verify ZIP structure
        Add-Type -AssemblyName System.IO.Compression.FileSystem
        $zip = [System.IO.Compression.ZipFile]::OpenRead($zipPath)
        $hasSppopupsFolder = $zip.Entries | Where-Object { $_.FullName -like "sppopups/*" } | Select-Object -First 1
        $zip.Dispose()
        
        if ($hasSppopupsFolder) {
            Write-Host "   [OK] ZIP structure verified: contains sppopups/ folder" -ForegroundColor Green
        } else {
            Write-Host "   [WARNING] ZIP structure may be incorrect" -ForegroundColor Yellow
        }
    } else {
        Write-Host "Error: ZIP file was not created" -ForegroundColor Red
        Remove-Item -Path $tempDir -Recurse -Force
        exit 1
    }
} catch {
    Write-Host "Error creating ZIP: $_" -ForegroundColor Red
    Remove-Item -Path $tempDir -Recurse -Force
    exit 1
}

# Clean up temp directory
Write-Host "Cleaning up temporary files..." -ForegroundColor Yellow
Remove-Item -Path $tempDir -Recurse -Force
Write-Host "   [OK] Cleanup complete" -ForegroundColor Green

Write-Host ""
Write-Host "========================================" -ForegroundColor Green
Write-Host "  Build Complete!" -ForegroundColor Green
Write-Host "========================================" -ForegroundColor Green
Write-Host ""
Write-Host "ZIP file created: $zipPath" -ForegroundColor Cyan
Write-Host ""
Write-Host "You can now:" -ForegroundColor Yellow
Write-Host "  1. Test the zip by installing it on a WordPress site" -ForegroundColor White
Write-Host "  2. Upload it to GitHub as a release asset" -ForegroundColor White
Write-Host "  3. Use it for local testing" -ForegroundColor White
Write-Host ""
