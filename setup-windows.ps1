param(
    [ValidateSet('all', 'backend', 'agent', 'frontend', 'check', 'start', 'stop')]
    [string]$Action = 'all',
    [switch]$Help
)

if ($Help) {
    @'
Network Packet Analyzer - Windows Setup Script

Usage: .\setup-windows.ps1 [Action] [Options]

Actions:
  all              - Setup everything (default)
  backend          - Setup backend API only
  agent            - Setup router agent only
  frontend         - Setup frontend dashboard only
  check            - Check prerequisites
  start            - Start all services
  stop             - Stop all services

Examples:
  .\setup-windows.ps1 -Action check
  .\setup-windows.ps1 -Action backend
  .\setup-windows.ps1 -Help
'@ | Write-Host
    exit 0
}

function Write-Success {
    param([string]$Message)
    Write-Host ("[OK] $Message") -ForegroundColor Green
}

function Write-Error-Custom {
    param([string]$Message)
    Write-Host ("[ERR] $Message") -ForegroundColor Red
}

function Write-Warning-Custom {
    param([string]$Message)
    Write-Host ("[WARN] $Message") -ForegroundColor Yellow
}

function Write-Info {
    param([string]$Message)
    Write-Host ("[INFO] $Message") -ForegroundColor Cyan
}

function Test-Admin {
    $principal = New-Object Security.Principal.WindowsPrincipal([Security.Principal.WindowsIdentity]::GetCurrent())
    if (-not $principal.IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)) {
        Write-Warning-Custom 'Not running as Administrator. Packet capture may not work without admin privileges.'
        return $false
    }

    return $true
}

function Test-Prerequisites {
    Write-Info 'Checking prerequisites...'

    $missing = @()

    foreach ($tool in @('python', 'node', 'composer', 'git')) {
        if (Get-Command $tool -ErrorAction SilentlyContinue) {
            Write-Success ("$tool found")
        } else {
            Write-Error-Custom ("$tool not found")
            $missing += $tool
        }
    }

    if (Get-Command php -ErrorAction SilentlyContinue) {
        Write-Success 'php found'
    } else {
        Write-Error-Custom 'php not found'
        $missing += 'php'
    }

    $tsharkCommand = Get-Command tshark -ErrorAction SilentlyContinue
    $wiresharkPaths = @(
        'C:\Program Files\Wireshark\tshark.exe',
        'C:\Program Files (x86)\Wireshark\tshark.exe'
    )

    if ($tsharkCommand) {
        Write-Success 'Wireshark/Tshark found on PATH'
    } elseif ($wiresharkPaths | Where-Object { Test-Path $_ }) {
        Write-Success 'Wireshark found in standard install folder'
    } else {
        Write-Error-Custom 'Wireshark not found'
        $missing += 'Wireshark'
    }

    if ($missing.Count -gt 0) {
        Write-Host ''
        Write-Error-Custom ('Missing: ' + ($missing -join ', '))
        return $false
    }

    Write-Success 'All prerequisites are installed'
    return $true
}

function Copy-EnvFile {
    param(
        [string]$Source,
        [string]$Destination
    )

    if (-not (Test-Path $Destination)) {
        Copy-Item $Source $Destination
        Write-Success ("Created $Destination")
    } else {
        Write-Warning-Custom ("$Destination already exists (skipping)")
    }
}

function Setup-Backend {
    Write-Info 'Setting up Backend API...'

    if (-not (Test-Path 'backend-api')) {
        Write-Error-Custom 'Backend directory not found'
        return $false
    }

    Push-Location 'backend-api'
    try {
        Copy-EnvFile '.env.example' '.env'

        if (Get-Command composer -ErrorAction SilentlyContinue) {
            Write-Info 'Installing Composer dependencies...'
            composer install
            if ($LASTEXITCODE -ne 0) {
                Write-Error-Custom 'Composer install failed'
                return $false
            }
        }

        if (Get-Command php -ErrorAction SilentlyContinue) {
            Write-Info 'Generating Laravel key...'
            php artisan key:generate
            Write-Info 'Running migrations...'
            php artisan migrate --force
        }

        Write-Success 'Backend setup completed'
        return $true
    } finally {
        Pop-Location
    }
}

function Setup-Agent {
    Write-Info 'Setting up Router Agent...'

    if (-not (Test-Path 'router-agent')) {
        Write-Error-Custom 'Router agent directory not found'
        return $false
    }

    Push-Location 'router-agent'
    try {
        Copy-EnvFile '.env.example' '.env'

        if (-not (Test-Path 'venv')) {
            Write-Info 'Creating Python virtual environment...'
            python -m venv venv
            if ($LASTEXITCODE -ne 0) {
                Write-Error-Custom 'Python virtual environment creation failed'
                return $false
            }
        }

        $pipPath = Join-Path $PWD 'venv\Scripts\pip.exe'
        if (Test-Path $pipPath) {
            Write-Info 'Installing Python dependencies...'
            & $pipPath install -r requirements-windows.txt
            if ($LASTEXITCODE -ne 0) {
                Write-Error-Custom 'Python dependencies install failed'
                return $false
            }
        } else {
            Write-Error-Custom 'pip.exe not found inside venv'
            return $false
        }

        Write-Success 'Router Agent setup completed'
        return $true
    } finally {
        Pop-Location
    }
}

function Setup-Frontend {
    Write-Info 'Setting up Frontend Dashboard...'

    if (-not (Test-Path 'frontend-dashboard')) {
        Write-Error-Custom 'Frontend directory not found'
        return $false
    }

    Push-Location 'frontend-dashboard'
    try {
        Write-Info 'Installing NPM dependencies...'
        npm install
        if ($LASTEXITCODE -ne 0) {
            Write-Error-Custom 'NPM install failed'
            return $false
        }

        Write-Success 'Frontend setup completed'
        return $true
    } finally {
        Pop-Location
    }
}

function Start-Services {
    Write-Info 'Starting services...'

    Start-Process -FilePath 'php.exe' -ArgumentList @('artisan', 'serve') -WorkingDirectory (Join-Path $PWD 'backend-api')
    Start-Process -FilePath 'powershell.exe' -ArgumentList @('-NoProfile', '-ExecutionPolicy', 'Bypass', '-Command', "Set-Location 'router-agent'; .\venv\Scripts\Activate.ps1; python main_windows.py") -WorkingDirectory (Join-Path $PWD 'router-agent')
    Start-Process -FilePath 'npm.cmd' -ArgumentList @('run', 'dev') -WorkingDirectory (Join-Path $PWD 'frontend-dashboard')

    Write-Success 'Services started'
    Write-Host ''
    Write-Host 'Access points:' -ForegroundColor Cyan
    Write-Host '  Backend:  http://localhost:8000/api' -ForegroundColor Green
    Write-Host '  Frontend: http://localhost:5173' -ForegroundColor Green
    Write-Host '  Agent:    Running in background' -ForegroundColor Green
}

function Stop-Services {
    Write-Info 'Stopping related services...'
    Get-Process php, python, node -ErrorAction SilentlyContinue | Stop-Process -Force
    Write-Success 'Stop request sent'
}

Write-Host ''
Write-Host '---------------------------------------------------------------' -ForegroundColor Cyan
Write-Host 'Network Packet Analyzer - Windows Setup Script' -ForegroundColor Cyan
Write-Host 'Version 1.0.0' -ForegroundColor Cyan
Write-Host '---------------------------------------------------------------' -ForegroundColor Cyan
Write-Host ''

switch ($Action) {
    'check' {
        Test-Admin | Out-Null
        Test-Prerequisites
    }
    'backend' {
        if (Test-Prerequisites) { Setup-Backend }
    }
    'agent' {
        if (Test-Prerequisites) { Setup-Agent }
    }
    'frontend' {
        if (Test-Prerequisites) { Setup-Frontend }
    }
    'start' {
        Start-Services
    }
    'stop' {
        Stop-Services
    }
    'all' {
        if (Test-Prerequisites) {
            Setup-Backend | Out-Null
            Write-Host ''
            Setup-Agent | Out-Null
            Write-Host ''
            Setup-Frontend | Out-Null
            Write-Success 'All components set up successfully'
        }
    }
    default {
        Write-Error-Custom ("Unknown action: $Action")
        Write-Host 'Use -Help for usage information'
    }
}

Write-Host ''
