#!/usr/bin/env python3
"""
Threat Detection Module
Analyzes traffic for security threats and anomalies
"""

import logging
import json
import re
import time
from datetime import datetime, timedelta, timezone
from typing import Dict, List, Optional, Tuple
from collections import defaultdict
from enum import Enum

logger = logging.getLogger(__name__)


class ThreatLevel(Enum):
    """Threat severity levels"""
    LOW = "low"
    MEDIUM = "medium"
    HIGH = "high"
    CRITICAL = "critical"


class ThreatType(Enum):
    """Types of threats"""
    PORT_SCAN = "port_scan"
    DDOS_ATTEMPT = "ddos_attempt"
    MALWARE_SIGNATURE = "malware_signature"
    SUSPICIOUS_DNS = "suspicious_dns"
    BRUTE_FORCE = "brute_force"
    DATA_EXFILTRATION = "data_exfiltration"
    VPN_DETECTION = "vpn_detection"
    ANOMALOUS_TRAFFIC = "anomalous_traffic"
    ARP_SPOOFING = "arp_spoofing"
    MAC_CHANGE = "mac_change"
    EXCESSIVE_CONNECTIONS = "excessive_connections"


class ThreatDetector:
    """Detects security threats in network traffic"""
    
    # VPN/Proxy detection
    COMMON_VPN_PORTS = {1194, 500, 1701, 1723, 8443}
    VPN_PROTOCOLS = {'OpenVPN', 'WireGuard', 'PPTP', 'L2TP', 'IKEv2'}
    
    # Suspicious domains/patterns
    MALWARE_PATTERNS = [
        r'malware', r'botnet', r'phishing', r'ransomware',
        r'trojan', r'virus', r'exploit', r'shellcode'
    ]

    UNSAFE_SITE_MARKERS = [
        'phishing',
        'malware',
        'ransomware',
        'trojan',
        'virus',
        'exploit',
        'shellcode',
        'fake-login',
        'secure-update',
        'verify-account',
        'account-update',
    ]
    
    def __init__(self):
        """Initialize threat detector"""
        self.device_traffic_history = defaultdict(list)
        self.threat_history = []
        self.max_history = 10000
        self.ip_mac_map = {}
        self.connection_attempts = defaultdict(list)
        
    def analyze_packet(self, packet_data: Dict) -> List[Dict]:
        """
        Analyze packet for threats
        
        Args:
            packet_data: Packet information from capturer
            
        Returns:
            List of detected threats
        """
        threats = []
        
        source_ip = packet_data.get('source_ip')
        source_mac = packet_data.get('source_mac')

        # 1. ARP Spoofing and MAC Change detection
        if source_ip and source_mac:
            source_mac_normalized = source_mac.lower().replace('-', ':')
            if source_ip in self.ip_mac_map:
                old_mac = self.ip_mac_map[source_ip]
                if old_mac != source_mac_normalized:
                    # Gateway check: typically ends with .1
                    is_gateway = source_ip.endswith('.1')
                    if is_gateway:
                        threats.append(self._create_threat(
                            ThreatType.ARP_SPOOFING,
                            ThreatLevel.CRITICAL,
                            f"ARP Spoofing detected! Gateway IP {source_ip} mapped to a new MAC address: {source_mac_normalized} (was: {old_mac})",
                            packet_data
                        ))
                    else:
                        threats.append(self._create_threat(
                            ThreatType.MAC_CHANGE,
                            ThreatLevel.MEDIUM,
                            f"MAC address change detected for IP {source_ip}: {source_mac_normalized} (was: {old_mac})",
                            packet_data
                        ))
            
            # Check if MAC maps to the gateway IP but device IP is not gateway (gateway impersonation)
            for ip, mac in self.ip_mac_map.items():
                if mac == source_mac_normalized and ip != source_ip and ip.endswith('.1'):
                    threats.append(self._create_threat(
                        ThreatType.ARP_SPOOFING,
                        ThreatLevel.CRITICAL,
                        f"ARP Spoofing! Device {source_ip} with MAC {source_mac_normalized} is spoofing gateway IP {ip}",
                        packet_data
                    ))
                    
            self.ip_mac_map[source_ip] = source_mac_normalized

        # 2. Excessive connection attempts detection (e.g. TCP SYN scan / brute force)
        if source_ip:
            is_conn_start = False
            if packet_data.get('protocol') == 'TCP' and 'SYN' in (packet_data.get('flags') or ''):
                is_conn_start = True
            elif packet_data.get('protocol') == 'UDP':
                is_conn_start = True

            if is_conn_start:
                now_sec = time.time()
                self.connection_attempts[source_ip].append(now_sec)
                
                # Filter to last 10 seconds
                recent = [t for t in self.connection_attempts[source_ip] if now_sec - t < 10]
                self.connection_attempts[source_ip] = recent
                
                if len(recent) > 80:
                    # Check cooldown: only alert once every 60s per device
                    recent_threats = [t for t in self.threat_history 
                                      if t['type'] == ThreatType.EXCESSIVE_CONNECTIONS.value
                                      and t['source_ip'] == source_ip
                                      and (datetime.now(timezone.utc) - datetime.fromisoformat(t['timestamp'])).seconds < 60]
                    if not recent_threats:
                        threats.append(self._create_threat(
                            ThreatType.EXCESSIVE_CONNECTIONS,
                            ThreatLevel.HIGH,
                            f"Excessive connection attempts: {source_ip} initiated {len(recent)} connection packets in 10 seconds.",
                            packet_data
                        ))

        # Port scanning detection
        if self._is_port_scan(packet_data):
            threats.append(self._create_threat(
                ThreatType.PORT_SCAN,
                ThreatLevel.HIGH,
                f"Port scanning from {packet_data.get('source_ip')}",
                packet_data
            ))
        
        # DDoS detection
        if self._is_ddos_attempt(packet_data):
            threats.append(self._create_threat(
                ThreatType.DDOS_ATTEMPT,
                ThreatLevel.CRITICAL,
                f"Potential DDoS attack from {packet_data.get('source_ip')}",
                packet_data
            ))
        
        # Suspicious DNS
        if self._is_suspicious_dns(packet_data):
            threats.append(self._create_threat(
                ThreatType.SUSPICIOUS_DNS,
                ThreatLevel.MEDIUM,
                f"Suspicious DNS query to {packet_data.get('domain')}",
                packet_data
            ))

        if self._is_unsafe_site(packet_data):
            threats.append(self._create_threat(
                ThreatType.MALWARE_SIGNATURE,
                ThreatLevel.HIGH,
                self._unsafe_site_description(packet_data),
                packet_data
            ))
        
        # VPN detection
        if self._is_vpn_detected(packet_data):
            threats.append(self._create_threat(
                ThreatType.VPN_DETECTION,
                ThreatLevel.LOW,
                "VPN/Proxy usage detected",
                packet_data
            ))
        
        # Data exfiltration
        if self._is_data_exfiltration(packet_data):
            threats.append(self._create_threat(
                ThreatType.DATA_EXFILTRATION,
                ThreatLevel.HIGH,
                "Suspicious large data transfer detected",
                packet_data
            ))
        
        return threats
    
    def _is_port_scan(self, packet_data: Dict) -> bool:
        """Detect port scanning activity"""
        source_ip = packet_data.get('source_ip')
        if not source_ip:
            return False
        
        # Check if source has contacted multiple ports in short time
        recent_packets = [
            p for p in self.device_traffic_history[source_ip][-100:]
            if (datetime.now(timezone.utc) - datetime.fromisoformat(p['timestamp'])).seconds < 60
        ]
        
        unique_ports = set(p.get('destination_port') for p in recent_packets if p.get('destination_port'))
        
        # Keep this conservative for home networks.
        return len(unique_ports) > 40 and len(recent_packets) > 120
    
    def _is_ddos_attempt(self, packet_data: Dict) -> bool:
        """Detect DDoS attack patterns"""
        packet_count = sum(1 for p in self.device_traffic_history.get(
            packet_data.get('source_ip'), [])[-1000:])
        
        size = packet_data.get('size', 0)
        
        # Only flag extreme bursts to avoid false positives on normal browsing.
        return packet_count > 5000 and size < 80
    
    def _is_suspicious_dns(self, packet_data: Dict) -> bool:
        """Detect suspicious DNS queries"""
        domain = packet_data.get('domain', '').lower()
        protocol = packet_data.get('protocol', '')
        
        if protocol != 'DNS' or not domain:
            return False
        
        # Check for malware patterns
        for pattern in self.MALWARE_PATTERNS:
            if re.search(pattern, domain):
                return True
        
        return False

    def _is_unsafe_site(self, packet_data: Dict) -> bool:
        """Detect unsafe websites from DNS, HTTP, or TLS hostnames."""
        domain = str(packet_data.get('domain') or packet_data.get('hostname') or '').lower().strip()
        if not domain:
            return False

        return any(marker in domain for marker in self.UNSAFE_SITE_MARKERS)

    def _unsafe_site_description(self, packet_data: Dict) -> str:
        domain = packet_data.get('domain') or packet_data.get('hostname')
        if domain:
            return f"Unsafe site visited: {domain}"
        return "Unsafe site visited"
    
    def _is_vpn_detected(self, packet_data: Dict) -> bool:
        """Detect VPN/Proxy usage"""
        dest_port = packet_data.get('destination_port')
        protocol = packet_data.get('protocol', '')
        
        port_match = dest_port in self.COMMON_VPN_PORTS if dest_port else False
        protocol_match = any(vpn in protocol for vpn in self.VPN_PROTOCOLS)
        
        return port_match or protocol_match
    
    def _is_data_exfiltration(self, packet_data: Dict) -> bool:
        """Detect suspicious data transfer patterns"""
        source_ip = packet_data.get('source_ip')
        if not source_ip:
            return False
        
        # Check if unusually large data transfer
        total_bytes = sum(p.get('size', 0) for p in self.device_traffic_history[source_ip][-100:])
        
        return total_bytes > 500 * 1024 * 1024  # 500MB in short time
    
    def _create_threat(self, threat_type: ThreatType, level: ThreatLevel,
                      description: str, packet_data: Dict) -> Dict:
        """Create threat record"""
        threat = {
            'timestamp': datetime.now(timezone.utc).isoformat(),
            'type': threat_type.value,
            'level': level.value,
            'description': description,
            'source_ip': packet_data.get('source_ip'),
            'destination_ip': packet_data.get('destination_ip'),
            'source_port': packet_data.get('source_port'),
            'destination_port': packet_data.get('destination_port'),
            'protocol': packet_data.get('protocol'),
            'metadata': self._build_metadata(packet_data),
            'packet_info': packet_data,
        }
        
        self.threat_history.append(threat)
        
        # Limit history size
        if len(self.threat_history) > self.max_history:
            self.threat_history = self.threat_history[-self.max_history:]
        
        return threat

    def _build_metadata(self, packet_data: Dict) -> Dict:
        metadata = {}

        for key in ('domain', 'hostname', 'browser', 'user_agent', 'app_name'):
            value = packet_data.get(key)
            if value:
                metadata[key] = value

        return metadata
    
    def update_traffic_history(self, packet_data: Dict):
        """Update traffic history for analysis"""
        source_ip = packet_data.get('source_ip')
        if source_ip:
            self.device_traffic_history[source_ip].append(packet_data)
            
            # Keep only recent traffic (last 10000 packets per IP)
            if len(self.device_traffic_history[source_ip]) > 10000:
                self.device_traffic_history[source_ip] = \
                    self.device_traffic_history[source_ip][-10000:]
    
    def get_threats(self, limit: int = 50) -> List[Dict]:
        """Get recent threats"""
        return self.threat_history[-limit:]
    
    def get_threat_stats(self, hours: int = 24) -> Dict:
        """Get threat statistics"""
        cutoff_time = datetime.now(timezone.utc) - timedelta(hours=hours)
        recent_threats = [
            t for t in self.threat_history
            if datetime.fromisoformat(t['timestamp']) > cutoff_time
        ]
        
        return {
            'total_threats': len(recent_threats),
            'critical': len([t for t in recent_threats if t['level'] == 'critical']),
            'high': len([t for t in recent_threats if t['level'] == 'high']),
            'medium': len([t for t in recent_threats if t['level'] == 'medium']),
            'low': len([t for t in recent_threats if t['level'] == 'low']),
        }


if __name__ == '__main__':
    # Test threat detection
    detector = ThreatDetector()
    
    # Simulate suspicious packet
    test_packet = {
        'timestamp': datetime.now(timezone.utc).isoformat(),
        'size': 50,
        'protocol': 'TCP',
        'source_ip': '192.168.1.100',
        'destination_ip': '8.8.8.8',
        'source_port': 54321,
        'destination_port': 443,
    }
    
    threats = detector.analyze_packet(test_packet)
    print(f"Threats detected: {len(threats)}")
    for threat in threats:
        print(f"  - {threat['type']}: {threat['description']}")
