# Network Packet Analyzer & Threat Detection System

A real-time network monitoring and security system that captures packets, detects threats, tracks device activity, and visualizes your entire network through a modern web dashboard.

---

## Table of Contents

- [How It Works](#how-it-works)
- [Architecture](#architecture)
- [Features](#features)
- [Prerequisites](#prerequisites)
- [Installation](#installation)
- [Running the System](#running-the-system)
- [Configuration](#configuration)
- [Dashboard Pages](#dashboard-pages)
- [Threat Detection](#threat-detection)
- [API Reference](#api-reference)
- [Troubleshooting](#troubleshooting)

---

## How It Works

The system has three components that work together:

```
┌─────────────────┐     ┌─────────────────┐     ┌─────────────────┐
│  Router Agent   │────▶│   Backend API   │────▶│    Frontend     │
│   (Python)      │     │   (Laravel)     │     │   (React)       │
│                 │     │                 │     │                 │
│ Captures packets│     │ Stores data     │     │ Shows dashboard │
│ Detects threats │     │ Analyzes threat │     │ Real-time views │
│ Sends to API    │     │ Serves API      │     │ Device tracking │
└─────────────────┘     └─────────────────┘     └─────────────────┘
        │                       │                       │
        │    UDP Broadcast      │    WebSocket          │
        └──────────────────────▶└──────────────────────▶
              (port 6002)            (port 6001)
```

### Data Flow

1. **Router Agent** captures network packets using pyshark (Wireshark's Python library)
2. It extracts device info (IP, MAC, hostname), domains visited, and traffic data
3. **Threat detection** runs in two places:
   - Python agent: detects ARP spoofing, port scans, DDoS, excessive connections
   - PHP backend: detects unsafe sites, suspicious DNS, VPN usage, website visits
4. Agent sends data to the backend API via HTTP POST every 5 seconds
5. Backend stores data in SQLite database and broadcasts via UDP to WebSocket server
6. **WebSocket server** streams packets and threats to all connected frontend clients
7. Frontend dashboard displays everything in real-time

---

## Architecture

### Components

| Component | Technology | Port | Purpose |
|-----------|-----------|------|---------|
| Router Agent | Python + pyshark | - | Packet capture and local threat detection |
| Backend API | Laravel 10 + PHP 8.1 | 8000 | REST API, data storage, threat analysis |
| Frontend | React + Vite + Tailwind | 5173 | Web dashboard and real-time views |
| WebSocket Server | Python + aiohttp | 6001 | Real-time packet/threat streaming |
| Database | SQLite | - | Device, traffic, and threat storage |

### Network Diagram

```
                    ┌──────────────┐
                    │   Internet   │
                    └──────┬───────┘
                           │
                    ┌──────┴───────┐
                    │ Phone Hotspot│
                    │  (AP Mode)   │
                    └──────┬───────┘
                           │
              ┌────────────┼────────────┐
              │            │            │
        ┌─────┴─────┐ ┌───┴───┐ ┌─────┴─────┐
        │  Device 1 │ │ Phone │ │  Device 2 │
        │ (Windows) │ │ Router│ │ (Laptop)  │
        └───────────┘ └───┬───┘ └───────────┘
                          │
                   ┌──────┴──────┐
                   │Router Agent │
                   │  (Python)   │
                   └──────┬──────┘
                          │
                   ┌──────┴──────┐
                   │ Backend API │
                   │  (Laravel)  │
                   └──────┬──────┘
                          │
                   ┌──────┴──────┐
                   │   Frontend  │
                   │   (React)   │
                   └─────────────┘
```

---

## Features

### Real-time Monitoring
- Live device discovery and tracking
- Real-time packet capture and analysis
- WebSocket streaming for instant updates
- Live network topology visualization

### Threat Detection
- **ARP Spoofing** - Detects MAC address changes on gateway
- **Port Scanning** - Identifies reconnaissance attacks
- **DDoS Detection** - Flags traffic bursts exceeding thresholds
- **Malware Domains** - Matches against known threat patterns
- **Suspicious DNS** - Detects queries to known malicious domains
- **VPN Detection** - Identifies VPN/proxy usage
- **Website Visits** - Tracks all HTTP/HTTPS site visits in real-time
- **Excessive Connections** - Flags devices making too many connections
- **Data Exfiltration** - Detects unusually large data transfers

### Dashboard Pages
- **Dashboard** - Overview with stats, charts, and connected devices
- **Devices** - Full device list with online/offline status
- **Device Detail** - Per-device bandwidth, sites visited, connections
- **Live Packets** - Real-time packet stream with filtering
- **Security Center** - Threat list with filtering and actions
- **Analytics** - Content distribution and top domains
- **Topology** - Network topology visualization
- **Reports** - Daily/weekly/monthly summaries
- **Speed Test** - Network performance testing
- **Settings** - Active window and refresh configuration

---

## Prerequisites

### Windows (Primary Platform)
- **Windows 10/11** (64-bit)
- **Python 3.9+** (with pip)
- **PHP 8.1+** (with Composer)
- **Node.js 18+** (with npm)
- **Wireshark** (required for pyshark packet capture)
- **Administrator privileges** (for network packet capture)

### Required Software
1. **Python 3.9+** - Download from https://python.org
2. **PHP 8.1+** - Download from https://windows.php.net or use XAMPP
3. **Composer** - Download from https://getcomposer.org
4. **Node.js 18+** - Download from https://nodejs.org
5. **Wireshark** - Download from https://wireshark.org/download

---

## Installation

### 1. Clone the Repository

```bash
git clone https://github.com/yourusername/network-packet-analyzer.git
cd network-packet-analyzer
```

### 2. Backend Setup (Laravel)

```bash
cd backend-api

# Copy environment file
cp .env.example .env

# Install PHP dependencies
composer install

# Generate application key
php artisan key:generate

# Run database migrations
php artisan migrate

# Start the backend server
php artisan serve
```

The backend will run at `http://localhost:8000`.

### 3. Router Agent Setup (Python)

```bash
cd router-agent

# Create virtual environment
python -m venv venv

# Activate virtual environment (Windows)
.\venv\Scripts\Activate.ps1

# Or activate (Linux/Mac)
source venv/bin/activate

# Install dependencies
pip install -r requirements-windows.txt

# Copy environment file
cp .env.windows .env

# Edit .env to set your backend URL
# BACKEND_URL=http://localhost:8000
# ROUTER_AGENT_KEY=your_secret_key_here
```

### 4. Frontend Setup (React)

```bash
cd frontend-dashboard

# Install Node dependencies
npm install

# Start development server
npm run dev
```

The frontend will run at `http://localhost:5173`.

### 5. WebSocket Server Setup

```bash
cd router-agent

# Install aiohttp if not already installed
pip install aiohttp>=3.9.0

# Start WebSocket server
python websocket_server.py
```

The WebSocket server will run at `ws://localhost:6001`.

---

## Running the System

### Start All Services (in order)

**Terminal 1 - Backend:**
```bash
cd backend-api
php artisan serve
```

**Terminal 2 - WebSocket Server:**
```bash
cd router-agent
python websocket_server.py
```

**Terminal 3 - Router Agent (Run as Administrator):**
```bash
cd router-agent
.\venv\Scripts\Activate.ps1
python main_windows.py
```

**Terminal 4 - Frontend:**
```bash
cd frontend-dashboard
npm run dev
```

### Verify Everything Works

1. Open browser to `http://localhost:5173`
2. Check Dashboard shows "Live" WebSocket status
3. Check Devices page shows your computer
4. Browse any website - it should appear in Security Center
5. Check Live Packets page for real-time traffic

---

## Configuration

### Router Agent (.env)

```env
# Backend API connection
BACKEND_URL=http://localhost:8000
ROUTER_AGENT_KEY=your_secret_key_here

# Capture settings
INTERFACES=Wi-Fi
PACKET_BUFFER_SIZE=10000
ANALYSIS_INTERVAL=5
SYNC_INTERVAL=5

# Threat detection
ENABLE_ARP_SPOOF_DETECTION=true
ENABLE_PORT_SCAN_DETECTION=true
ENABLE_DDOS_DETECTION=true
```

### Backend (.env)

```env
APP_NAME="Network Packet Analyzer"
APP_URL=http://localhost:8000
APP_KEY=base64:...

# Database (SQLite by default)
DB_CONNECTION=sqlite
DB_DATABASE=database/network_packet_analyzer.sqlite

# Router agent authentication
ROUTER_AGENT_KEY=your_secret_key_here
```

### Frontend Settings

The frontend stores settings in browser localStorage:
- **Active Window**: How long devices stay visible (default: 2 minutes)
- **Refresh Interval**: Dashboard polling frequency (default: 5 seconds)

Access via Settings page in the dashboard.

---

## Dashboard Pages

### Dashboard
- **Active Devices**: Count of currently connected devices
- **Total Traffic**: Sum of all captured traffic in MB
- **Threats Detected**: Total threats in last 24 hours
- **Avg Bandwidth**: Average throughput
- **Packets Passed**: Total packets analyzed
- **Traffic Chart**: Upload/download over 24 hours
- **Recent Threats**: Latest security events
- **Connected Devices Table**: All devices with status

### Devices
- Full list of discovered devices
- Shows device name, type, IP, vendor, MAC, last seen
- Online/Offline status indicator
- Filters infrastructure devices (router, gateway)

### Device Detail
- Per-device bandwidth usage
- Top sites visited with block controls
- Threat history
- Blocked domains
- Live connection tree

### Live Packets
- Real-time packet stream via WebSocket
- Filter by protocol, IP, or domain
- Shows source/destination, protocol, size, domain
- Period selector (5m, 1h, 24h, 7d)

### Security Center
- All detected threats and website visits
- Filter by level (Critical/High/Medium/Low)
- Filter by type (Site Visits, Unsafe Sites, DNS, VPN, etc.)
- Filter by time period
- Block/Resolve actions
- Real-time WebSocket updates

### Analytics
- Content type distribution (pie chart)
- Top domains by traffic
- Protocol analysis
- DNS queries
- Blocked domains

### Topology
- Visual network map
- Shows Internet -> Router -> Devices
- Device icons by type (phone, laptop, TV, etc.)
- Green/Red online indicators
- Real-time updates every 5 seconds

### Reports
- Daily/Weekly/Monthly traffic summaries
- Protocol usage breakdown
- Top devices by traffic
- Policy snapshots

### Speed Test
- Download/Upload speed testing
- Ping and jitter measurement
- Test history chart

---

## Threat Detection

### How Threats Are Detected

**Python Agent (Local):**
- ARP Spoofing: IP-MAC mapping changes
- Port Scanning: >40 unique ports in 60 seconds
- DDoS: >5000 packets with small size
- Excessive Connections: >80 connection attempts in 10 seconds
- Data Exfiltration: >500MB transfer in short period

**PHP Backend (Per-Packet):**
- Unsafe Sites: Domain matches threat patterns (phishing, malware, etc.)
- Suspicious DNS: DNS queries to known malicious domains
- VPN Detection: Traffic on VPN-specific ports
- Website Visits: All HTTP/HTTPS traffic with valid domain
- Anomalous Traffic: 20x above device's 7-day average

### Threat Levels
- **Critical**: ARP spoofing, DDoS attacks
- **High**: Port scans, unsafe sites, excessive connections
- **Medium**: Suspicious DNS, MAC changes
- **Low**: VPN detection, website visits

### Real-time Threat Streaming

The system broadcasts threats in real-time via WebSocket:
1. Backend detects threat via `ThreatAnalysisService`
2. Creates threat record in database
3. Broadcasts via UDP to WebSocket server (port 6002)
4. WebSocket server forwards to all connected clients (port 6001)
5. Frontend receives and displays instantly

---

## API Reference

### Devices
```
GET    /api/devices                    # List devices
POST   /api/devices/register           # Register device
GET    /api/devices/{id}               # Device details
PATCH  /api/devices/{id}               # Update device
POST   /api/devices/{id}/block-domain  # Block domain
POST   /api/devices/{id}/unblock-domain # Unblock domain
GET    /api/devices/{id}/connections   # Device connections
```

### Traffic
```
GET    /api/traffic/stats              # Traffic statistics
GET    /api/traffic/timeline           # Traffic timeline
GET    /api/traffic/live-packets       # Recent packets
GET    /api/traffic/device/{id}        # Device traffic
GET    /api/traffic/device/{id}/content # Content distribution
GET    /api/traffic/device/{id}/top-domains # Top domains
```

### Threats
```
GET    /api/threats                    # List threats
GET    /api/threats/stats              # Threat statistics
GET    /api/threats/device/{id}        # Device threats
PATCH  /api/threats/{id}/block         # Block threat
PATCH  /api/threats/{id}/resolve       # Resolve threat
```

### Data Ingestion
```
POST   /api/data/ingest                # Ingest from router agent
```

### Other
```
GET    /api/topology                   # Network topology
GET    /api/reports/summary            # Report summary
GET    /api/alerts                     # List alerts
POST   /api/speedtest/run              # Run speed test
GET    /api/health                     # Health check
```

---

## Troubleshooting

### "No devices showing"
1. Run router agent as Administrator
2. Check Wireshark is installed
3. Verify `.env` has correct `BACKEND_URL`
4. Check `agent.log` for errors

### "WebSocket not connecting"
1. Start WebSocket server: `python websocket_server.py`
2. Check port 6001 is not blocked
3. Look for "Live" indicator on Dashboard

### "Threats not appearing"
1. Ensure router agent is capturing packets
2. Check backend is running on port 8000
3. Visit a website with "phishing" or "malware" in the domain
4. Check Security Center filters are set to "All Types"

### "Backend won't start"
```bash
cd backend-api
composer install
php artisan key:generate
php artisan migrate
php artisan serve
```

### "Frontend build fails"
```bash
cd frontend-dashboard
rm -rf node_modules
npm install
npm run dev
```

### "pyshark import error"
1. Ensure Wireshark is installed
2. Ensure Wireshark's tshark is in PATH
3. Reinstall: `pip install pyshark`

---

## Project Structure

```
network-packet-analyzer/
├── backend-api/                    # Laravel PHP API
│   ├── app/
│   │   ├── Http/Controllers/       # API controllers
│   │   │   ├── TrafficController.php    # Data ingestion & traffic
│   │   │   ├── ThreatController.php     # Threat management
│   │   │   ├── DeviceController.php     # Device management
│   │   │   └── AlertController.php      # Alert notifications
│   │   ├── Models/                 # Eloquent models
│   │   │   ├── Device.php
│   │   │   ├── Threat.php
│   │   │   ├── TrafficLog.php
│   │   │   └── Alert.php
│   │   └── Services/               # Business logic
│   │       └── ThreatAnalysisService.php
│   ├── database/migrations/        # Database schema
│   ├── routes/api.php              # API routes
│   └── .env.example                # Environment template
│
├── router-agent/                   # Python packet capture agent
│   ├── src/
│   │   ├── packet_capturer_windows.py  # Windows packet capture
│   │   ├── packet_capturer.py          # Linux packet capture
│   │   └── threat_detector.py          # Local threat detection
│   ├── websocket_server.py         # WebSocket streaming server
│   ├── main_windows.py             # Windows agent entry point
│   ├── main.py                     # Linux agent entry point
│   ├── config_windows.py           # Windows configuration
│   ├── requirements-windows.txt    # Python dependencies
│   └── .env.windows                # Environment template
│
├── frontend-dashboard/             # React web dashboard
│   ├── src/
│   │   ├── pages/                  # Dashboard pages
│   │   │   ├── Dashboard.jsx       # Main overview
│   │   │   ├── Devices.jsx         # Device list
│   │   │   ├── DeviceDetail.jsx    # Per-device view
│   │   │   ├── LivePackets.jsx     # Real-time packets
│   │   │   ├── SecurityCenter.jsx  # Threat management
│   │   │   ├── Analytics.jsx       # Traffic analytics
│   │   │   ├── Topology.jsx        # Network map
│   │   │   ├── Reports.jsx         # Summaries
│   │   │   ├── SpeedTest.jsx       # Speed testing
│   │   │   ├── Alerts.jsx          # Alert notifications
│   │   │   └── Settings.jsx        # Configuration
│   │   ├── services/api.js         # API client
│   │   └── App.jsx                 # Router setup
│   ├── package.json                # Node dependencies
│   └── vite.config.js              # Build configuration
│
└── README.md                       # This file
```

---

## GitHub Hosting Setup

### To host on GitHub:

1. **Create a new repository** on GitHub

2. **Initialize and push:**
```bash
cd network-packet-analyzer
git init
git add .
git commit -m "Initial commit: Network Packet Analyzer"
git branch -M main
git remote add origin https://github.com/yourusername/network-packet-analyzer.git
git push -u origin main
```

3. **Add .gitignore** (create if not exists):
```gitignore
# Python
__pycache__/
*.py[cod]
venv/
.env

# PHP
backend-api/vendor/
backend-api/.env
backend-api/*.sqlite

# Node
frontend-dashboard/node_modules/
frontend-dashboard/dist/

# IDE
.vscode/
.idea/
*.swp
*.swo

# OS
.DS_Store
Thumbs.db
```

4. **Users cloning your repo need:**
```bash
git clone https://github.com/yourusername/network-packet-analyzer.git
cd network-packet-analyzer

# Follow Installation steps above for each component
```

---

## License

MIT License

---

**Version**: 2.0.0
**Last Updated**: June 2026