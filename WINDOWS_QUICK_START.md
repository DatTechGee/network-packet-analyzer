# Quick Start Guide - Windows 10/11

## 🚀 Network Packet Analyzer - 5-Minute Windows Setup

### ✅ Prerequisites (Install First)

1. **Python 3.9+** → https://www.python.org/downloads/
   - ✓ Check "Add Python to PATH"
   
2. **Node.js 18+** → https://nodejs.org/
   
3. **Wireshark** → https://www.wireshark.org/download/
   - ✓ Install with "Npcap" option (for packet capture)
   
4. **PHP 8.1+** (Choose one):
   - XAMPP: https://www.apachefriends.org/ (Easiest)
   - or Native PHP: https://windows.php.net/download/
   - or WSL2
   
5. **Composer** (for PHP) → https://getcomposer.org/

---

## 🎯 One-Command Setup (Windows PowerShell)

### Step 1: Open PowerShell as Administrator

Right-click PowerShell → "Run as Administrator"

### Step 2: Run Setup Script

```powershell
# Navigate to project
cd C:\Users\Bris\attendance-system\network-packet-analyzer

# Run setup script
.\setup-windows.ps1 -Action all
```

**This will:**
- ✓ Install backend dependencies
- ✓ Install Python packages for agent
- ✓ Install frontend packages
- ✓ Configure all .env files
- ✓ Run database migrations

---

## 🏃 Quick Manual Setup

### Terminal 1: Backend API

```powershell
cd network-packet-analyzer\backend-api
Copy-Item .env.example .env
composer install
php artisan migrate
php artisan serve
```

**Output:**
```
Laravel development server started: http://127.0.0.1:8000
```

### Terminal 2: Router Agent

```powershell
cd network-packet-analyzer\router-agent
python -m venv venv
.\venv\Scripts\Activate.ps1
pip install -r requirements-windows.txt
Copy-Item .env.example .env
python main_windows.py
```

**Output:**
```
Network Packet Analyzer - Windows Router Agent
Starting packet capture on Ethernet...
```

### Terminal 3: Frontend Dashboard

```powershell
cd network-packet-analyzer\frontend-dashboard
npm install
npm run dev
```

**Output:**
```
  VITE v5.0.0  ready in 345 ms

  ➜  Local:   http://127.0.0.1:5173/
```

---

## 🌐 Access Your Dashboard

After all 3 services are running:

| Component | URL |
|-----------|-----|
| **Dashboard** | http://localhost:5173 |
| **Backend API** | http://localhost:8000/api |
| **API Health** | http://localhost:8000/api/health |

---

## ⚙️ Configure Network Interface

The router agent needs to know which network interface to monitor.

### Find Your Interface Name:

```powershell
# Open Command Prompt (not PowerShell) and run:
ipconfig

# Look for:
# - "Ethernet adapter" → Name is "Ethernet"
# - "Wireless LAN adapter" → Name is "Wi-Fi"
# - Copy the adapter name exactly

# Then update in: router-agent\.env
# INTERFACE=Ethernet
```

---

## 📊 What You Can Monitor

Once running, your dashboard shows:

- 📱 **Active Devices** - All connected devices
- 📈 **Real-time Bandwidth** - Upload/download speeds
- 🔴 **Threat Alerts** - Security threats detected
- 📉 **Analytics** - Usage patterns
- 🛡️ **Security Center** - Threat management

---

## 🔧 Troubleshooting

### Issue: "Python not found"
```powershell
# Solution: Python not in PATH
# 1. Reinstall Python with "Add Python to PATH" checked
# 2. Restart PowerShell
# 3. Verify: python --version
```

### Issue: "Wireshark not found"
```powershell
# Solution: Wireshark not installed properly
# 1. Download from https://www.wireshark.org/download/
# 2. Install with "Npcap" option selected
# 3. Restart computer
# 4. Verify: where tshark
```

### Issue: "Permission denied" (packet capture)
```powershell
# Solution: Run PowerShell as Administrator
# Right-click → "Run as administrator"
```

### Issue: "Port 8000 already in use"
```powershell
# Solution: Kill the process using port 8000
netstat -ano | findstr :8000
taskkill /PID <PID_NUMBER> /F

# Then restart: php artisan serve
```

### Issue: "Composer not found"
```powershell
# Solution: Download and install Composer
# https://getcomposer.org/download/
# Add to PATH if needed
```

---

## 🛑 Stop Services

```powershell
# Backend: Press Ctrl+C in Terminal 1
# Agent: Press Ctrl+C in Terminal 2
# Frontend: Press Ctrl+C in Terminal 3
```

---

## 📚 Next Steps

1. ✅ Services running?
2. ✅ Dashboard loading?
3. ✅ Devices showing?
4. 👉 **Go to Security Center to view threats**
5. 👉 **Go to Analytics for bandwidth breakdown**

---

## 🎓 Full Documentation

- **Setup Guide** → [docs/WINDOWS_SETUP.md](docs/WINDOWS_SETUP.md)
- **API Docs** → [docs/API.md](docs/API.md)
- **Project README** → [README.md](README.md)

---

## 💡 Pro Tips

1. **Keep 3 terminals open** while testing
2. **Use Ctrl+C to stop services** (graceful shutdown)
3. **Check logs** if something breaks
4. **Restart Wireshark service** if packet capture stops
5. **Use SQLite** for easiest database setup

---

## 🎉 You're Done!

Your network monitoring system is live on Windows!

Network traffic is being captured and analyzed in real-time.

**Happy monitoring!** 🛡️

---

**Questions?** Check [docs/WINDOWS_SETUP.md](docs/WINDOWS_SETUP.md) for detailed troubleshooting.

**Version:** 1.0.0 Windows Edition  
**Date:** May 2026
