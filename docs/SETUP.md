# Setup Guide - Network Packet Analyzer

## System Requirements

| Component | Minimum | Recommended |
|-----------|---------|-------------|
| OS | Ubuntu 18.04 | Ubuntu 22.04 LTS |
| CPU | 1 core | 2+ cores |
| RAM | 2GB | 4GB+ |
| Storage | 10GB | 50GB+ |
| Network | 100 Mbps | 1 Gbps |
| Database | SQLite | PostgreSQL |

## Pre-Installation Checklist

```bash
# Check OS
uname -a

# Check Python version (3.9+)
python3 --version

# Check PHP version (8.1+)
php --version

# Check Node.js (18+)
node --version
npm --version

# Update system
sudo apt update && sudo apt upgrade -y

# Install common dependencies
sudo apt install -y git curl wget build-essential
```

## Backend Setup (Laravel)

### 1. Install PHP & Dependencies

```bash
sudo apt install -y php8.1 php8.1-cli php8.1-common php8.1-fpm php8.1-mbstring php8.1-pgsql php8.1-curl php8.1-json

# Install Composer
curl -sS https://getcomposer.org/installer | sudo php -- --install-dir=/usr/local/bin --filename=composer
```

### 2. Setup Database

#### PostgreSQL Option:
```bash
sudo apt install -y postgresql postgresql-contrib

sudo -u postgres createdb packet_analyzer
sudo -u postgres createuser packet_user -P  # Set password when prompted

# Grant privileges
sudo -u postgres psql -c "GRANT ALL PRIVILEGES ON DATABASE packet_analyzer TO packet_user;"
```

#### SQLite Option:
```bash
# SQLite doesn't require installation, just create DB file
touch storage/packet_analyzer.db
```

### 3. Configure Laravel

```bash
cd backend-api

# Copy environment file
cp .env.example .env

# Edit configuration
nano .env

# Key settings to update:
# APP_DEBUG=false (for production)
# DB_CONNECTION=pgsql (or sqlite)
# DB_DATABASE=packet_analyzer
# DB_USERNAME=packet_user
# DB_PASSWORD=your_password
# ROUTER_AGENT_URL=http://[router-ip]:5000
# ROUTER_AGENT_KEY=your_secure_key

# Generate application key
php artisan key:generate

# Run migrations
php artisan migrate

# Create admin user (optional)
php artisan tinker
# > User::create(['name' => 'Admin', 'email' => 'admin@example.com', 'password' => bcrypt('password')])

# Seed sample data (optional)
php artisan db:seed
```

### 4. Configure Web Server

#### Using Built-in Server (Development):
```bash
php artisan serve --host=0.0.0.0 --port=8000
```

#### Using Nginx (Production):
```bash
sudo apt install -y nginx

sudo nano /etc/nginx/sites-available/packet-analyzer
```

```nginx
server {
    listen 80;
    server_name your_domain.com;
    root /path/to/backend-api/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";
    add_header X-XSS-Protection "1; mode=block";

    index index.html index.php;

    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

```bash
sudo ln -s /etc/nginx/sites-available/packet-analyzer /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl start nginx
sudo systemctl enable nginx
```

## Router Agent Setup (Python)

### 1. Install Python & Dependencies

```bash
sudo apt install -y python3 python3-pip python3-venv libpcap-dev

# Create virtual environment
cd router-agent
python3 -m venv venv
source venv/bin/activate

# Upgrade pip
pip install --upgrade pip

# Install Python dependencies
pip install -r requirements.txt
```

### 2. Configure Agent

```bash
cp .env.example .env
nano .env

# Update settings:
# INTERFACE=eth0  (check with: ip link show)
# BACKEND_URL=http://localhost:8000/api
# ROUTER_AGENT_KEY=your_secure_key
# DEBUG=False (for production)
```

### 3. Setup Systemd Service (Optional)

```bash
sudo nano /etc/systemd/system/packet-analyzer.service
```

```ini
[Unit]
Description=Network Packet Analyzer Router Agent
After=network.target

[Service]
Type=simple
User=root
WorkingDirectory=/path/to/router-agent
ExecStart=/path/to/router-agent/venv/bin/python3 main.py
Restart=always
RestartSec=10

[Install]
WantedBy=multi-user.target
```

```bash
sudo systemctl daemon-reload
sudo systemctl start packet-analyzer
sudo systemctl enable packet-analyzer
sudo systemctl status packet-analyzer
```

## Frontend Setup (React)

### 1. Install Node.js

```bash
# Using NodeSource repository
curl -fsSL https://deb.nodesource.com/setup_18.x | sudo -E bash -
sudo apt install -y nodejs

# Verify installation
node --version
npm --version
```

### 2. Build & Run

```bash
cd frontend-dashboard

# Install dependencies
npm install

# Create .env file
cat > .env << EOF
REACT_APP_API_URL=http://localhost:8000/api
REACT_APP_DEBUG=false
EOF

# Development
npm run dev

# Production build
npm run build

# Preview build
npm run preview
```

### 3. Serve Frontend (Production)

#### Using Nginx:
```bash
sudo nano /etc/nginx/sites-available/packet-analyzer-frontend
```

```nginx
server {
    listen 80;
    server_name dashboard.example.com;
    root /path/to/frontend-dashboard/dist;

    index index.html;

    location / {
        try_files $uri $uri/ /index.html;
    }

    location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf|eot)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }
}
```

```bash
sudo ln -s /etc/nginx/sites-available/packet-analyzer-frontend /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

## Enable SSL/TLS with Let's Encrypt

```bash
sudo apt install -y certbot python3-certbot-nginx

# For backend
sudo certbot --nginx -d backend.example.com

# For frontend
sudo certbot --nginx -d dashboard.example.com

# Auto-renewal
sudo systemctl enable certbot.timer
```

## Verify Installation

```bash
# 1. Check Backend
curl http://localhost:8000/api/health

# 2. Check Router Agent (should be running)
ps aux | grep packet_analyzer

# 3. Check Frontend
curl http://localhost:3000

# 4. Test API endpoint
curl http://localhost:8000/api/devices

# 5. Check logs
tail -f storage/logs/laravel.log  # Backend
tail -f /var/log/packet-analyzer.log  # Agent (if using systemd)
```

## Docker Deployment (Optional)

Create `docker-compose.yml`:

```yaml
version: '3.8'

services:
  postgres:
    image: postgres:15
    environment:
      POSTGRES_DB: packet_analyzer
      POSTGRES_USER: packet_user
      POSTGRES_PASSWORD: secure_password
    volumes:
      - postgres_data:/var/lib/postgresql/data

  backend:
    build: ./backend-api
    ports:
      - "8000:8000"
    environment:
      APP_DEBUG: "false"
      DB_CONNECTION: pgsql
      DB_HOST: postgres
    depends_on:
      - postgres

  frontend:
    build: ./frontend-dashboard
    ports:
      - "3000:80"
    depends_on:
      - backend

volumes:
  postgres_data:
```

```bash
docker-compose up -d
```

## Backup Strategy

```bash
# Backup database
pg_dump packet_analyzer > backup_$(date +%Y%m%d).sql

# Backup configuration
tar -czf config_backup_$(date +%Y%m%d).tar.gz ./backend-api/.env ./router-agent/.env

# Backup traffic logs
tar -czf logs_backup_$(date +%Y%m%d).tar.gz ./storage/logs
```

## Monitoring & Maintenance

```bash
# Monitor disk usage
df -h

# Monitor database
sudo -u postgres psql -d packet_analyzer -c "SELECT COUNT(*) FROM traffic_logs;"

# Clear old logs (older than 30 days)
find ./storage/logs -type f -mtime +30 -delete

# Monitor system resources
top  # or: htop
```

## Troubleshooting

See [TROUBLESHOOTING.md](TROUBLESHOOTING.md) for common issues and solutions.

---

For support, check the main [README.md](README.md)
