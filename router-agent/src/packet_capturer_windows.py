#!/usr/bin/env python3
"""
Windows Packet Capture Module
Uses pyshark (Wireshark) for cross-platform packet capture
"""

import json
import logging
import ipaddress
import re
import socket
import time
import subprocess
import threading
from concurrent.futures import ThreadPoolExecutor, as_completed
from threading import Lock
from collections import defaultdict, deque
from datetime import datetime, timezone
from typing import Dict, List, Optional

from config_windows import Config

try:
    import pyshark
except ImportError:
    print("ERROR: pyshark not installed.")
    print("Install with: pip install pyshark")
    print("\nAlso requires Wireshark to be installed:")
    print("Download from: https://www.wireshark.org/download")
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



class PacketCaptureWindows:
    """Cross-platform packet capture using pyshark (Wireshark backend)"""
    
    def __init__(self, interface: str = 'Ethernet', buffer_size: int = 10000):
        """
        Initialize packet capture
        
        Args:
            interface: Network interface name (e.g., 'Ethernet', 'Wi-Fi')
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
            'device_name': None,
            'os_guess': None,
            'fingerprint': None,
        })
        self.is_running = False
        self.capture = None
        self.last_subnet_discovery = 0.0
        self._host_ip: Optional[str] = None
        self._cached_states: Dict[str, str] = {}
        self._last_state_fetch = 0.0
        self._ip_domain_cache: Dict[str, str] = {}
        self._dns_cache_lock = Lock()
        self._dns_cache_thread: Optional[threading.Thread] = None
        
    def list_interfaces(self) -> List[str]:
        """List available network interfaces"""
        try:
            tshark_path = Config.TSHARK_PATH or Config._detect_tshark_path() or 'tshark'
            result = subprocess.run(
                [tshark_path, '-D'],
                capture_output=True,
                text=True,
                check=True
            )
            interfaces = []
            for line in result.stdout.strip().splitlines():
                if '. ' in line:
                    _, iface = line.split('. ', 1)
                    interfaces.append(iface.strip())
            logger.info(f"Available interfaces: {interfaces}")
            return interfaces
        except Exception as e:
            logger.warning(f"Error listing interfaces with tshark -D: {e}")
            try:
                interfaces = pyshark.tshark.tshark.get_tshark_interfaces()
                logger.info(f"Available interfaces (fallback): {interfaces}")
                return interfaces
            except Exception as fallback_error:
                logger.error(f"Fallback interface enumeration failed: {fallback_error}")
                return []

    def _resolve_interface_id(self, desired_interface: str) -> str:
        """Resolve a human-friendly interface name to a tshark device ID."""
        tshark_path = Config.TSHARK_PATH or Config._detect_tshark_path() or 'tshark'
        result = subprocess.run(
            [tshark_path, '-D'],
            capture_output=True,
            text=True,
            check=True
        )

        desired_lower = desired_interface.lower().strip()
        desired_normalized = re.sub(r'\W+', '', desired_lower)

        for line in result.stdout.strip().splitlines():
            if '. ' not in line:
                continue

            _, entry = line.split('. ', 1)
            entry = entry.strip()

            if ' (' in entry and entry.endswith(')'):
                device_id, label = entry.split(' (', 1)
                label = label[:-1]
            else:
                device_id = entry
                label = entry

            entry_lower = entry.lower()
            entry_normalized = re.sub(r'\W+', '', entry_lower)

            if (
                desired_lower == device_id.lower()
                or desired_lower == label.lower()
                or desired_lower == entry_lower
                or desired_normalized == entry_normalized
                or desired_normalized in entry_normalized
                or entry_normalized in desired_normalized
            ):
                return device_id

            if 'ethernet' in desired_lower and 'local area connection' in entry_lower:
                return device_id

            if ('wi-fi' in desired_lower or 'wifi' in desired_lower) and (
                'wi-fi' in entry_lower or 'wifi' in entry_lower or 'wireless' in entry_lower
            ):
                return device_id

        return desired_interface

    def _is_private_ip(self, ip_str: Optional[str]) -> bool:
        """Determine whether an IP is local/private."""
        if not ip_str:
            return False

        try:
            ip_addr = ipaddress.ip_address(ip_str)
            return ip_addr.is_private or ip_addr.is_loopback or ip_addr.is_link_local
        except ValueError:
            return False

    def _discover_arp_devices(self) -> Dict[str, Dict]:
        """Discover connected LAN clients from the local ARP cache."""
        discovered: Dict[str, Dict] = {}

        try:
            result = subprocess.run(
                ['arp', '-a'],
                capture_output=True,
                text=True,
                check=True,
            )
        except Exception as e:
            logger.debug(f"ARP discovery failed: {e}")
            return discovered

        now_iso = datetime.now(timezone.utc).isoformat()
        entry_pattern = re.compile(
            r'^(?P<ip>(?:\d{1,3}\.){3}\d{1,3})\s+'
            r'(?P<mac>(?:[0-9a-f]{2}-){5}[0-9a-f]{2}|incomplete)\s+'
            r'(?P<type>\w+)$',
            re.IGNORECASE,
        )

        for line in result.stdout.splitlines():
            line = line.strip()
            if not line or line.lower().startswith('interface:') or line.lower().startswith('internet'):
                continue

            match = entry_pattern.match(line)
            if not match:
                continue

            ip_address = match.group('ip')
            mac_address = match.group('mac')
            if (
                not self._is_private_ip(ip_address)
                or mac_address.lower() == 'incomplete'
                or ip_address.endswith('.0')
                or ip_address.endswith('.255')
            ):
                continue

            discovered[ip_address] = {
                'packets': 0,
                'bytes_sent': 0,
                'bytes_received': 0,
                'first_seen': now_iso,
                'last_seen': now_iso,
                'device_name': None,
                'mac_address': mac_address.replace('-', ':').lower(),
            }

        return discovered

    def _interface_alias(self) -> str:
        """Extract a friendly interface alias for Windows network commands."""
        if '(' in self.interface and self.interface.endswith(')'):
            return self.interface.rsplit('(', 1)[-1].rstrip(')').strip()
        return self.interface.strip()

    def _get_interface_ipv4(self) -> Optional[str]:
        """Get the first IPv4 address for the configured interface alias."""
        alias = self._interface_alias()
        try:
            script = (
                f"$a = Get-NetIPAddress -AddressFamily IPv4 | "
                f"Where-Object {{ $_.InterfaceAlias -like '*{alias}*' -and $_.IPAddress -notlike '169.254*' -and $_.IPAddress -notlike '127.*' }} | "
                f"Select-Object -First 1; "
                f"if ($a) {{ Write-Output $a.IPAddress }}"
            )
            result = subprocess.run(
                ['powershell', '-NoProfile', '-Command', script],
                capture_output=True,
                text=True,
                check=True,
            )
            ip_address = result.stdout.strip().splitlines()[0].strip() if result.stdout.strip() else ''
            if ip_address:
                return ip_address
            
            # Fallback: get first active non-loopback IP
            fallback_script = (
                "$a = Get-NetIPAddress -AddressFamily IPv4 | "
                "Where-Object { $_.IPAddress -notlike '169.254*' -and $_.IPAddress -notlike '127.*' } | "
                "Select-Object -First 1; "
                "if ($a) { Write-Output $a.IPAddress }"
            )
            fallback_result = subprocess.run(
                ['powershell', '-NoProfile', '-Command', fallback_script],
                capture_output=True, text=True
            )
            fallback_ip = fallback_result.stdout.strip().splitlines()[0].strip() if fallback_result.stdout.strip() else ''
            return fallback_ip or None
        except Exception as e:
            logger.debug(f"Interface IPv4 lookup failed for {alias}: {e}")
            return None

    def _get_default_gateway_ipv4(self) -> Optional[str]:
        """Get the active IPv4 default gateway for the configured interface."""
        alias = self._interface_alias()
        try:
            script = (
                f"$r = Get-NetRoute -DestinationPrefix '0.0.0.0/0' | "
                f"Where-Object {{ $_.InterfaceAlias -like '*{alias}*' }} | "
                f"Sort-Object RouteMetric, InterfaceMetric | Select-Object -First 1; "
                f"if ($r) {{ Write-Output $r.NextHop }}"
            )
            result = subprocess.run(
                ['powershell', '-NoProfile', '-Command', script],
                capture_output=True,
                text=True,
                check=True,
            )
            gateway = result.stdout.strip().splitlines()[0].strip() if result.stdout.strip() else ''
            return gateway or None
        except Exception as e:
            logger.debug(f"Default gateway lookup failed for {alias}: {e}")
            return None

    def _get_host_ip(self) -> Optional[str]:
        """Get this host's IP address on the capture interface."""
        if self._host_ip:
            return self._host_ip
        alias = self._interface_alias()
        logger.info(f"Detecting host IP for alias '{alias}'")

        # Strategy 1: netsh parsing (primary)
        try:
            result = subprocess.run(
                ['netsh', 'interface', 'ip', 'show', 'addresses'],
                capture_output=True, text=True, timeout=10
            )
            current_iface = None
            for line in result.stdout.splitlines():
                s = line.strip()
                if s.startswith('Configuration for interface'):
                    parts = s.split('"')
                    current_iface = parts[1] if len(parts) > 1 else None
                elif current_iface and s.startswith('IP Address'):
                    ip = s.split(':')[1].strip()
                    if self._is_private_ip(ip):
                        self._host_ip = ip
                        logger.info(f"Host IP detected via netsh: {ip}")
                        return ip
        except Exception as e:
            logger.warning(f"Host IP detection via netsh failed: {e}")

        # Strategy 2: Get-NetIPAddress PowerShell (works for any interface)
        try:
            ip_addr = self._get_interface_ipv4()
            if ip_addr:
                self._host_ip = ip_addr
                logger.info(f"Host IP detected via Get-NetIPAddress: {ip_addr}")
                return ip_addr
        except Exception as e:
            logger.warning(f"Host IP detection via Get-NetIPAddress failed: {e}")

        # Strategy 3: socket.gethostbyname (last resort)
        try:
            hostname = socket.gethostname()
            ip_addr = socket.gethostbyname(hostname)
            if self._is_private_ip(ip_addr):
                self._host_ip = ip_addr
                logger.info(f"Host IP detected via socket: {ip_addr}")
                return ip_addr
        except Exception as e:
            logger.warning(f"Host IP detection via socket failed: {e}")

        logger.warning(f"Host IP not found for alias '{alias}' (all strategies exhausted)")
        return None

    def _get_host_mac(self) -> Optional[str]:
        """Get this host's MAC address on the capture interface."""
        alias = self._interface_alias()
        try:
            result = subprocess.run(
                ['getmac', '/fo', 'csv', '/nh'],
                capture_output=True, text=True, timeout=10
            )
            nics = []
            for line in result.stdout.splitlines():
                parts = [p.strip('"') for p in line.split(',')]
                if len(parts) >= 2:
                    mac, device = parts[0], parts[1]
                    nics.append((mac, device))
            for mac, device in nics:
                if alias.lower() in device.lower():
                    return mac
            for mac, device in nics:
                if 'wi-fi' in alias.lower() and 'E74CD6E3' in device:
                    return mac
            if nics:
                return nics[0][0]
        except Exception:
            pass
        return None

    def _prime_subnet_neighbors(self):
        """Probe each IP on the subnet individually to populate ARP cache.

        Broadcast ping doesn't work through AP client isolation on most
        phone hotspots (unicast blocks responses).  Instead, send one
        ICMP ping to every possible /24 host IP in parallel and let
        Windows create ARP entries from the replies.

        If any host responds, Windows populates its ARP cache, making
        the device visible to _discover_arp_devices() on the next sync.
        """
        now = time.time()
        if now - self.last_subnet_discovery < 6:
            return

        local_ip = self._get_interface_ipv4()
        if not local_ip:
            return

        try:
            network = ipaddress.ip_network(f"{local_ip}/24", strict=False)
        except ValueError:
            return

        hosts = [str(h) for h in network.hosts() if str(h) != local_ip]

        def _ping(ip):
            try:
                subprocess.run(
                    ['ping', '-n', '1', '-l', '0', '-w', '300', ip],
                    capture_output=True, text=True, timeout=2
                )
            except Exception:
                pass

        with ThreadPoolExecutor(max_workers=50) as pool:
            list(pool.map(_ping, hosts))

        self.last_subnet_discovery = now

    def _get_neighbor_states(self) -> Dict[str, str]:
        """Return {ip: state} for all private IPv4 neighbors via Get-NetNeighbor.

        States: Reachable (confirmed <30s ago), Stale (unconfirmed),
        Unreachable (probe failed), Incomplete (no response).
        Results cached for 10 seconds to avoid excessive PowerShell calls.
        """
        now = time.time()
        if now - self._last_state_fetch < 10:
            return self._cached_states

        state_map = {'0': 'Unreachable', '1': 'Incomplete', '2': 'Probe',
                     '3': 'Delay', '4': 'Stale', '5': 'Reachable'}
        states: Dict[str, str] = {}
        try:
            result = subprocess.run(
                ['powershell', '-NoProfile', '-Command',
                 'Get-NetNeighbor -AddressFamily IPv4 | ForEach-Object { "$($_.IPAddress)=$([int]$_.State)" }'],
                capture_output=True, text=True, timeout=10
            )
            for line in result.stdout.strip().splitlines():
                line = line.strip()
                if '=' not in line:
                    continue
                ip, raw = line.split('=', 1)
                raw = raw.strip()
                if self._is_private_ip(ip):
                    state = state_map.get(raw, '')
                    if state:
                        states[ip] = state
        except Exception:
            pass

        self._cached_states = states
        self._last_state_fetch = now
        return states

    def start_capture(self, callback=None, packet_count: Optional[int] = None):
        """
        Start capturing packets
        
        Args:
            callback: Function to call for each packet
            packet_count: Number of packets to capture (None = infinite)
        """
        import asyncio
        
        self.is_running = True
        self._start_dns_cache_sync()
        logger.info(f"Starting packet capture on interface: {self.interface}")
        
        try:
            tshark_path = Config.TSHARK_PATH or Config._detect_tshark_path()
            if tshark_path:
                logger.info(f"Using tshark at: {tshark_path}")
            else:
                logger.warning("Tshark path not found; falling back to PATH lookup")

            capture_interface = self._resolve_interface_id(self.interface)
            if capture_interface != self.interface:
                logger.info(f"Resolved capture interface '{self.interface}' -> '{capture_interface}'")

            # Set up event loop for this thread if using async mode
            try:
                loop = asyncio.get_event_loop()
            except RuntimeError:
                loop = asyncio.new_event_loop()
                asyncio.set_event_loop(loop)
            
            # Create capture object with simpler configuration (no use_json to avoid async issues)
            self.capture = pyshark.LiveCapture(
                interface=capture_interface,
                tshark_path=tshark_path
            )
            
            packet_count_actual = 0
            for packet in self.capture.sniff_continuously(packet_count=packet_count):
                if not self.is_running:
                    break
                
                try:
                    packet_data = self._extract_packet_info(packet)
                    if packet_data:
                        with self.buffer_lock:
                            self.packet_buffer.append(packet_data)
                        self._update_device_stats(packet_data)
                        
                        if callback:
                            callback(packet_data)
                        
                        packet_count_actual += 1
                        if packet_count_actual % 100 == 0:
                            logger.debug(f"Captured {packet_count_actual} packets")
                            
                except Exception as e:
                    logger.debug(f"Error processing packet: {e}")
                    continue
                    
        except Exception as e:
            logger.error(f"Capture error: {e}")
            raise
        finally:
            self.is_running = False
            if self.capture:
                self.capture.close()
    
    def _start_dns_cache_sync(self):
        """Start background thread that polls Windows DNS client cache."""
        if self._dns_cache_thread and self._dns_cache_thread.is_alive():
            return
        self._dns_cache_thread = threading.Thread(
            target=self._dns_cache_sync_loop, daemon=True, name='dns-cache-sync'
        )
        self._dns_cache_thread.start()

    def _dns_cache_sync_loop(self):
        """Periodically sync IP→domain mappings from Windows DNS cache."""
        while self.is_running:
            try:
                self._sync_dns_cache_from_os()
            except Exception as e:
                logger.debug(f"DNS cache sync error: {e}")
            time.sleep(5)

    def _sync_dns_cache_from_os(self):
        """Read the Windows DNS client cache and populate IP→domain map."""
        try:
            result = subprocess.run(
                ['powershell', '-NoProfile', '-Command',
                 'Get-DnsClientCache | Select-Object Entry,Data | ConvertTo-Json -Compress'],
                capture_output=True, text=True, timeout=5,
                creationflags=getattr(subprocess, 'CREATE_NO_WINDOW', 0),
            )
            if result.returncode != 0 or not result.stdout.strip():
                return
            rows = json.loads(result.stdout)
            if isinstance(rows, dict):
                rows = [rows]
            with self._dns_cache_lock:
                for row in rows:
                    entry = (row.get('Entry') or '').strip().rstrip('.')
                    data = (row.get('Data') or '').strip().rstrip('.')
                    if not entry or not data:
                        continue
                    # Only cache IP→domain, skip CNAME→CNAME or domain→domain
                    try:
                        ipaddress.ip_address(data)
                        self._ip_domain_cache[data] = entry
                    except ValueError:
                        pass
        except Exception:
            pass

    def _lookup_domain_for_ip(self, ip: str) -> Optional[str]:
        """Look up a domain name for an IP from the DNS cache."""
        if not ip:
            return None
        with self._dns_cache_lock:
            return self._ip_domain_cache.get(ip)

    def _extract_packet_info(self, packet) -> Optional[Dict]:
        """Extract relevant information from packet"""
        try:
            packet_info = {
                'timestamp': datetime.now(timezone.utc).isoformat(),
                'size': int(packet.length) if hasattr(packet, 'length') else 0,
                'protocol': 'Unknown',
                'source_ip': None,
                'destination_ip': None,
                'source_port': None,
                'destination_port': None,
                'flags': None,
                'ttl': None,
                'window_size': None,
                'hostname': None,
                'url': None,
                'browser': None,
                'user_agent': None,
                'app_name': None,
                'source_mac': None,
                'destination_mac': None,
            }
            
            # Ethernet layer
            if hasattr(packet, 'eth'):
                packet_info['source_mac'] = packet.eth.src
                packet_info['destination_mac'] = packet.eth.dst
            
            # IP layer
            if hasattr(packet, 'ip'):
                packet_info['source_ip'] = packet.ip.src
                packet_info['destination_ip'] = packet.ip.dst
                packet_info['ttl'] = int(getattr(packet.ip, 'ttl', 0) or 0)
                
                # TCP
                if hasattr(packet, 'tcp'):
                    packet_info['protocol'] = 'TCP'
                    packet_info['source_port'] = int(packet.tcp.srcport)
                    packet_info['destination_port'] = int(packet.tcp.dstport)
                    if hasattr(packet.tcp, 'flags'):
                        packet_info['flags'] = packet.tcp.flags
                    packet_info['window_size'] = int(getattr(packet.tcp, 'window_size_value', getattr(packet.tcp, 'window_size', 0)) or 0)
                
                # UDP
                elif hasattr(packet, 'udp'):
                    packet_info['protocol'] = 'UDP'
                    packet_info['source_port'] = int(packet.udp.srcport)
                    packet_info['destination_port'] = int(packet.udp.dstport)
                
                # ICMP
                elif hasattr(packet, 'icmp'):
                    packet_info['protocol'] = 'ICMP'
                
                # DNS
                if hasattr(packet, 'dns'):
                    packet_info['protocol'] = 'DNS'
                    domain = self._extract_dns_query(packet)
                    if domain:
                        packet_info['domain'] = domain
                        # Populate IP→domain cache from DNS responses (A/AAAA records only)
                        dns_source = packet_info.get('source_ip')
                        dns_dest = packet_info.get('destination_ip')
                        # Cache the IP that answered the DNS query
                        if dns_source:
                            try:
                                ipaddress.ip_address(dns_source)
                                with self._dns_cache_lock:
                                    self._ip_domain_cache[dns_source] = domain
                            except ValueError:
                                pass

                # DHCP — discover new devices from lease assignments
                dhcp_info = self._extract_dhcp_info(packet)
                if dhcp_info:
                    packet_info['dhcp'] = dhcp_info
                    if dhcp_info.get('yiaddr'):
                        packet_info['dhcp_client_ip'] = dhcp_info['yiaddr']
                    if dhcp_info.get('chaddr'):
                        packet_info['dhcp_client_mac'] = dhcp_info['chaddr']
                    if dhcp_info.get('hostname'):
                        packet_info['dhcp_client_hostname'] = dhcp_info['hostname']

                # Fall back to application-layer hostnames so we can show real sites,
                # not just DNS queries, in the analytics view.
                hostname = self._extract_hostname(packet)
                if hostname and not packet_info.get('domain'):
                    packet_info['domain'] = hostname

                http_metadata = self._extract_http_metadata(packet)
                packet_info.update({k: v for k, v in http_metadata.items() if v is not None})
                if packet_info.get('hostname') and not packet_info.get('domain'):
                    packet_info['domain'] = packet_info['hostname']

                # If still no domain, try resolving destination IP from DNS cache
                if not packet_info.get('domain') and packet_info['protocol'] in ('TCP', 'UDP'):
                    dest_ip = packet_info.get('destination_ip')
                    if dest_ip and not self._is_private_ip(dest_ip):
                        cached_domain = self._lookup_domain_for_ip(dest_ip)
                        if cached_domain:
                            packet_info['domain'] = cached_domain
                        else:
                            # Fallback: reverse DNS lookup
                            try:
                                hostname, _, _ = socket.gethostbyaddr(dest_ip)
                                if hostname and hostname != dest_ip:
                                    packet_info['domain'] = hostname.rstrip('.')
                                    with self._dns_cache_lock:
                                        self._ip_domain_cache[dest_ip] = hostname.rstrip('.')
                            except (socket.herror, socket.gaierror, OSError):
                                pass

                source_vendor, source_device_type = resolve_vendor_and_type(packet_info.get('source_mac', ''), packet_info.get('hostname') or packet_info.get('domain') or '')
                packet_info['os_guess'] = guess_os_from_packet(
                    packet_info.get('ttl'),
                    packet_info.get('window_size'),
                    source_vendor,
                    source_device_type,
                )

                fingerprint_parts = []
                if packet_info['source_mac']:
                    fingerprint_parts.append(f"smac:{packet_info['source_mac'].lower().replace('-', ':')}")
                if packet_info['destination_mac']:
                    fingerprint_parts.append(f"dmac:{packet_info['destination_mac'].lower().replace('-', ':')}")
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

    def _extract_hostname(self, packet) -> Optional[str]:
        """Extract a website hostname from HTTP/TLS fields when available."""
        try:
            if hasattr(packet, 'http') and hasattr(packet.http, 'host'):
                host = str(packet.http.host).strip().rstrip('.')
                if host:
                    return host

            if hasattr(packet, 'tls'):
                for field_name in (
                    'handshake_extensions_server_name',
                    'handshake_extensions_server_name_list',
                    'handshake_extensions_server_name_indication',
                ):
                    if hasattr(packet.tls, field_name):
                        host = str(getattr(packet.tls, field_name)).strip().rstrip('.')
                        if host:
                            return host

                # Some tshark builds expose SNI through field lookup rather than attrs.
                try:
                    host = packet.tls.get_field_value('handshake.extensions_server_name')
                    if host:
                        host = str(host).strip().rstrip('.')
                        if host:
                            return host
                except Exception:
                    pass
        except Exception:
            pass

        return None

    def _extract_http_metadata(self, packet) -> Dict[str, Optional[str]]:
        """Extract host and browser metadata from HTTP headers when available."""
        metadata: Dict[str, Optional[str]] = {
            'hostname': None,
            'url': None,
            'browser': None,
            'user_agent': None,
            'app_name': None,
        }

        try:
            if hasattr(packet, 'http'):
                if hasattr(packet.http, 'host'):
                    host = str(packet.http.host).strip().rstrip('.')
                    if host:
                        metadata['hostname'] = host

                for field_name in ('request_full_uri', 'request_uri', 'request_line'):
                    if hasattr(packet.http, field_name):
                        url = str(getattr(packet.http, field_name)).strip()
                        if url:
                            metadata['url'] = url
                            break

                if hasattr(packet.http, 'user_agent'):
                    user_agent = str(packet.http.user_agent).strip()
                    if user_agent:
                        metadata['user_agent'] = user_agent
                        browser = self._detect_browser(user_agent)
                        metadata['browser'] = browser
                        metadata['app_name'] = browser or self._detect_application(user_agent)
        except Exception:
            pass

        return metadata

    def _detect_browser(self, user_agent: str) -> Optional[str]:
        """Map a user agent string to a friendly browser name."""
        user_agent_lower = user_agent.lower()

        browser_checks = [
            ('edge', 'Microsoft Edge'),
            ('edg/', 'Microsoft Edge'),
            ('chrome', 'Google Chrome'),
            ('firefox', 'Mozilla Firefox'),
            ('safari', 'Safari'),
            ('opr/', 'Opera'),
            ('opera', 'Opera'),
            ('ucbrowser', 'UC Browser'),
            ('trident', 'Internet Explorer'),
        ]

        for marker, label in browser_checks:
            if marker in user_agent_lower:
                return label

        return None

    def _detect_application(self, user_agent: str) -> Optional[str]:
        """Provide a coarse application label when the browser is unknown."""
        if not user_agent:
            return None

        if 'mobile' in user_agent.lower():
            return 'Mobile Browser'

        return 'Web Client'
    
    def _extract_dns_query(self, packet) -> Optional[str]:
        """Extract DNS query domain"""
        try:
            if hasattr(packet, 'dns') and hasattr(packet.dns, 'qry_name'):
                return str(packet.dns.qry_name).rstrip('.')
        except:
            pass
        return None

    def _extract_dhcp_info(self, packet) -> Optional[Dict]:
        """Extract new-device info from DHCP ACK packets.

        AP isolation blocks ARP, so the only way to learn about new
        hotspot clients is from DHCP broadcast traffic captured by
        the sniffer.  A DHCP ACK from the gateway tells us the MAC
        (chaddr) and assigned IP (yiaddr) of the joining device.
        """
        try:
            if not hasattr(packet, 'bootp'):
                return None

            # Determine DHCP message type (2=OFFER, 5=ACK, 3=REQUEST, 8=INFORM)
            msg_type = None
            # Method 1: direct dhcp.option.dhcp attribute
            try:
                if hasattr(packet, 'dhcp') and hasattr(packet.dhcp, 'option'):
                    if hasattr(packet.dhcp.option, 'dhcp'):
                        msg_type = int(packet.dhcp.option.dhcp)
            except (ValueError, TypeError, AttributeError):
                pass
            # Method 2: field value lookup
            if msg_type is None:
                try:
                    val = packet.dhcp.get_field_value('message_type')
                    if val is not None:
                        msg_type = int(val)
                except Exception:
                    pass
            # Method 3: bootp.option.dhcp
            if msg_type is None:
                try:
                    if hasattr(packet.bootp, 'option') and hasattr(packet.bootp.option, 'dhcp'):
                        msg_type = int(packet.bootp.option.dhcp)
                except Exception:
                    pass
            if msg_type is not None:
                if msg_type not in (2, 3, 5, 8):
                    return None

            # Extract yiaddr (your/assigned IP) - try multiple field names
            yiaddr = None
            for attr in ('yiaddr', 'your_ip_address', 'ip'):
                try:
                    val = getattr(packet.bootp, attr, None)
                    if val is not None:
                        yiaddr = str(val).strip()
                        if yiaddr:
                            break
                except Exception:
                    continue
            if yiaddr is None:
                try:
                    ip_field = packet.bootp.get_field_value('ip.your')
                    if ip_field:
                        yiaddr = str(ip_field).strip()
                except Exception:
                    pass

            # Extract chaddr (client hardware address/MAC) - try multiple field names
            chaddr = None
            for attr in ('chaddr', 'client_hw_addr', 'client_mac_address', 'hw_mac_addr'):
                try:
                    val = getattr(packet.bootp, attr, None)
                    if val is not None:
                        chaddr = str(val).strip()
                        if chaddr:
                            break
                except Exception:
                    continue
            if chaddr is None:
                try:
                    hw_field = packet.bootp.get_field_value('hw.mac_addr')
                    if hw_field:
                        chaddr = str(hw_field).strip()
                except Exception:
                    pass

            if not yiaddr or not chaddr:
                return None

            # Extract hostname from DHCP option 12
            hostname = None
            try:
                if hasattr(packet, 'dhcp') and hasattr(packet.dhcp, 'option'):
                    if hasattr(packet.dhcp.option, 'hostname'):
                        hostname = str(packet.dhcp.option.hostname)
            except Exception:
                pass
            if hostname is None:
                try:
                    val = packet.dhcp.get_field_value('option.hostname')
                    if val:
                        hostname = str(val)
                except Exception:
                    pass
            if hostname is None:
                try:
                    val = packet.bootp.get_field_value('option.hostname')
                    if val:
                        hostname = str(val)
                except Exception:
                    pass

            return {
                'yiaddr': yiaddr,
                'chaddr': chaddr.replace('-', ':').lower(),
                'hostname': hostname,
            }
        except Exception:
            return None

    def _update_device_stats(self, packet_data: Dict):
        """Update statistics for device"""
        source_ip = packet_data.get('source_ip')
        if source_ip and self._is_private_ip(source_ip):
            stats = self.device_stats[source_ip]
            stats['packets'] += 1
            stats['bytes_sent'] += packet_data.get('size', 0)
            stats['last_seen'] = packet_data['timestamp']
            stats['packet_last_seen'] = packet_data['timestamp']
            if packet_data.get('os_guess') and stats.get('os_guess') in (None, 'Unknown'):
                stats['os_guess'] = packet_data.get('os_guess')
            if packet_data.get('fingerprint'):
                stats['fingerprint'] = packet_data.get('fingerprint')
            if packet_data.get('source_mac'):
                stats['mac_address'] = packet_data.get('source_mac').lower().replace('-', ':')
            
            if stats['first_seen'] is None:
                stats['first_seen'] = packet_data['timestamp']
            if not stats.get('device_name'):
                stats['device_name'] = self._resolve_device_name(source_ip, packet_data)

        destination_ip = packet_data.get('destination_ip')
        if destination_ip and destination_ip != source_ip and self._is_private_ip(destination_ip):
            stats = self.device_stats[destination_ip]
            stats['packets'] += 1
            stats['bytes_received'] += packet_data.get('bytes_received', 0)
            stats['last_seen'] = packet_data['timestamp']
            stats['packet_last_seen'] = packet_data['timestamp']
            if packet_data.get('destination_mac'):
                stats['mac_address'] = packet_data.get('destination_mac').lower().replace('-', ':')

            if stats['first_seen'] is None:
                stats['first_seen'] = packet_data['timestamp']
            if not stats.get('device_name'):
                stats['device_name'] = self._resolve_device_name(destination_ip, packet_data)

        # DHCP-based discovery: register devices from lease broadcasts
        dhcp = packet_data.get('dhcp')
        if dhcp:
            client_ip = dhcp.get('yiaddr')
            client_mac = dhcp.get('chaddr')
            if client_ip and client_mac and self._is_private_ip(client_ip):
                stats = self.device_stats[client_ip]
                stats['last_seen'] = packet_data['timestamp']
                stats['packet_last_seen'] = packet_data['timestamp']
                stats['mac_address'] = stats.get('mac_address') or client_mac
                stats['first_seen'] = stats.get('first_seen') or packet_data['timestamp']
                hostname = dhcp.get('hostname')
                if hostname and not stats.get('device_name'):
                    stats['device_name'] = hostname

    def _merge_discovered_devices(self):
        """Merge discovered ARP devices into device_stats.

        ARP provides MAC, first_seen, and last_seen.  Devices are kept
        visible as long as they appear in the local ARP cache.

        To prevent stale ARP entries from showing disconnected devices
        indefinitely, this method only updates `last_seen` from ARP when
        the neighbor is confirmed reachable or the device has recent
        packet activity.  Stale/Unreachable ARP entries without packet
        activity are left to age out naturally.
        """

        try:
            self._prime_subnet_neighbors()
        except Exception:
            pass

        discovered = self._discover_arp_devices()
        gateway_ip = self._get_default_gateway_ipv4()
        neighbor_states = self._get_neighbor_states()
        now_iso = datetime.now(timezone.utc).isoformat()
        packet_stale_threshold = 120  # seconds without packets = idle

        with self.buffer_lock:
            for ip, info in discovered.items():
                stats = self.device_stats[ip]
                stats['mac_address'] = info.get('mac_address') or stats.get('mac_address')
                stats['first_seen'] = stats['first_seen'] or info['first_seen']

                # Determine if device has recent packet activity
                packet_last = stats.get('packet_last_seen')
                has_recent_packets = False
                if packet_last:
                    try:
                        pkt_time = datetime.fromisoformat(packet_last)
                        if (datetime.now(timezone.utc) - pkt_time).total_seconds() < packet_stale_threshold:
                            has_recent_packets = True
                    except Exception:
                        pass

                # Only update last_seen from ARP if:
                # - device has recent packet activity, OR
                # - ARP neighbor state is Reachable (confirmed contact)
                neighbor_state = neighbor_states.get(ip, '')
                if has_recent_packets or neighbor_state in ('Reachable', 'Delay'):
                    stats['last_seen'] = info['last_seen']
                elif not stats.get('last_seen'):
                    # First time seeing this device — keep it even without confirmation
                    stats['last_seen'] = info['last_seen']

                if not stats.get('device_name'):
                    stats['device_name'] = self._resolve_device_name(ip, {})

                # Gateway detection
                if ip == gateway_ip:
                    stats['device_name'] = 'Router / Gateway'
                    stats['device_type'] = 'router'
                    stats['is_gateway'] = True
                    stats['vendor'] = 'Network'
                else:
                    vendor, device_type = resolve_vendor_and_type(stats['mac_address'], stats.get('device_name'))
                    stats['vendor'] = vendor
                    stats['device_type'] = device_type
                    if not stats.get('device_name'):
                        stats['device_name'] = self._resolve_device_name(ip, {})

    def _resolve_device_name(self, ip: str, packet_data: Dict) -> str:
        """Resolve a stable device label from packet hints or reverse DNS."""
        for candidate in (
            packet_data.get('device_name'),
            packet_data.get('hostname'),
        ):
            if isinstance(candidate, str):
                candidate = candidate.strip().rstrip('.')
                if candidate:
                    return candidate

        try:
            host, _, _ = socket.gethostbyaddr(ip)
            host = host.strip().rstrip('.')
            if host:
                return host
        except Exception:
            pass

        return f'Device {ip}'
    
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
        """Get device statistics, excluding host and gateway.

        Devices are discovered from the local ARP cache and kept visible
        as long as they appear there.  This is necessary because AP
        client isolation on the hotspot blocks both:
        - Packet sniffing of other devices (so packet-only last_seen
          would miss idle connected devices)
        - NUD probes (so stale ARP entries persist even after a device
          disconnects)

        Without admin privileges we cannot clear stale ARP entries, so
        disconnected devices may linger in the list.  This is a
        fundamental hotspot limitation.
        """
        self._merge_discovered_devices()
        host_ip = self._get_host_ip()
        gateway_ip = self._get_default_gateway_ipv4()

        with self.buffer_lock:
            return {
                ip: dict(stats) for ip, stats in self.device_stats.items()
                if ip != host_ip and ip != gateway_ip
                and stats.get('last_seen') is not None
            }
    
    def clear_buffer(self):
        """Clear packet buffer"""
        with self.buffer_lock:
            self.packet_buffer.clear()
    
    def stop_capture(self):
        """Stop packet capture"""
        self.is_running = False
        if self.capture:
            self.capture.close()
        logger.info("Stopping packet capture")


if __name__ == '__main__':
    # Test packet capture
    capture = PacketCaptureWindows(interface='Ethernet', buffer_size=1000)
    
    print("Available network interfaces:")
    interfaces = capture.list_interfaces()
    for iface in interfaces:
        print(f"  - {iface}")
    
    try:
        print("\nStarting packet capture (press Ctrl+C to stop)...")
        capture.start_capture(packet_count=100)
        
        print("\n--- Packet Statistics ---")
        for ip, stats in capture.get_device_stats().items():
            print(f"{ip}: {stats['packets']} packets, {stats['bytes_sent']} bytes")
            
    except KeyboardInterrupt:
        print("\nCapture stopped by user")
