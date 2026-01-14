# PowerShell script to fix common PHPCS violations
# Fixes: comment punctuation, missing class docblocks, Yoda conditions, parameter comments

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

foreach ($file in $files) {
    if (-not (Test-Path $file)) {
        Write-Host "Skipping $file (not found)" -ForegroundColor Yellow
        continue
    }
    
    Write-Host "Processing $file..." -ForegroundColor Cyan
    $content = Get-Content $file -Raw -Encoding UTF8
    $originalContent = $content
    
    # Fix 1: Add periods to inline comments that don't end with punctuation
    # Match: // comment text (not ending with . ! ?)
    $content = $content -replace '(//[^.!?\r\n]+)(?<!\.)(?<!!)(?<!\?)(\r?\n)', '$1.$2'
    
    # Fix 2: Add class docblock if missing (after ABSPATH check, before class declaration)
    if ($content -match '(?s)(// Exit if accessed directly\.\s+if \( ! defined\( ''ABSPATH'' \) \)\s+\{\s+exit;\s+\}\s+)(class\s+\w+\s+\{)') {
        $classMatch = $matches[0]
        $className = if ($classMatch -match 'class\s+(\w+)') { $matches[1] } else { "SPPopups" }
        
        if ($classMatch -notmatch '/\*\*.*?@package') {
            $docblock = @"

/**
 * $className class.
 *
 * @package SPPopups
 */
"@
            $content = $content -replace '(\}\s+)(class\s+\w+\s+\{)', "`$1$docblock`n`$2"
        }
    }
    
    # Fix 3: Yoda conditions - fix !== null patterns
    $content = $content -replace '(\$\w+)\s+!==\s+null', 'null !== $1'
    $content = $content -replace '(\$\w+)\s+===\s+null', 'null === $1'
    
    # Fix 4: Parameter comment punctuation in @param tags
    $content = $content -replace '(@param\s+\S+\s+\$\w+\s+[^.!?\r\n]+)(?<!\.)(?<!!)(?<!\?)(\s*\*)', '$1.$2'
    
    # Fix 5: Fix "Exit if accessed directly" comment if missing period
    $content = $content -replace '// Exit if accessed directly(\s)', '// Exit if accessed directly.$1'
    
    # Only write if content changed
    if ($content -ne $originalContent) {
        Set-Content -Path $file -Value $content -Encoding UTF8 -NoNewline
        Write-Host "  Updated $file" -ForegroundColor Green
    } else {
        Write-Host "  No changes needed for $file" -ForegroundColor Gray
    }
}

Write-Host "`nDone! Run phpcs to verify fixes." -ForegroundColor Green
