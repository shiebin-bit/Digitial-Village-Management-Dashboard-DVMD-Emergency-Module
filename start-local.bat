@echo off
cd /d "%~dp0"
echo Starting DVMD local server at http://127.0.0.1:8001/backend/loginpage.php
echo.
echo Keep this window open while using the website.
echo Press Ctrl+C to stop the server.
echo.
E:\xampp\php\php.exe -S 127.0.0.1:8001 -t .
if errorlevel 1 (
  echo.
  echo Failed to start with E:\xampp\php\php.exe. Trying php from PATH...
  php -S 127.0.0.1:8001 -t .
)
pause
