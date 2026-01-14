# PowerShell script to fix common PHPCS violations (improved version)
# Fixes: comment punctuation, missing class docblocks, Yoda conditions, BOM, whitespace

$ErrorActionPreference = "Stop"

$files = @(
    "sppopups.php",
    "includes\class-sppopups-abilities.php",
    "includes\class-sppopups-admin.php",
    "includes\class-sppopups-ajax.php",
    "includes\class-sppopups-asset-collector.php",
    "includes\class-sppopups-cache.php",
    "includes\class-sppopups-gallery.php",
    "includes\class-sppopups-pattern.php",
    "includes\class-sppopups-plugin.php",
    "includes\class-sppopups-review-notice.php",
    "includes\class-sppopups-settings.php",
    "includes\class-sppopups-shipped-patterns.php",
    "includes\class-sppopups-tldr.php",
    "includes\class-sppopups-trigger-parser.php"
)

function Remove-BOM {
    param([string]$content)
    # Remove UTF-8 BOM if present
    if ($content.StartsWith([char]0xFEFF)) {
        $content = $content.Substring(1)
    }
    return $content
}

function Remove-TrailingWhitespace {
    param([string]$content)
    # Remove trailing whitespace from each line
    $lines = $content -split "`r?`n"
    $cleaned = $lines | ForEach-Object { $_ -replace '\s+$', '' }
    return $cleaned -join "`n"
}

foreach ($file in $files) {
    if (-not (Test-Path $file)) {
        Write-Host "Skipping $file (not found)" -ForegroundColor Yellow
        continue
    }
    
    Write-Host "Processing $file..." -ForegroundColor Cyan
    # Read file as bytes to avoid BOM issues, then convert to string
    $bytes = [System.IO.File]::ReadAllBytes((Resolve-Path $file))
    # Remove BOM if present
    if ($bytes.Length -ge 3 -and $bytes[0] -eq 0xEF -and $bytes[1] -eq 0xBB -and $bytes[2] -eq 0xBF) {
        $bytes = $bytes[3..($bytes.Length-1)]
    }
    $content = [System.Text.Encoding]::UTF8.GetString($bytes)
    $originalContent = $content
    
    # Fix 1: Remove trailing whitespace
    $content = Remove-TrailingWhitespace $content
    
    # Fix 2: Add periods to inline comments that don't end with punctuation
    # More careful regex to avoid double periods
    $content = $content -replace '(//[^.!?\r\n]+?)(?<!\.)(?<!!)(?<!\?)(\s*)(\r?\n)', '$1.$3$4'
    
    # Fix 3: Fix Yoda conditions - be more careful with context
    # Only fix !== null and === null, not other comparisons
    $content = $content -replace '(\$\w+)\s+!==\s+null\b', 'null !== $1'
    $content = $content -replace '(\$\w+)\s+===\s+null\b', 'null === $1'
    
    # Fix 4: Parameter comment punctuation in @param tags
    $content = $content -replace '(@param\s+\S+\s+\$\w+\s+[^.!?\r\n*]+?)(?<!\.)(?<!!)(?<!\?)(\s*\*)', '$1.$2'
    
    # Fix 5: Fix docblock formatting - ensure proper spacing after asterisk
    $content = $content -replace '(\*\s*)\r?\n(\s*\*)', '$1`n$2'
    
    # Fix 6: Ensure proper blank line after file comment
    $content = $content -replace '(/\*\*.*?\*/)(\s*)(// Exit)', '$1`n`n$3'
    
    # Fix 7: Ensure proper blank line before class docblock
    $content = $content -replace '(}\s+)(/\*\*)', '$1`n$2'
    
    # Normalize line endings to LF
    $content = $content -replace "`r`n", "`n"
    $content = $content -replace "`r", "`n"
    
    # Only write if content changed
    if ($content -ne $originalContent) {
        # Write without BOM
        $utf8NoBom = New-Object System.Text.UTF8Encoding $false
        [System.IO.File]::WriteAllText((Resolve-Path $file), $content, $utf8NoBom)
        Write-Host "  Updated $file" -ForegroundColor Green
    } else {
        Write-Host "  No changes needed for $file" -ForegroundColor Gray
    }
}

Write-Host "`nDone! Run phpcs to verify fixes." -ForegroundColor Green
