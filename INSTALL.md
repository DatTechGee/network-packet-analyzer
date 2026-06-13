# G2 Project — Network Packet Analyzer

## Installation Guide

### Prerequisites

| Software | Version | Download |
|----------|---------|----------|
| Python | 3.9+ | https://python.org/downloads |
| PHP | 8.1+ | https://windows.php.net/download |
| Composer | Latest | https://getcomposer.org/download |
| Node.js | 18+ | https://nodejs.org/download |
| Wireshark | Latest | https://www.wireshark.org/download |

> **Note:** Wireshark is required for packet capture. Install with default options.

---

### 1. Clone the Repository

```bash
git clone https://github.com/your-group/network-packet-analyzer.git
cd network-packet-analyzer
```

---

### 2. Backend API Setup (Laravel)

```bash
cd backend-api

# Install PHP dependencies
composer install

# Copy environment file
cp .env.example .env

# Generate application key
php artisan key:generate

# Create SQLite database
New-Item -ItemType File -Path "database/network_packet_analyzer.sqlite"

# Run migrations
php artisan migrate

# Start the backend server (keeps running in background)
Start-Process php -ArgumentList "artisan serve --host=0.0.0.0 --port=8000"
```

The API is now running at `http://localhost:8000`.

---

### 3. Frontend Dashboard Setup (React)

```bash
cd ../frontend-dashboard

# Install JavaScript dependencies
npm install

# Start the dev server (keeps running in background)
Start-Process npm -ArgumentList "run dev -- --host"
```

The dashboard is now running at `http://localhost:5173`.

---

### 4. WebSocket Server Setup (Real-time Streaming)

```bash
cd ../router-agent

# Create Python virtual environment
python -m venv venv
.\venv\Scripts\Activate.ps1

# Install Python dependencies
pip install -r requirements-windows.txt

# Start the WebSocket server (keeps running in background)
Start-Process python -ArgumentList "websocket_server.py"
```

WebSocket server is now running at `ws://localhost:6001` (listens on UDP 6002).

---

### 5. Router Agent Setup (Packet Capture)

```bash
# Still in router-agent directory with venv active

# Create .env file
@"
ROUTER_AGENT_KEY=network_packet_agent_key
INTERFACES=Wi-Fi
TSHARK_PATH=
BACKEND_URL=http://localhost:8000
SYNC_INTERVAL=5
"@ | Out-File -FilePath .env -Encoding utf8

# Start the router agent (must run as Administrator)
Start-Process python -ArgumentList "main_windows.py" -Verb RunAs
```

> **Important:** The router agent must run as Administrator for network packet capture.

---

### 6. Verify Everything is Running

Open `http://localhost:5173` in your browser. You should see:

1. **Welcome page** — G2 Project introduction
2. **Dashboard** — Shows network stats, threats, connected devices
3. **Security Center** — Shows detected threats with filtering

Check that all 4 processes are running:

```bash
# Check for running processes
Get-Process python, php, node -ErrorAction SilentlyContinue | Select-Object Name, Id
```

You should see:
- `php` — Backend API (port 8000)
- `node` — Frontend dev server (port 5173)
- `python` — WebSocket server (port 6001)
- `python` — Router agent (packet capture)

---

## Quick Start (All Commands)

Run these in order from the project root:

```bash
# 1. Backend
cd backend-api
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
Start-Process php -ArgumentList "artisan serve --host=0.0.0.0 --port=8000"

# 2. Frontend
cd ..\frontend-dashboard
npm install
Start-Process npm -ArgumentList "run dev -- --host"

# 3. WebSocket Server
cd ..\router-agent
.\venv\Scripts\Activate.ps1
Start-Process python -ArgumentList "websocket_server.py"

# 4. Router Agent (as Admin)
Start-Process python -ArgumentList "main_windows.py" -Verb RunAs
```

Then open **http://localhost:5173** in your browser.

---

## How Domain Blocking Works

1. In the **Dashboard** or **Device Detail** page, click the **Block** button next to a domain
2. The backend stores the blocked domain in the device's metadata
3. The **router agent** periodically fetches blocked domains from the backend
4. When a blocked domain is detected, the agent:
   - Logs the attempt as a `site_blocked` threat (high severity)
   - Blocks the domain via the Windows hosts file (requires admin)
5. The blocked domain appears in the **Security Center** as a blocked threat

---

## Troubleshooting

### "No interfaces found" error
```bash
# List available network interfaces
python -c "import pyshark; print(pyshark.LiveCapture('Wi-Fi').list_interfaces())"
```

### Backend API not responding
```bash
# Check if port 8000 is in use
netstat -ano | findstr :8000

# Restart backend
php artisan serve --host=0.0.0.0 --port=8000
```

### Frontend shows "Network Error"
Ensure the backend is running on port 8000. The frontend proxies API requests to it.

### Router agent shows "0 devices"
- Ensure Wireshark is installed
- Run the agent as Administrator
- Check that the correct interface is selected in `.env`

### WebSocket not connecting
```bash
# Check if port 6001 is in use
netstat -ano | findstr :6001

# Restart WebSocket server
python websocket_server.py
```

### Domain blocking not working
- The router agent must run as **Administrator** to modify the Windows hosts file
- Check if entries exist: `type C:\Windows\System32\drivers\etc\hosts | findstr "G2 PROJECT"`
- Flush DNS after blocking: `ipconfig /flushdns`

---

## Project Structure

```
network-packet-analyzer/
├── backend-api/            # Laravel PHP API
│   ├── app/
│   │   ├── Http/Controllers/
│   │   │   ├── DeviceController.php      # Device management + blocking
│   │   │   ├── ThreatController.php      # Threat listing + analysis
│   │   │   ├── TrafficController.php     # Traffic data + ingestion
│   │   │   ├── SpeedTestController.php   # Network speed tests
│   │   │   └── AlertController.php       # Notifications
│   │   ├── Models/                       # Eloquent models
│   │   └── Services/                     # Business logic
│   ├── database/
│   │   ├── migrations/                   # Database schema
│   │   └── *.sqlite                      # SQLite database
│   └── routes/api.php                    # API routes
├── frontend-dashboard/     # React + Vite + Tailwind
│   ├── src/
│   │   ├── components/                   # Shared components
│   │   │   └── Navbar.jsx                # Sidebar navigation
│   │   ├── pages/
│   │   │   ├── Welcome.jsx               # Landing page
│   │   │   ├── Dashboard.jsx             # Main overview
│   │   │   ├── Devices.jsx               # Device list
│   │   │   ├── DeviceDetail.jsx          # Per-device detail
│   │   │   ├── LivePackets.jsx           # Real-time packets
│   │   │   ├── SecurityCenter.jsx        # Threat management
│   │   │   ├── Analytics.jsx             # Content analysis
│   │   │   ├── Topology.jsx              # Network topology
│   │   │   ├── Reports.jsx               # Summaries
│   │   │   ├── SpeedTest.jsx             # Speed tests
│   │   │   └── Settings.jsx              # Configuration
│   │   ├── services/api.js               # API client
│   │   └── hooks/                        # Custom React hooks
│   └── vite.config.js                    # Dev server + proxy
├── router-agent/           # Python packet capture agent
│   ├── src/
│   │   ├── packet_capturer_windows.py    # pyshark packet capture
│   │   ├── threat_detector.py            # Python threat detection
│   │   └── domain_blocker.py             # Domain blocking enforcement
│   ├── main_windows.py                   # Agent entry point
│   ├── websocket_server.py               # Real-time WebSocket server
│   ├── config_windows.py                 # Configuration
│   ├── .env                              # Environment variables
│   └── requirements-windows.txt          # Python dependencies
└── README.md               # Full documentation
```

---

## Environment Variables

### Router Agent (.env)

| Variable | Default | Description |
|----------|---------|-------------|
| `ROUTER_AGENT_KEY` | `network_packet_agent_key` | API authentication key |
| `INTERFACES` | `Wi-Fi` | Network interface(s) to capture |
| `TSHARK_PATH` | (auto-detect) | Path to tshark.exe |
| `BACKEND_URL` | `http://localhost:8000` | Backend API URL |
| `SYNC_INTERVAL` | `5` | Seconds between backend syncs |

---

## License

This project is for educational purposes (G2 Project — Group 2 Packet Tracer).
