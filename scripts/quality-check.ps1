$ErrorActionPreference = "Stop"
$repositoryRoot = (Resolve-Path (Join-Path $PSScriptRoot "..")).Path
$tracked = @(git -C $repositoryRoot ls-files)
if ($LASTEXITCODE -ne 0) { throw "Unable to list tracked files" }

$phpFiles = @($tracked | Where-Object { $_ -match '\.php$' -and $_ -notmatch '^storage/' })
$jsFiles = @($tracked | Where-Object { $_ -match '\.js$' })

$failures = New-Object System.Collections.Generic.List[string]
foreach ($relative in $phpFiles) {
    $full = Join-Path $repositoryRoot $relative
    & php -l $full *> $null
    if ($LASTEXITCODE -ne 0) { $failures.Add("PHP syntax: $relative") }
}
foreach ($relative in $jsFiles) {
    $full = Join-Path $repositoryRoot $relative
    & node --check $full *> $null
    if ($LASTEXITCODE -ne 0) { $failures.Add("JavaScript syntax: $relative") }
}

$forbidden = '(^|/)(storage/(data|runtime-data)\.php|uploads?/|backups?/|logs?/|cache/)|\.(env|sql|sqlite3?|db|csv|xlsx?|zip|bak)$'
foreach ($relative in $tracked) {
    if ($relative -match $forbidden) { $failures.Add("Forbidden tracked file: $relative") }
    $full = Join-Path $repositoryRoot $relative
    if ((Get-Item $full).Length -gt 20MB) { $failures.Add("Tracked file over 20 MB: $relative") }
}

$secretPatterns = @(
    '-----BEGIN (RSA |EC |OPENSSH )?PRIVATE KEY-----',
    'github_pat_[A-Za-z0-9_]{20,}',
    'ghp_[A-Za-z0-9]{30,}',
    'AKIA[0-9A-Z]{16}'
)
$textExtensions = @('.php','.js','.css','.json','.md','.txt','.yml','.yaml','.htaccess','.gitattributes','.gitignore')
foreach ($relative in $tracked) {
    $extension = [System.IO.Path]::GetExtension($relative).ToLowerInvariant()
    $leaf = [System.IO.Path]::GetFileName($relative)
    if ($textExtensions -notcontains $extension -and $leaf -notin @('.htaccess','.gitattributes','.gitignore')) { continue }
    $content = Get-Content -Raw -LiteralPath (Join-Path $repositoryRoot $relative)
    foreach ($pattern in $secretPatterns) {
        if ($content -match $pattern) { $failures.Add("Possible secret pattern: $relative"); break }
    }
}

if ($failures.Count -gt 0) {
    $failures | Sort-Object -Unique | ForEach-Object { Write-Output $_ }
    exit 1
}

Write-Output ("TRACKED_FILES={0}" -f $tracked.Count)
Write-Output ("PHP_FILES={0}" -f $phpFiles.Count)
Write-Output ("JS_FILES={0}" -f $jsFiles.Count)
Write-Output "FORBIDDEN_FILES=0"
Write-Output "POSSIBLE_SECRETS=0"
Write-Output "QUALITY_CHECK=PASS"
