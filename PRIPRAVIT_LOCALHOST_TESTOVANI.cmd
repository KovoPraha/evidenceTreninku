@echo off
setlocal
chcp 65001 >nul
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0bin\setup-localhost-testing.ps1"
if errorlevel 1 (
  echo.
  echo Priprava selhala. Vyfotografujte tuto obrazovku pro pozdejsi diagnostiku.
)
echo.
pause
endlocal
