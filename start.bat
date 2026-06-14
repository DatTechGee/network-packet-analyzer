@echo off
echo ============================================
echo   G2 Project - Network Packet Analyzer
echo   Quick Start
echo ============================================
echo.

echo Starting all services...
echo.

echo [1/3] Backend API (port 8000)...
cd /d "%~dp0backend-api"
start "Laravel API" php artisan serve --host=0.0.0.0 --port=8000
timeout /t 3 /nobreak >nul

echo [2/3] WebSocket Server (port 6001)...
cd /d "%~dp0router-agent"
start "WebSocket Server" "%~dp0venv\Scripts\python.exe" websocket_server.py
timeout /t 2 /nobreak >nul

echo [3/3] Frontend Dashboard (port 5173)...
cd /d "%~dp0frontend-dashboard"
start "Dashboard" npm run dev
timeout /t 5 /nobreak >nul

echo.
echo Opening dashboard...
start http://localhost:5173

echo.
echo ============================================
echo   All services running!
echo ============================================
echo   Backend API:  http://localhost:8000
echo   Dashboard:    http://localhost:5173
echo   WebSocket:    ws://localhost:6001
echo.
echo   To start router agent, run:
echo   router-agent\start.bat
echo.
echo   Press Ctrl+C to stop.
echo ============================================
pause
