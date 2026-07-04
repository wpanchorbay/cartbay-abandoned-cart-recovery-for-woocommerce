Param(
    [switch]$Visible
)

$RootDir = Resolve-Path (Join-Path $PSScriptRoot "..")

Write-Host "Starting CartBay dev tooling..." -ForegroundColor Cyan

# Prefer Bun if available; fallback to npm.
$bun = Get-Command bun -ErrorAction SilentlyContinue
$npm = Get-Command npm -ErrorAction SilentlyContinue

if ($bun) {
    Push-Location $RootDir
    try {
        bun start
    }
    finally {
        Pop-Location
    }
}
elseif ($npm) {
    Push-Location $RootDir
    try {
        npm start
    }
    finally {
        Pop-Location
    }
}
else {
    Write-Host "Error: neither bun nor npm is available in PATH." -ForegroundColor Red
    exit 1
}
