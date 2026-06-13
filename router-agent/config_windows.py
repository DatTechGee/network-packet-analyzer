#!/usr/bin/env python3
"""
Network Packet Analyzer - Windows Router Agent
Uses pyshark (Wireshark) for cross-platform packet capture
Works with Windows, macOS, and Linux
"""

import os
import sys
import json
import logging
import shutil
from pathlib import Path
from datetime import datetime

# Configuration
class Config:
    """Application configuration"""
    DEBUG = os.getenv('DEBUG', 'True').lower() == 'true'
    LOG_LEVEL = os.getenv('LOG_LEVEL', 'INFO')
    
    # API Configuration
    BACKEND_URL = os.getenv('BACKEND_URL', 'http://localhost:8000/api')
    ROUTER_AGENT_PORT = int(os.getenv('ROUTER_AGENT_PORT', 5000))
    ROUTER_AGENT_KEY = os.getenv('ROUTER_AGENT_KEY', 'default_key')
    
    # Packet Capture
    # Support multiple interfaces (comma-separated): Ethernet, Wi-Fi, Ring
    INTERFACES_STR = os.getenv('INTERFACES', 'Ethernet,Wi-Fi')
    INTERFACES = [i.strip() for i in INTERFACES_STR.split(',') if i.strip()]
    PACKET_BUFFER_SIZE = int(os.getenv('PACKET_BUFFER_SIZE', 10000))
    ANALYSIS_INTERVAL = int(os.getenv('ANALYSIS_INTERVAL', 5))  # seconds
    SYNC_INTERVAL = int(os.getenv('SYNC_INTERVAL', 3))  # seconds (lower = more real-time)
    
    # Threat Detection
    ENABLE_IDS = os.getenv('ENABLE_IDS', 'True').lower() == 'true'
    ENABLE_DPI = os.getenv('ENABLE_DPI', 'True').lower() == 'true'
    THREAT_DB_PATH = os.getenv('THREAT_DB_PATH', './threats')
    
    # Content Filtering
    ENABLE_CONTENT_FILTER = os.getenv('ENABLE_CONTENT_FILTER', 'True').lower() == 'true'
    BLOCK_ADULT_CONTENT = os.getenv('BLOCK_ADULT_CONTENT', 'False').lower() == 'true'
    BLOCK_GAMBLING = os.getenv('BLOCK_GAMBLING', 'False').lower() == 'true'
    
    # Database
    DB_PATH = os.getenv('DB_PATH', './packet_analyzer_db')
    TSHARK_PATH = os.getenv('TSHARK_PATH', '')

    @classmethod
    def _project_dir(cls):
        return Path(__file__).resolve().parent

    @classmethod
    def _resolve_env_file(cls, preferred=None):
        candidates = []
        if preferred:
            preferred_path = Path(preferred)
            candidates.append(preferred_path if preferred_path.is_absolute() else cls._project_dir() / preferred_path)

        candidates.extend([
            cls._project_dir() / '.env.windows',
            cls._project_dir() / '.env',
            Path.cwd() / '.env.windows',
            Path.cwd() / '.env',
        ])

        for candidate in candidates:
            if candidate.exists():
                return str(candidate)

        return str(cls._project_dir() / '.env')

    @classmethod
    def _detect_tshark_path(cls):
        """Find a local Wireshark tshark executable on Windows."""
        if cls.TSHARK_PATH:
            return cls.TSHARK_PATH

        which_tshark = shutil.which('tshark')
        if which_tshark:
            return which_tshark

        candidates = [
            Path(r'C:\Program Files\Wireshark\tshark.exe'),
            Path(r'C:\Program Files (x86)\Wireshark\tshark.exe'),
            Path(r'C:\ProgramData\chocolatey\bin\tshark.exe'),
            Path(r'C:\Wireshark\tshark.exe'),
        ]

        for candidate in candidates:
            if candidate.exists():
                return str(candidate)

        return ''

    @classmethod
    def _select_capture_interfaces(cls, interfaces):
        """Prefer the real Wi-Fi hotspot interface to avoid duplicate virtual-adapter captures."""
        capture_all = os.getenv('CAPTURE_ALL_INTERFACES', 'False').lower() == 'true'
        if capture_all:
            return interfaces

        # Try to find the most likely active capture interface.
        # Prefer non-virtual adapters in this order: Wi-Fi > Ethernet > Local Area Connection
        wifi_like = [iface for iface in interfaces if 'wi-fi' in iface.lower() or 'wireless' in iface.lower()]
        eth_like  = [iface for iface in interfaces if 'ethernet' in iface.lower()]
        loc_like  = [iface for iface in interfaces if 'local area connection' in iface.lower()]

        for candidates in (wifi_like, eth_like, loc_like, interfaces):
            if candidates:
                return candidates[:1]

        return interfaces[:1] if interfaces else []
    
    @classmethod
    def from_env_file(cls, env_file='.env'):
        """Load configuration from .env file"""
        resolved_env_file = cls._resolve_env_file(env_file)
        if Path(resolved_env_file).exists():
            from dotenv import load_dotenv
            load_dotenv(resolved_env_file, override=True)
        cls.DEBUG = os.getenv('DEBUG', 'True').lower() == 'true'
        cls.LOG_LEVEL = os.getenv('LOG_LEVEL', 'INFO')

        cls.BACKEND_URL = os.getenv('BACKEND_URL', 'http://localhost:8000/api')
        cls.ROUTER_AGENT_PORT = int(os.getenv('ROUTER_AGENT_PORT', 5000))
        cls.ROUTER_AGENT_KEY = os.getenv('ROUTER_AGENT_KEY', 'default_key')

        cls.INTERFACES_STR = os.getenv('INTERFACES', os.getenv('INTERFACE', 'Ethernet,Wi-Fi'))
        cls.INTERFACES = cls._select_capture_interfaces([i.strip() for i in cls.INTERFACES_STR.split(',') if i.strip()])
        cls.PACKET_BUFFER_SIZE = int(os.getenv('PACKET_BUFFER_SIZE', 10000))
        cls.ANALYSIS_INTERVAL = int(os.getenv('ANALYSIS_INTERVAL', 5))
        cls.SYNC_INTERVAL = int(os.getenv('SYNC_INTERVAL', 5))

        cls.ENABLE_IDS = os.getenv('ENABLE_IDS', 'True').lower() == 'true'
        cls.ENABLE_DPI = os.getenv('ENABLE_DPI', 'True').lower() == 'true'
        cls.THREAT_DB_PATH = os.getenv('THREAT_DB_PATH', './threats')

        cls.ENABLE_CONTENT_FILTER = os.getenv('ENABLE_CONTENT_FILTER', 'True').lower() == 'true'
        cls.BLOCK_ADULT_CONTENT = os.getenv('BLOCK_ADULT_CONTENT', 'False').lower() == 'true'
        cls.BLOCK_GAMBLING = os.getenv('BLOCK_GAMBLING', 'False').lower() == 'true'

        cls.DB_PATH = os.getenv('DB_PATH', './packet_analyzer_db')
        cls.TSHARK_PATH = os.getenv('TSHARK_PATH', '') or cls._detect_tshark_path()

        return cls


Config.from_env_file(Config._resolve_env_file())

# Setup logging
def setup_logging():
    """Configure logging"""
    log_format = '%(asctime)s - %(name)s - %(levelname)s - %(message)s'
    logging.basicConfig(
        level=getattr(logging, Config.LOG_LEVEL),
        format=log_format
    )
    return logging.getLogger(__name__)

logger = setup_logging()

print(f"{'='*60}")
print(f"Network Packet Analyzer - Windows Router Agent")
print(f"Version: 1.0.0 (Windows Compatible)")
print(f"Environment: {'Debug' if Config.DEBUG else 'Production'}")
print(f"Interfaces: {', '.join(Config.INTERFACES)}")
print(f"Backend URL: {Config.BACKEND_URL}")
print(f"TShark path: {Config.TSHARK_PATH or 'not found'}")
print(f"{'='*60}")
