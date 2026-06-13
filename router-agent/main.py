#!/usr/bin/env python3
"""
Router Agent Main Application
Orchestrates packet capture, threat detection, and API communication
"""

import time
import logging
import requests
import json
import sys
from threading import Thread, Lock
from datetime import datetime
from typing import Dict, List

from config import Config, logger
from src.packet_capturer import PacketCapture
from src.threat_detector import ThreatDetector


class RouterAgent:
    """Main agent that coordinates all monitoring activities"""
    
    def __init__(self):
        """Initialize router agent"""
        self.config = Config.from_env_file('.env')
        self.packet_capture = PacketCapture(
            interface=self.config.INTERFACE,
            buffer_size=self.config.PACKET_BUFFER_SIZE
        )
        self.threat_detector = ThreatDetector()
        
        self.is_running = False
        self.capture_thread = None
        self.analysis_thread = None
        self.sync_thread = None
        self.lock = Lock()
        
        self.pending_data = {
            'packets': [],
            'threats': [],
        }
        
        logger.info("Router Agent initialized")
    
    def start(self):
        """Start the agent"""
        if self.is_running:
            logger.warning("Agent already running")
            return
        
        self.is_running = True
        logger.info("Starting Router Agent")
        
        # Start capture thread
        self.capture_thread = Thread(target=self._capture_loop, daemon=True)
        self.capture_thread.start()
        
        # Start analysis thread
        self.analysis_thread = Thread(target=self._analysis_loop, daemon=True)
        self.analysis_thread.start()
        
        # Start sync thread (sends data to backend)
        self.sync_thread = Thread(target=self._sync_loop, daemon=True)
        self.sync_thread.start()
        
        logger.info("All threads started")
    
    def stop(self):
        """Stop the agent"""
        self.is_running = False
        self.packet_capture.stop_capture()
        logger.info("Router Agent stopped")
    
    def _capture_loop(self):
        """Continuous packet capture loop"""
        logger.info(f"Capture loop started on {self.config.INTERFACE}")
        
        try:
            self.packet_capture.start_capture()
        except Exception as e:
            logger.error(f"Capture loop error: {e}")
            self.is_running = False
    
    def _analysis_loop(self):
        """Analyze captured packets periodically"""
        logger.info("Analysis loop started")
        
        while self.is_running:
            try:
                time.sleep(self.config.ANALYSIS_INTERVAL)
                
                # Get current buffer
                packets = self.packet_capture.drain_buffer()
                
                for packet in packets:
                    # Analyze for threats
                    threats = self.threat_detector.analyze_packet(packet)
                    self.threat_detector.update_traffic_history(packet)
                    
                    # Store for sync
                    with self.lock:
                        self.pending_data['packets'].append(packet)
                        self.pending_data['threats'].extend(threats)
                
                # Log stats
                device_stats = self.packet_capture.get_device_stats()
                threat_stats = self.threat_detector.get_threat_stats(hours=1)
                
                logger.info(
                    f"Analyzed {len(packets)} packets | "
                    f"Devices: {len(device_stats)} | "
                    f"Threats: {threat_stats['total_threats']}"
                )
                
            except Exception as e:
                logger.error(f"Analysis loop error: {e}")
    
    def _sync_loop(self):
        """Synchronize data with backend periodically"""
        logger.info("Sync loop started")
        
        while self.is_running:
            try:
                    time.sleep(self.config.SYNC_INTERVAL)  # Sync interval from config
                
                with self.lock:
                    if self.pending_data['packets'] or self.pending_data['threats']:
                        self._send_to_backend(self.pending_data)
                        self.pending_data = {'packets': [], 'threats': []}
                
            except Exception as e:
                logger.error(f"Sync loop error: {e}")
    
    def _send_to_backend(self, data: Dict):
        """Send collected data to backend API"""
        try:
            # Prepare data
            payload = {
                'timestamp': datetime.now().isoformat(),
                'packets': data['packets'][:100],  # Batch
                'threats': data['threats'],
                'device_stats': self.packet_capture.get_device_stats(),
            }
            
            # Send to backend
            response = requests.post(
                f"{self.config.BACKEND_URL}/data/ingest",
                json=payload,
                headers={
                    'Authorization': f"Bearer {self.config.ROUTER_AGENT_KEY}",
                    'Content-Type': 'application/json'
                },
                timeout=10
            )
            
            if response.status_code == 200:
                logger.debug(
                    f"Synced {len(data['packets'])} packets, "
                    f"{len(data['threats'])} threats"
                )
            else:
                logger.warning(f"Backend sync failed: {response.status_code}")
                
        except requests.exceptions.RequestException as e:
            logger.warning(f"Backend communication error: {e}")
        except Exception as e:
            logger.error(f"Sync error: {e}")
    
    def get_status(self) -> Dict:
        """Get agent status"""
        return {
            'running': self.is_running,
            'interface': self.config.INTERFACE,
            'packets_captured': len(self.packet_capture.get_buffer()),
            'active_devices': len(self.packet_capture.get_device_stats()),
            'threat_stats': self.threat_detector.get_threat_stats(hours=24),
            'pending_sync': len(self.pending_data['packets']) + len(self.pending_data['threats']),
        }


def main():
    """Main entry point"""
    try:
        logger.info("=" * 60)
        logger.info("Network Packet Analyzer - Router Agent")
        logger.info("=" * 60)
        
        agent = RouterAgent()
        agent.start()
        
        # Keep running
        while agent.is_running:
            time.sleep(1)
        
    except KeyboardInterrupt:
        logger.info("Shutdown signal received")
        agent.stop()
    except Exception as e:
        logger.error(f"Fatal error: {e}")
        sys.exit(1)


if __name__ == '__main__':
    main()
