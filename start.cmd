@echo off
setlocal
cd /d "%~dp0"
where node >nul 2>nul
if errorlevel 1 (
  echo Node.js 18+ is required. Install it from https://nodejs.org/
  exit /b 1
)
if "%PORT%"=="" set PORT=8083
echo Starting Scratch Code on http://localhost:%PORT%
call npm start
endlocal
