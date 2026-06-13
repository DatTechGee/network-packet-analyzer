#!/usr/bin/env python3
"""
Domain Blocker - Enforces blocked domains via Windows hosts file
"""

import os
import sys
import time
import logging
import requests
import socket
import subprocess
import ctypes
from pathlib import Path
from threading import Thread, Lock
from typing import Dict, Set, Optional

logger = logging.getLogger('domain_blocker')

HOSTS_FILE = r"C:\Windows\System32\drivers\etc\hosts"
HOSTS_MARKER_START = "# === G2 PROJECT BLOCKED DOMAINS START ==="
HOSTS_MARKER_END = "# === G2 PROJECT BLOCKED DOMAINS END ==="


def is_admin() -> bool:
    try:
        return ctypes.windll.shell32.IsUserAnAdmin() != 0
    except Exception:
        return False


def resolve_domain(domain: str) -> Optional[str]:
    try:
        return socket.gethostbyname(domain)
    except socket.gaierror:
        return None


class DomainBlocker:
    def __init__(self, backend_url: str, agent_key: str):
        self.backend_url = backend_url
        self.agent_key = agent_key
        self._blocked_domains: Dict[str, int] = {}
        self._blocked_ips: Set[str] = set()
        self._lock = Lock()
        self._running = False
        self._thread: Optional[Thread] = None
        self.admin_mode = is_admin()

    def start(self):
        if self._running:
            return
        self._running = True
        self._thread = Thread(target=self._sync_loop, daemon=True)
        self._thread.start()
        logger.info(f"Domain blocker started (admin={self.admin_mode})")

    def stop(self):
        self._running = False
        self._remove_all_from_hosts()

    def get_blocked_domains(self) -> Dict[str, int]:
        with self._lock:
            return dict(self._blocked_domains)

    def is_blocked(self, domain: str) -> bool:
        with self._lock:
            return domain in self._blocked_domains

    def is_ip_blocked(self, ip: str) -> bool:
        with self._lock:
            return ip in self._blocked_ips

    def _sync_loop(self):
        while self._running:
            try:
                self._fetch_blocked_domains()
                self._update_hosts_file()
            except Exception as e:
                logger.error(f"Domain blocker sync error: {e}")
            time.sleep(10)

    def _fetch_blocked_domains(self):
        try:
            resp = requests.get(
                f"{self.backend_url}/blocked-domains",
                headers={'Authorization': f'Bearer {self.agent_key}'},
                timeout=10
            )
            if resp.status_code == 200:
                data = resp.json()
                new_blocked = data.get('blocked_domains', {})
                new_ips = set()
                for domain in new_blocked:
                    ip = resolve_domain(domain)
                    if ip:
                        new_ips.add(ip)
                        logger.debug(f"Resolved {domain} -> {ip}")
                with self._lock:
                    added = set(new_blocked.keys()) - set(self._blocked_domains.keys())
                    removed = set(self._blocked_domains.keys()) - set(new_blocked.keys())
                    self._blocked_domains = new_blocked
                    self._blocked_ips = new_ips
                if added:
                    logger.info(f"Blocked domains added: {added}")
                if removed:
                    logger.info(f"Blocked domains removed: {removed}")
            else:
                logger.warning(f"Blocked domains API returned {resp.status_code}")
        except Exception as e:
            logger.warning(f"Could not fetch blocked domains: {e}")

    def _update_hosts_file(self):
        if not self.admin_mode:
            return
        try:
            with open(HOSTS_FILE, 'r', encoding='utf-8') as f:
                content = f.read()
            lines = content.split('\n')
            cleaned = []
            skip = False
            for line in lines:
                if line.strip() == HOSTS_MARKER_START:
                    skip = True
                    continue
                if line.strip() == HOSTS_MARKER_END:
                    skip = False
                    continue
                if not skip:
                    cleaned.append(line)
            if self._blocked_ips:
                block_lines = [HOSTS_MARKER_START]
                with self._lock:
                    for domain, ip in self._blocked_domains.items():
                        resolved = resolve_domain(domain) if domain not in [d for d in self._blocked_domains] else None
                        target_ip = resolved or "127.0.0.1"
                        block_lines.append(f"{target_ip}  {domain}")
                block_lines.append(HOSTS_MARKER_END)
                cleaned.extend(block_lines)
                logger.info(f"Blocked {len(self._blocked_domains)} domain(s) in hosts file")
            with open(HOSTS_FILE, 'w', encoding='utf-8') as f:
                f.write('\n'.join(cleaned))
            try:
                subprocess.run(['ipconfig', '/flushdns'], capture_output=True, timeout=5)
                logger.debug("Flushed DNS cache")
            except Exception:
                pass
        except PermissionError:
            logger.warning("No permission to modify hosts file - run as admin")
            self.admin_mode = False
        except Exception as e:
            logger.error(f"Failed to update hosts file: {e}")

    def _remove_all_from_hosts(self):
        if not self.admin_mode:
            return
        try:
            with open(HOSTS_FILE, 'r', encoding='utf-8') as f:
                content = f.read()
            lines = content.split('\n')
            cleaned = []
            skip = False
            for line in lines:
                if line.strip() == HOSTS_MARKER_START:
                    skip = True
                    continue
                if line.strip() == HOSTS_MARKER_END:
                    skip = False
                    continue
                if not skip:
                    cleaned.append(line)
            with open(HOSTS_FILE, 'w', encoding='utf-8') as f:
                f.write('\n'.join(cleaned))
            try:
                subprocess.run(['ipconfig', '/flushdns'], capture_output=True, timeout=5)
            except Exception:
                pass
        except Exception as e:
            logger.error(f"Failed to clean hosts file: {e}")
