param(
    [string]$Version = "1.30.11",
    [string]$OutputDirectory = "..\_source_pack"
)

$ErrorActionPreference = "Stop"
$repositoryRoot = (Resolve-Path (Join-Path $PSScriptRoot "..")).Path
$resolvedOutput = [System.IO.Path]::GetFullPath((Join-Path $repositoryRoot $OutputDirectory))
$archivePath = Join-Path $resolvedOutput ("MR_BAR-Full-Source-v{0}.zip" -f $Version)

New-Item -ItemType Directory -Force -Path $resolvedOutput | Out-Null
if (Test-Path $archivePath) {
    Remove-Item -Force $archivePath
}

git -C $repositoryRoot archive --format=zip --prefix=("MR_BAR-Full-Source-v{0}/" -f $Version) --output=$archivePath HEAD
if ($LASTEXITCODE -ne 0) {
    throw "git archive failed"
}

$hash = (Get-FileHash -Algorithm SHA256 $archivePath).Hash
Write-Output ("ARCHIVE={0}" -f $archivePath)
Write-Output ("SHA256={0}" -f $hash)
