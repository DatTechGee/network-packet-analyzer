#!/usr/bin/env python3
"""
Network Packet Analyzer - Router Agent
Real-time packet capture, analysis, and threat detection
"""

import os
import sys
import json
import logging
from pathlib import Path

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
    INTERFACE = os.getenv('INTERFACE', 'eth0')  # Network interface to monitor
    PACKET_BUFFER_SIZE = int(os.getenv('PACKET_BUFFER_SIZE', 10000))
    ANALYSIS_INTERVAL = int(os.getenv('ANALYSIS_INTERVAL', 30))  # seconds
    SYNC_INTERVAL = int(os.getenv('SYNC_INTERVAL', 30))  # seconds
    
    # Threat Detection
    ENABLE_IDS = os.getenv('ENABLE_IDS', 'True').lower() == 'true'
    ENABLE_DPI = os.getenv('ENABLE_DPI', 'True').lower() == 'true'
    THREAT_DB_PATH = os.getenv('THREAT_DB_PATH', '/var/lib/packet-analyzer/threats')
    
    # Content Filtering
    ENABLE_CONTENT_FILTER = os.getenv('ENABLE_CONTENT_FILTER', 'True').lower() == 'true'
    BLOCK_ADULT_CONTENT = os.getenv('BLOCK_ADULT_CONTENT', 'False').lower() == 'true'
    BLOCK_GAMBLING = os.getenv('BLOCK_GAMBLING', 'False').lower() == 'true'
    
    # Database
    DB_PATH = os.getenv('DB_PATH', '/var/lib/packet-analyzer/db')
    
    @classmethod
    def from_env_file(cls, env_file='.env'):
        """Load configuration from .env file"""
        if Path(env_file).exists():
            from dotenv import load_dotenv
            load_dotenv(env_file, override=True)

        cls.DEBUG = os.getenv('DEBUG', 'True').lower() == 'true'
        cls.LOG_LEVEL = os.getenv('LOG_LEVEL', 'INFO')

        cls.BACKEND_URL = os.getenv('BACKEND_URL', 'http://localhost:8000/api')
        cls.ROUTER_AGENT_PORT = int(os.getenv('ROUTER_AGENT_PORT', 5000))
        cls.ROUTER_AGENT_KEY = os.getenv('ROUTER_AGENT_KEY', 'default_key')

        cls.INTERFACE = os.getenv('INTERFACE', 'eth0')
        cls.PACKET_BUFFER_SIZE = int(os.getenv('PACKET_BUFFER_SIZE', 10000))
        cls.ANALYSIS_INTERVAL = int(os.getenv('ANALYSIS_INTERVAL', 30))
        cls.SYNC_INTERVAL = int(os.getenv('SYNC_INTERVAL', 30))

        cls.ENABLE_IDS = os.getenv('ENABLE_IDS', 'True').lower() == 'true'
        cls.ENABLE_DPI = os.getenv('ENABLE_DPI', 'True').lower() == 'true'
        cls.THREAT_DB_PATH = os.getenv('THREAT_DB_PATH', '/var/lib/packet-analyzer/threats')

        cls.ENABLE_CONTENT_FILTER = os.getenv('ENABLE_CONTENT_FILTER', 'True').lower() == 'true'
        cls.BLOCK_ADULT_CONTENT = os.getenv('BLOCK_ADULT_CONTENT', 'False').lower() == 'true'
        cls.BLOCK_GAMBLING = os.getenv('BLOCK_GAMBLING', 'False').lower() == 'true'

        cls.DB_PATH = os.getenv('DB_PATH', '/var/lib/packet-analyzer/db')

        return cls

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

print(f"Network Packet Analyzer - Router Agent")
print(f"Version: 1.0.0")
print(f"Environment: {'Debug' if Config.DEBUG else 'Production'}")
print(f"Interface: {Config.INTERFACE}")
print(f"Backend URL: {Config.BACKEND_URL}")
