@echo off
echo ============================================
echo   G2 Project - Network Packet Analyzer
echo ============================================
echo.

echo [1/4] Starting Backend API...
cd /d "%~dp0..\backend-api"
start "Laravel API" php artisan serve --host=0.0.0.0 --port=8000
timeout /t 3 /nobreak >nul

echo [2/4] Starting WebSocket Server...
cd /d "%~dp0"
start "WebSocket Server" "%~dp0venv\Scripts\python.exe" websocket_server.py
timeout /t 2 /nobreak >nul

echo [3/4] Starting Router Agent (as Administrator)...
powershell -Command "Start-Process '%~dp0venv\Scripts\python.exe' -ArgumentList '%~dp0main_windows.py' -Verb RunAs"
timeout /t 2 /nobreak >nul

echo [4/4] Opening Dashboard...
start http://localhost:5173

echo.
echo All services started!
echo - Backend API:    http://localhost:8000
echo - Dashboard:      http://localhost:5173
echo - WebSocket:      ws://localhost:6001
echo.
echo Press any key to stop all services...
pause >nul

echo Stopping services...
taskkill /f /im php.exe 2>nul
taskkill /f /im python.exe 2>nul
echo Done.
