# Windows Setup Guide

## Network Packet Analyzer - Windows Installation

This guide covers setup for **Windows 10/11**.

### Prerequisites for Windows

1. **Python 3.9+**
   - Download from: https://www.python.org/downloads/
   - ✅ Check "Add Python to PATH" during installation
   - Verify: Open PowerShell and run `python --version`

2. **Node.js 18+**
   - Download from: https://nodejs.org/
   - Verify: `node --version` and `npm --version`

3. **PHP 8.1+ (for Backend)**
   - Download from: https://windows.php.net/download/
   - Or use: Windows Subsystem for Linux (WSL)
   - Or use: Docker Desktop

4. **Wireshark** (Required for packet capture)
   - Download: https://www.wireshark.org/download/
   - Install with default options
   - ⚠️ **IMPORTANT**: You need admin privileges for packet capture

5. **PostgreSQL or SQLite** (Optional - for production)
   - PostgreSQL: https://www.postgresql.org/download/windows/
   - SQLite: Works out of the box with Laravel

---

## Step 1: Backend API Setup (Windows)

### Option A: Using XAMPP (Easiest for Windows)

```powershell
# 1. Download XAMPP from: https://www.apachefriends.org/
# 2. Install XAMPP with Apache, MySQL/MariaDB, PHP, phpMyAdmin

# 3. Open PowerShell as Administrator
cd C:\xampp\htdocs

git clone your_repo.git
cd network-packet-analyzer\backend-api

# 4. Copy environment file
Copy-Item .env.example .env

# 5. Install Composer (if not installed)
# Download from: https://getcomposer.org/

composer install

# 6. Generate key
php artisan key:generate

# 7. Configure database in .env
# For SQLite:
# DB_CONNECTION=sqlite
# DB_DATABASE=C:\xampp\htdocs\network-packet-analyzer\backend-api\database\packet_analyzer.db

# For MySQL (XAMPP):
# DB_CONNECTION=mysql
# DB_HOST=127.0.0.1
# DB_DATABASE=packet_analyzer
# DB_USERNAME=root
# DB_PASSWORD=

# 8. Run migrations
php artisan migrate

# 9. Start XAMPP (start Apache and MySQL)
# Then access: http://localhost/network-packet-analyzer/backend-api/public/api/health
```

### Option B: Using PHP Built-in Server

```powershell
# 1. Install PHP via Chocolatey
choco install php

# 2. Navigate to backend
cd network-packet-analyzer\backend-api

# 3. Install Composer dependencies
composer install

# 4. Setup environment
Copy-Item .env.example .env

# 5. Generate key
php artisan key:generate

# 6. Run migrations
php artisan migrate

# 7. Start server
php artisan serve
# Access: http://localhost:8000/api
```

### Option C: Using Windows Subsystem for Linux (WSL)

```powershell
# Install WSL2 (Windows 10/11)
wsl --install

# Then follow Linux setup guide inside WSL terminal
```

---

## Step 2: Router Agent Setup (Windows)

The router agent on Windows requires **Wireshark** for packet capture.

### Setup Instructions

```powershell
# 1. Install Wireshark
# Download and install from: https://www.wireshark.org/download/
# Choose option to "Install Npcap" (packet capture driver)

# 2. Navigate to router agent
cd network-packet-analyzer\router-agent

# 3. Create Python virtual environment
python -m venv venv

# 4. Activate virtual environment
.\venv\Scripts\Activate.ps1

# If you get execution policy error:
Set-ExecutionPolicy -ExecutionPolicy RemoteSigned -Scope CurrentUser

# Then retry:
.\venv\Scripts\Activate.ps1

# 5. Install dependencies
pip install -r requirements-windows.txt

# 6. Configure agent
Copy-Item .env.example .env

# Edit .env file:
# INTERFACE=Ethernet  (see list below for your interface name)
# BACKEND_URL=http://localhost:8000/api
# DEBUG=True  (for testing)
```

### Finding Your Interface Name (Windows)

```powershell
# Open PowerShell and run:
ipconfig

# Look for:
# - "Ethernet" - Wired connection
# - "Wi-Fi" - Wireless connection
# - "Adapter" names vary by system

# Or use Python to list interfaces:
python -m pyshark.tshark --list-interfaces

# Or use PowerShell:
Get-NetAdapter | Select-Object Name, InterfaceDescription
```

### Run Router Agent

```powershell
# Make sure you're in the virtual environment
# (should see (venv) in terminal prefix)

# Run agent
python main_windows.py

# Output should show:
# Network Packet Analyzer - Windows Router Agent
# Starting packet capture...
```

---

## Step 3: Frontend Dashboard Setup (Windows)

```powershell
# 1. Navigate to frontend
cd network-packet-analyzer\frontend-dashboard

# 2. Install Node dependencies
npm install

# 3. Create environment file
$env_content = @"
REACT_APP_API_URL=http://localhost:8000/api
REACT_APP_DEBUG=false
"@
$env_content | Out-File -Encoding UTF8 .env.local

# 4. Start development server
npm run dev

# Browser opens to: http://localhost:5173 (or similar)

# 5. For production build
npm run build
# Output in: dist/ folder
```

---

## Verification Checklist

```powershell
# 1. Check Backend API
curl http://localhost:8000/api/health
# Should return: {"status":"ok"}

# 2. Check Frontend (open browser)
http://localhost:5173

# 3. Check Router Agent (should see in terminal)
# "Capture loop started on Ethernet"
# "Analyzed X packets"

# 4. Check processes
Get-Process | Where-Object {$_.Name -like "*php*" -or $_.Name -like "*node*" -or $_.Name -like "*python*"}
```

---

## Running Everything Together

### Quick Start Script (save as `start-all.ps1`)

```powershell
# Navigate to project root
cd network-packet-analyzer

# Terminal 1: Backend
Start-Process -NoNewWindow -FilePath "cmd" -ArgumentList "/k cd backend-api && php artisan serve"

# Wait a moment
Start-Sleep -Seconds 3

# Terminal 2: Router Agent
Start-Process -NoNewWindow -FilePath "powershell" -ArgumentList "-noprofile -command '& {cd router-agent; .\venv\Scripts\Activate.ps1; python main_windows.py}'"

# Wait a moment
Start-Sleep -Seconds 3

# Terminal 3: Frontend
Start-Process -NoNewWindow -FilePath "cmd" -ArgumentList "/k cd frontend-dashboard && npm run dev"

Write-Host "✓ All services started!"
Write-Host "  Backend:  http://localhost:8000/api"
Write-Host "  Frontend: http://localhost:5173"
Write-Host "  Agent:    Running in background"
```

### Manual Start (Recommended for First Time)

Open **3 separate PowerShell/Command windows:**

**Window 1 - Backend:**
```powershell
cd network-packet-analyzer\backend-api
php artisan serve
```

**Window 2 - Router Agent:**
```powershell
cd network-packet-analyzer\router-agent
.\venv\Scripts\Activate.ps1
python main_windows.py
```

**Window 3 - Frontend:**
```powershell
cd network-packet-analyzer\frontend-dashboard
npm run dev
```

---

## Windows-Specific Troubleshooting

### Issue: "pyshark not found"
```powershell
# Solution: Install explicitly
pip install pyshark

# Then install Wireshark:
# Download from: https://www.wireshark.org/download/
```

### Issue: "Wireshark not found"
```powershell
# Verify Wireshark installation
where tshark
# Should show path like: C:\Program Files\Wireshark\tshark.exe

# If not found, reinstall Wireshark with Npcap option
```

### Issue: "Permission denied" for packet capture
```powershell
# Solution 1: Run as Administrator
# Right-click PowerShell → "Run as administrator"

# Solution 2: Check if Npcap is installed properly
# Go to: Settings > Apps > Installed apps
# Look for "Npcap" (installed by Wireshark)
```

### Issue: PHP not found
```powershell
# Solution: Add PHP to PATH
# 1. Settings > System > Environment Variables
# 2. Add PHP installation directory to PATH
# 3. Restart PowerShell

# Verify:
php --version
```

### Issue: Port already in use (8000, 5173)
```powershell
# Change in .env or config:
# Backend: APP_PORT=8001
# Frontend: VITE_PORT=5174

# Or kill process using port:
netstat -ano | findstr :8000
taskkill /PID <PID_NUMBER> /F
```

---

## Database Setup (Windows)

### SQLite (Easiest - No Installation)
```powershell
# Already included with Laravel
# Just update .env:
DB_CONNECTION=sqlite
DB_DATABASE=database\packet_analyzer.db

# Run migrations:
php artisan migrate
```

### MySQL/MariaDB (via XAMPP)
```powershell
# 1. Start XAMPP
# 2. Create database:
# Open http://localhost/phpmyadmin
# Create database "packet_analyzer"

# 3. Update .env:
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_DATABASE=packet_analyzer
DB_USERNAME=root
DB_PASSWORD=

# 4. Run migrations:
php artisan migrate
```

### PostgreSQL (Windows Native)
```powershell
# 1. Download installer: https://www.postgresql.org/download/windows/
# 2. Install (remember password for 'postgres' user)
# 3. Update .env:
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_DATABASE=packet_analyzer
DB_USERNAME=postgres
DB_PASSWORD=your_password

# 4. Run migrations:
php artisan migrate
```

---

## Production Deployment on Windows

For production, consider:

1. **Windows Server** with IIS
2. **Azure App Service**
3. **Docker Desktop**
4. **WSL2 + Nginx**

---

## Common Commands

```powershell
# Python virtual environment
python -m venv venv
.\venv\Scripts\Activate.ps1
deactivate

# Composer
composer install
composer update
composer require package-name

# Laravel
php artisan migrate
php artisan tinker
php artisan serve --host=0.0.0.0 --port=8000

# NPM
npm install
npm run dev
npm run build
npm run preview

# Git
git clone repo.git
git pull
git status
```

---

## Next Steps

1. ✅ Install Wireshark & dependencies
2. ✅ Run all three services
3. ✅ Open http://localhost:5173
4. ✅ Start monitoring your network!

---

**For more help**: See [../README.md](../README.md) and [API.md](API.md)

**Version**: 1.0.0 Windows Edition
**Last Updated**: May 2026
