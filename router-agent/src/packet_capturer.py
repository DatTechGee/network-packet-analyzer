#!/usr/bin/env python3
"""
Packet Capture Module
Captures and processes network packets in real-time
"""

import time
import struct
import textwrap
import socket
import logging
import ipaddress
from threading import Lock
from collections import defaultdict, deque
from datetime import datetime
from typing import Dict, List, Optional, Tuple

try:
    from scapy.all import sniff, AsyncSniffer, IP, TCP, UDP, ICMP, DNS, DNSQR, Raw, Ether
except ImportError:
    print("ERROR: Scapy not installed. Install with: pip install scapy")
    exit(1)

logger = logging.getLogger(__name__)

MAC_VENDOR_MAP = {
    '00:05:02': 'Apple', '00:1c:42': 'Parallels', '00:15:5d': 'Microsoft', '00:50:56': 'VMware',
    '3c:5a:37': 'Google', 'd8:0d:17': 'Apple', 'f4:0f:24': 'Apple', 'fc:fc:48': 'Apple',
    '00:0c:29': 'VMware', '2c:f0:ee': 'Intel', '3c:a6:f6': 'Samsung', '70:a8:e3': 'Apple',
    '8c:85:90': 'Apple', 'a4:77:33': 'Apple', 'ac:bc:32': 'Apple', 'b8:27:eb': 'Raspberry Pi',
    'dc:a6:32': 'Raspberry Pi', 'e4:5f:01': 'Raspberry Pi', 'b8:8d:12': 'Apple',
    '00:1e:c9': 'Dell', '00:26:b9': 'Dell', '00:21:70': 'Dell', '1c:72:1d': 'Intel',
    '24:fd:52': 'Intel', '48:2c:6a': 'Intel', '8c:16:45': 'Intel', 'a4:38:cc': 'Huawei',
    '00:e0:fc': 'Huawei', '3c:15:c2': 'Xiaomi', '64:09:80': 'Xiaomi', '9c:2e:a1': 'Xiaomi',
    'f0:c8:50': 'Xiaomi', '84:d8:1b': 'Xiaomi', 'd8:b3:77': 'Xiaomi', '1c:5a:6b': 'Xiaomi',
    '00:17:88': 'Philips Hue', '00:11:32': 'Synology', '00:18:0a': 'Cisco',
    '30:b5:c2': 'Intel', 'a4:b1:c1': 'Apple', 'e0:d9:e3': 'Apple', 'cc:29:f5': 'Apple',
    'bc:a9:20': 'Apple', '50:3e:aa': 'Apple', '90:3c:93': 'Apple', 'c0:56:27': 'Apple',
    'd8:c4:67': 'Apple', '24:a0:74': 'Apple', '34:15:9e': 'Apple', '34:a3:95': 'Apple',
    'f4:5c:89': 'Apple', 'e0:b9:ba': 'Intel', 'fc:db:b3': 'Intel', '70:cd:0d': 'Intel',
    'ac:b5:7d': 'Intel', '74:da:38': 'Intel', 'b4:2e:99': 'Intel', 'b0:35:9f': 'Intel',
    'b4:a9:fc': 'Intel', '5c:87:9c': 'Intel', 'e8:d8:d1': 'Intel', 'e4:b3:18': 'Intel',
    'e4:b9:7a': 'Intel', 'e4:f8:9c': 'Intel', 'ec:8e:b5': 'Intel', 'f0:d5:bf': 'Intel',
    'f4:30:b9': 'Intel', '2c:33:7a': 'Intel', '3c:18:a0': 'Intel', '54:8c:a0': 'Intel',
    '80:3f:5d': 'Intel', '80:86:f2': 'Intel', '84:3a:4b': 'Intel', '88:53:95': 'Intel',
    '8c:3b:ad': 'Intel', 'a0:c5:89': 'Intel', 'a0:af:bd': 'Intel', 'a8:a1:59': 'Intel',
    'a8:5e:45': 'Intel', 'b4:b6:76': 'Intel', 'bc:83:85': 'Intel', 'c4:8e:8f': 'Intel',
    'd0:c6:37': 'Intel', 'd8:9c:67': 'Intel', 'e0:3f:49': 'Intel', '00:1d:60': 'Intel',
    '00:50:56': 'VMware', '00:0c:29': 'VMware', '00:05:69': 'VMware',
}

def resolve_vendor_and_type(mac_address: str, hostname: str) -> tuple:
    if not mac_address:
        return 'Unknown', 'laptop'
    mac_clean = mac_address.lower().replace('-', ':')
    oui = mac_clean[:8]
    vendor = MAC_VENDOR_MAP.get(oui, 'Unknown')
    
    host_lower = (hostname or '').lower()
    device_type = 'laptop'
    
    if any(k in host_lower for k in ('iphone', 'android', 'phone', 'galaxy', 'pixel', 'mobile')):
        device_type = 'phone'
    elif any(k in host_lower for k in ('ipad', 'tablet', 'tab')):
        device_type = 'tablet'
    elif any(k in host_lower for k in ('desktop', 'pc', 'workstation', 'computer')):
        device_type = 'desktop'
    elif any(k in host_lower for k in ('macbook', 'laptop', 'book', 'notebook')):
        device_type = 'laptop'
    elif any(k in host_lower for k in ('tv', 'roku', 'chromecast', 'shield', 'firestick')):
        device_type = 'smart_tv'
    elif any(k in host_lower for k in ('printer', 'hp', 'canon', 'epson')):
        device_type = 'printer'
    elif any(k in host_lower for k in ('router', 'gateway', 'switch', 'ap')):
        device_type = 'router'
    elif any(k in host_lower for k in ('raspbian', 'raspberry', 'pi', 'esp32', 'espressif', 'hue', 'smart', 'bulb', 'plug')):
        device_type = 'iot'
    elif vendor in ('Apple', 'Samsung', 'Xiaomi', 'Huawei') and not host_lower:
        device_type = 'phone'
    elif vendor == 'Raspberry Pi' or 'espressif' in vendor.lower() or 'philips' in vendor.lower():
        device_type = 'iot'
        
    return vendor, device_type


def guess_os_from_packet(ttl: Optional[int], window_size: Optional[int], vendor: str, device_type: str) -> str:
    """Best-effort OS family guess from common Scapy-visible packet traits."""
    vendor_lower = (vendor or '').lower()
    device_type_lower = (device_type or '').lower()

    if device_type_lower == 'router':
        return 'Network Device'

    if ttl is not None:
        if ttl >= 240:
            return 'Network Device'
        if ttl >= 127:
            return 'Windows'
        if ttl >= 63:
            if vendor_lower in ('apple', 'samsung', 'xiaomi', 'huawei') or device_type_lower in ('phone', 'tablet'):
                return 'iOS/Android'
            return 'Linux/Unix'

    if vendor_lower == 'apple':
        return 'iOS/macOS'
    if vendor_lower in ('samsung', 'xiaomi', 'huawei'):
        return 'Android'
    if vendor_lower in ('microsoft', 'vmware'):
        return 'Windows'

    if window_size is not None and window_size >= 64240:
        return 'Windows'

    return 'Unknown'


class PacketCapture:
    """Real-time packet capture and basic analysis"""
    
    def __init__(self, interface: str = 'eth0', buffer_size: int = 10000):
        """
        Initialize packet capture
        
        Args:
            interface: Network interface to monitor
            buffer_size: Maximum packets to keep in buffer
        """
        self.interface = interface
        self.buffer_size = buffer_size
        self.packet_buffer = deque(maxlen=buffer_size)
        self.buffer_lock = Lock()
        self.device_stats = defaultdict(lambda: {
            'packets': 0,
            'bytes_sent': 0,
            'bytes_received': 0,
            'first_seen': None,
            'last_seen': None,
            'mac_address': None,
            'fingerprint': None,
            'vendor': 'Unknown',
            'device_type': 'Unknown',
        })
        self.is_running = False
        self.sniffer = None
        
    def start_capture(self, callback=None, packet_count: Optional[int] = None):
        """
        Start capturing packets
        
        Args:
            callback: Function to call for each packet
            packet_count: Number of packets to capture (None = infinite)
        """
        self.is_running = True
        self.sniffer = None
        logger.info(f"Starting packet capture on interface: {self.interface}")
        
        try:
            capture_callback = self._process_packet if callback is None else callback
            self.sniffer = AsyncSniffer(
                iface=self.interface,
                prn=capture_callback,
                store=False,
                count=packet_count,
            )
            self.sniffer.start()

            while self.is_running and (packet_count is None or getattr(self.sniffer, 'running', True)):
                time.sleep(1)
        except PermissionError:
            logger.error("ERROR: Root/Administrator privileges required for packet capture")
            exit(1)
        except Exception as e:
            logger.error(f"Capture error: {e}")
            raise
        finally:
            self.is_running = False
            if self.sniffer and getattr(self.sniffer, 'running', False):
                self.sniffer.stop()
    
    def _process_packet(self, packet):
        """Process individual packet"""
        try:
            packet_data = self._extract_packet_info(packet)
            if packet_data:
                with self.buffer_lock:
                    self.packet_buffer.append(packet_data)
                self._update_device_stats(packet_data)
        except Exception as e:
            logger.warning(f"Error processing packet: {e}")
    
    def _extract_packet_info(self, packet) -> Optional[Dict]:
        """Extract relevant information from packet"""
        try:
            packet_info = {
                'timestamp': datetime.now().isoformat(),
                'size': len(packet),
                'protocol': 'Unknown',
                'source_ip': None,
                'destination_ip': None,
                'source_mac': None,
                'destination_mac': None,
                'source_port': None,
                'destination_port': None,
                'flags': None,
                'ttl': None,
                'window_size': None,
                'os_guess': None,
                'bytes_sent': 0,
                'bytes_received': 0,
                'packet_count': 1,
            }

            if Ether in packet:
                packet_info['source_mac'] = str(packet[Ether].src).lower().replace('-', ':')
                packet_info['destination_mac'] = str(packet[Ether].dst).lower().replace('-', ':')
            
            # IP layer
            if IP in packet:
                packet_info['source_ip'] = packet[IP].src
                packet_info['destination_ip'] = packet[IP].dst
                packet_info['ttl'] = int(getattr(packet[IP], 'ttl', 0) or 0)
                
                # TCP
                if TCP in packet:
                    packet_info['protocol'] = 'TCP'
                    packet_info['source_port'] = packet[TCP].sport
                    packet_info['destination_port'] = packet[TCP].dport
                    packet_info['flags'] = self._parse_tcp_flags(packet[TCP].flags)
                    packet_info['window_size'] = int(getattr(packet[TCP], 'window', 0) or 0)
                
                # UDP
                elif UDP in packet:
                    packet_info['protocol'] = 'UDP'
                    packet_info['source_port'] = packet[UDP].sport
                    packet_info['destination_port'] = packet[UDP].dport
                
                # ICMP
                elif ICMP in packet:
                    packet_info['protocol'] = 'ICMP'
                
                # DNS
                if DNS in packet:
                    packet_info['protocol'] = 'DNS'
                    packet_info['domain'] = self._extract_dns_query(packet[DNS])

                if self._is_private_ip(packet_info['source_ip']):
                    packet_info['bytes_sent'] = packet_info['size']
                elif self._is_private_ip(packet_info['destination_ip']):
                    packet_info['bytes_received'] = packet_info['size']

                source_vendor, source_device_type = resolve_vendor_and_type(packet_info.get('source_mac', ''), packet_info.get('domain') or '')
                packet_info['os_guess'] = guess_os_from_packet(
                    packet_info.get('ttl'),
                    packet_info.get('window_size'),
                    source_vendor,
                    source_device_type,
                )

                fingerprint_parts = []
                if packet_info['source_mac']:
                    fingerprint_parts.append(f"smac:{packet_info['source_mac']}")
                if packet_info['destination_mac']:
                    fingerprint_parts.append(f"dmac:{packet_info['destination_mac']}")
                if packet_info['ttl']:
                    fingerprint_parts.append(f"ttl:{packet_info['ttl']}")
                if packet_info['window_size']:
                    fingerprint_parts.append(f"win:{packet_info['window_size']}")
                if packet_info['protocol'] != 'Unknown':
                    fingerprint_parts.append(f"proto:{packet_info['protocol']}")
                packet_info['fingerprint'] = '|'.join(fingerprint_parts) if fingerprint_parts else None

                return packet_info
            
            return None
        except Exception as e:
            logger.debug(f"Error extracting packet info: {e}")
            return None
    
    def _parse_tcp_flags(self, flags) -> str:
        """Parse TCP flags"""
        flag_names = []
        if flags & 0x01:
            flag_names.append('FIN')
        if flags & 0x02:
            flag_names.append('SYN')
        if flags & 0x04:
            flag_names.append('RST')
        if flags & 0x08:
            flag_names.append('PSH')
        if flags & 0x10:
            flag_names.append('ACK')
        if flags & 0x20:
            flag_names.append('URG')
        
        return ','.join(flag_names) if flag_names else 'NONE'
    
    def _extract_dns_query(self, dns_layer) -> Optional[str]:
        """Extract DNS query domain"""
        try:
            if DNSQR in dns_layer:
                return dns_layer[DNSQR].qname.decode().rstrip('.')
        except:
            pass
        return None

    def _is_private_ip(self, ip_str: Optional[str]) -> bool:
        """Determine whether an IP is local/private."""
        if not ip_str:
            return False

        try:
            ip_addr = ipaddress.ip_address(ip_str)
            return ip_addr.is_private or ip_addr.is_loopback or ip_addr.is_link_local
        except ValueError:
            return False
    
    def _update_device_stats(self, packet_data: Dict):
        """Update statistics for device"""
        source_ip = packet_data.get('source_ip')
        destination_ip = packet_data.get('destination_ip')

        if source_ip and self._is_private_ip(source_ip):
            stats = self.device_stats[source_ip]
            stats['packets'] += 1
            stats['bytes_sent'] += packet_data.get('bytes_sent', 0)
            stats['bytes_received'] += packet_data.get('bytes_received', 0)
            stats['last_seen'] = packet_data['timestamp']
            if packet_data.get('source_mac'):
                stats['mac_address'] = packet_data.get('source_mac').lower().replace('-', ':')
            if packet_data.get('fingerprint'):
                stats['fingerprint'] = packet_data.get('fingerprint')
            if packet_data.get('os_guess') and stats.get('device_type') in (None, 'Unknown'):
                stats['device_type'] = packet_data.get('os_guess')

            if stats['first_seen'] is None:
                stats['first_seen'] = packet_data['timestamp']
            
            if not stats.get('vendor') or stats.get('vendor') == 'Unknown':
                vendor, device_type = resolve_vendor_and_type(packet_data.get('source_mac', ''), None)
                stats['vendor'] = vendor
                stats['device_type'] = device_type

        if destination_ip and destination_ip != source_ip and self._is_private_ip(destination_ip):
            stats = self.device_stats[destination_ip]
            stats['packets'] += 1
            stats['bytes_received'] += packet_data.get('bytes_received', 0)
            stats['last_seen'] = packet_data['timestamp']
            if packet_data.get('destination_mac'):
                stats['mac_address'] = packet_data.get('destination_mac').lower().replace('-', ':')
            if packet_data.get('fingerprint'):
                stats['fingerprint'] = packet_data.get('fingerprint')
            if packet_data.get('os_guess') and stats.get('device_type') in (None, 'Unknown'):
                stats['device_type'] = packet_data.get('os_guess')

            if stats['first_seen'] is None:
                stats['first_seen'] = packet_data['timestamp']
            
            if not stats.get('vendor') or stats.get('vendor') == 'Unknown':
                vendor, device_type = resolve_vendor_and_type(packet_data.get('destination_mac', ''), None)
                stats['vendor'] = vendor
                stats['device_type'] = device_type
    
    def get_buffer(self) -> List[Dict]:
        """Get current packet buffer"""
        with self.buffer_lock:
            return list(self.packet_buffer)

    def drain_buffer(self) -> List[Dict]:
        """Get and clear packet buffer in one operation."""
        with self.buffer_lock:
            packets = list(self.packet_buffer)
            self.packet_buffer.clear()
            return packets
    
    def get_device_stats(self) -> Dict:
        """Get device statistics"""
        return dict(self.device_stats)
    
    def clear_buffer(self):
        """Clear packet buffer"""
        with self.buffer_lock:
            self.packet_buffer.clear()
    
    def stop_capture(self):
        """Stop packet capture"""
        self.is_running = False
        if self.sniffer and getattr(self.sniffer, 'running', False):
            self.sniffer.stop()
        logger.info("Stopping packet capture")


if __name__ == '__main__':
    # Test packet capture
    capture = PacketCapture(interface='eth0', buffer_size=1000)
    
    try:
        print("Starting packet capture (press Ctrl+C to stop)...")
        capture.start_capture(packet_count=100)
        
        print("\n--- Packet Statistics ---")
        for ip, stats in capture.get_device_stats().items():
            print(f"{ip}: {stats['packets']} packets, {stats['bytes_sent']} bytes")
            
    except KeyboardInterrupt:
        print("\nCapture stopped by user")
