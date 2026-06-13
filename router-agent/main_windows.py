#!/usr/bin/env python3
"""
Windows Router Agent Main Application
Cross-platform orchestration using pyshark
"""

import time
import logging
import requests
import json
import sys
import os
from pathlib import Path
from threading import Thread, Lock
from datetime import datetime, timezone
from typing import Dict, List

from config_windows import Config, logger

# Import appropriate packet capture module
try:
    from src.packet_capturer_windows import PacketCaptureWindows
    PACKET_CAPTURE_CLASS = PacketCaptureWindows
except ImportError:
    logger.error("Failed to import PacketCaptureWindows")
    sys.exit(1)

from src.threat_detector import ThreatDetector
from src.domain_blocker import DomainBlocker


class RouterAgentWindows:
    """Windows-compatible router agent using pyshark"""
    
    def __init__(self):
        """Initialize router agent"""
        project_dir = Path(__file__).resolve().parent
        env_file = project_dir / '.env.windows' if (project_dir / '.env.windows').exists() else project_dir / '.env'
        self.config = Config.from_env_file(str(env_file))
        # Create packet capture instances for each interface
        self.packet_captures = {}
        for interface in self.config.INTERFACES:
            cap = PACKET_CAPTURE_CLASS(
                interface=interface,
                buffer_size=self.config.PACKET_BUFFER_SIZE
            )
            try:
                available = cap.list_interfaces()
                logger.info(f"Available adapters for desired '{interface}': {available}")
                if interface not in available:
                    match = None
                    for adapter in available:
                        if interface.lower() in adapter.lower() or adapter.lower() in interface.lower():
                            match = adapter
                            break
                    if match:
                        logger.info(f"Resolved interface '{interface}' -> '{match}'")
                        cap = PACKET_CAPTURE_CLASS(
                            interface=match,
                            buffer_size=self.config.PACKET_BUFFER_SIZE
                        )
            except Exception:
                logger.debug(f"Could not list interfaces for {interface}")

            self.packet_captures[interface] = cap
        
        self.threat_detector = ThreatDetector()
        
        self.domain_blocker = DomainBlocker(
            backend_url=self.config.BACKEND_URL,
            agent_key=self.config.ROUTER_AGENT_KEY
        )
        
        self.is_running = False
        self.capture_threads = {}
        self.analysis_thread = None
        self.sync_thread = None
        self.lock = Lock()
        
        self.pending_data = {
            'packets': [],
            'threats': [],
        }
        
        logger.info("Router Agent (Windows) initialized")
        logger.info(f"Interfaces: {', '.join(self.config.INTERFACES)}")
        logger.info(f"Backend URL: {self.config.BACKEND_URL}")
    
    def start(self):
        """Start the agent"""
        if self.is_running:
            logger.warning("Agent already running")
            return
        
        self.is_running = True
        logger.info("Starting Router Agent")
        
        # Start capture threads for each interface
        for interface in self.config.INTERFACES:
            thread = Thread(target=self._capture_loop, args=(interface,), daemon=True)
            self.capture_threads[interface] = thread
            thread.start()
            logger.info(f"Started capture thread for interface: {interface}")
        
        # Start analysis thread
        self.analysis_thread = Thread(target=self._analysis_loop, daemon=True)
        self.analysis_thread.start()
        
        # Start sync thread (sends data to backend)
        self.sync_thread = Thread(target=self._sync_loop, daemon=True)
        self.sync_thread.start()
        
        # Start domain blocker
        self.domain_blocker.start()
        
        logger.info(f"All threads started ({len(self.config.INTERFACES)} interfaces)")
    
    def stop(self):
        """Stop the agent"""
        self.is_running = False
        self.domain_blocker.stop()
        for interface, capture in self.packet_captures.items():
            capture.stop_capture()
            logger.info(f"Stopped capture on {interface}")
        logger.info("Router Agent stopped")
    
    def _capture_loop(self, interface: str):
        """Continuous packet capture loop for a specific interface"""
        logger.info(f"Capture loop started on {interface}")
        
        try:
            packet_capture = self.packet_captures[interface]
            packet_capture.start_capture()
        except Exception as e:
            logger.error(f"Capture loop error on {interface}: {e}")
            try:
                packet_capture.stop_capture()
            except Exception:
                pass
            # Keep the agent running for other interfaces.
            return
    
    def _analysis_loop(self):
        """Analyze captured packets periodically from all interfaces"""
        logger.info("Analysis loop started")
        
        while self.is_running:
            try:
                time.sleep(self.config.ANALYSIS_INTERVAL)
                
                all_packets = []
                all_device_stats = {}
                
                # Collect packets from all interfaces
                for interface, packet_capture in self.packet_captures.items():
                    packets = packet_capture.drain_buffer()
                    if packets:
                        all_packets.extend(packets)
                    
                    # Collect device stats from each interface
                    stats = packet_capture.get_device_stats()
                    all_device_stats.update(stats)
                
                # Analyze collected packets
                if all_packets:
                    for packet in all_packets:
                        # Check if domain is blocked
                        domain = packet.get('domain', '')
                        src_ip = packet.get('source_ip', '')
                        if domain and self.domain_blocker.is_blocked(domain):
                            self.pending_data['threats'].append({
                                'type': 'site_blocked',
                                'severity': 'high',
                                'source_ip': src_ip,
                                'description': f"Blocked domain accessed: {domain}",
                                'details': {'domain': domain, 'action': 'blocked'},
                                'timestamp': packet.get('timestamp', datetime.now(timezone.utc).isoformat()),
                            })
                            logger.warning(f"BLOCKED: {src_ip} tried to access {domain}")
                            continue
                        
                        # Analyze for threats
                        threats = self.threat_detector.analyze_packet(packet)
                        self.threat_detector.update_traffic_history(packet)
                        
                        # Store for sync
                        with self.lock:
                            self.pending_data['packets'].append(packet)
                            self.pending_data['threats'].extend(threats)
                    
                    # Log stats
                    threat_stats = self.threat_detector.get_threat_stats(hours=1)
                    
                    logger.info(
                        f"Analyzed {len(all_packets)} packets across {len(self.config.INTERFACES)} interfaces | "
                        f"Devices: {len(all_device_stats)} | "
                        f"Threats: {threat_stats['total_threats']}"
                    )
                    
            except Exception as e:
                logger.error(f"Analysis loop error: {e}")
    
    def _sync_loop(self):
        """Synchronize data with backend periodically"""
        logger.info("Sync loop started")

        while self.is_running:
            try:
                time.sleep(self.config.SYNC_INTERVAL)

                with self.lock:
                    success = self._send_to_backend(self.pending_data)
                    if success:
                        self.pending_data = {'packets': [], 'threats': []}

            except Exception as e:
                logger.error(f"Sync loop error: {e}")
    
    def _send_to_backend(self, data: Dict) -> bool:
        """Send collected data to backend API. Returns True on success."""
        try:
            # Prepare data
            all_device_stats = {}
            for interface, packet_capture in self.packet_captures.items():
                stats = packet_capture.get_device_stats()
                all_device_stats.update(stats)

            # Always send at least a heartbeat to keep device last_seen fresh
            host_ip = None
            for pc in self.packet_captures.values():
                host_ip = pc._get_host_ip()
                if host_ip:
                    break
            if host_ip and host_ip not in all_device_stats:
                host_mac = None
                for pc in self.packet_captures.values():
                    host_mac = pc._get_host_mac()
                    if host_mac:
                        break
                all_device_stats[host_ip] = {
                    'packets': 0,
                    'bytes_sent': 0,
                    'bytes_received': 0,
                    'last_seen': datetime.now(timezone.utc).isoformat(),
                    'mac_address': host_mac or '',
                }

            # Skip only if truly nothing to send
            if not data['packets'] and not data['threats'] and not all_device_stats:
                return True

            payload = {
                'timestamp': datetime.now(timezone.utc).isoformat(),
                'packets': data['packets'][:100],  # Batch
                'threats': data['threats'],
                'device_stats': all_device_stats,
            }

            # Send to backend
            response = requests.post(
                f"{self.config.BACKEND_URL}/data/ingest",
                json=payload,
                headers={
                    'Authorization': f"Bearer {self.config.ROUTER_AGENT_KEY}",
                    'Content-Type': 'application/json'
                },
                timeout=60
            )

            if response.status_code == 200:
                logger.debug(
                    f"Synced {len(data['packets'])} packets, "
                    f"{len(data['threats'])} threats"
                )
                return True
            else:
                try:
                    response_body = response.text
                except Exception:
                    response_body = '<unreadable response body>'
                logger.warning(f"Backend sync failed: {response.status_code} - {response_body}")
                return False

        except requests.exceptions.RequestException as e:
            logger.warning(f"Backend communication error: {e}")
            return False
        except Exception as e:
            logger.error(f"Sync error: {e}")
            return False
    
    def get_status(self) -> Dict:
        """Get agent status"""
        total_packets = 0
        total_devices = 0
        for packet_capture in self.packet_captures.values():
            total_packets += len(packet_capture.get_buffer())
            total_devices += len(packet_capture.get_device_stats())
        
        return {
            'running': self.is_running,
            'interfaces': self.config.INTERFACES,
            'packets_captured': total_packets,
            'active_devices': total_devices,
            'threat_stats': self.threat_detector.get_threat_stats(hours=24),
            'pending_sync': len(self.pending_data['packets']) + len(self.pending_data['threats']),
        }


def main():
    """Main entry point"""
    try:
        logger.info("=" * 70)
        logger.info("Network Packet Analyzer - Windows Router Agent")
        logger.info("Cross-Platform Packet Capture (Requires Wireshark)")
        logger.info("=" * 70)
        
        agent = RouterAgentWindows()
        agent.start()
        
        # Keep running
        while agent.is_running:
            time.sleep(1)
        
    except KeyboardInterrupt:
        logger.info("Shutdown signal received")
        if 'agent' in locals():
            agent.stop()
    except Exception as e:
        logger.error(f"Fatal error: {e}")
        sys.exit(1)


if __name__ == '__main__':
    main()
