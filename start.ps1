$ErrorActionPreference = 'Stop'
Set-Location -Path $PSScriptRoot
if (-not (Get-Command node -ErrorAction SilentlyContinue)) {
  throw 'Node.js 18+ is required. Install it from https://nodejs.org/'
}
if (-not $env:PORT) { $env:PORT = '8083' }
Write-Host "Starting Scratch Code on http://localhost:$env:PORT"
if (-not (Test-Path 'public/vendor/scratch-gui/dist/scratch-gui.js.gz')) {
  node scripts/compress.js
}
node server.js
