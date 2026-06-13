# G2 Project — Networking Glossary

A quick reference for all networking acronyms and technical terms used in this project.

---

## Networking Protocols

| Acronym | Full Name | Description |
|---------|-----------|-------------|
| **TCP** | Transmission Control Protocol | Reliable connection-oriented protocol. Used for HTTP, HTTPS, SSH, FTP. Ensures data arrives complete and in order. |
| **UDP** | User Datagram Protocol | Fast connectionless protocol. Used for DNS queries, video streaming, real-time data. No guarantee of delivery. |
| **IP** | Internet Protocol | Addressing protocol that routes packets between devices using IP addresses (e.g., 192.168.1.1). |
| **HTTP** | Hypertext Transfer Protocol | Web protocol for transferring data. Port 80. Unencrypted — anyone on the network can see the data. |
| **HTTPS** | HTTP Secure | Encrypted version of HTTP. Port 443. Uses TLS/SSL to protect data in transit. |
| **DNS** | Domain Name System | Translates domain names (google.com) to IP addresses (142.250.80.46). Like a phone book for the internet. |
| **DHCP** | Dynamic Host Configuration Protocol | Automatically assigns IP addresses to devices when they join a network. |
| **ARP** | Address Resolution Protocol | Maps IP addresses to MAC addresses on a local network. Used to find devices on the same LAN. |
| **ICMP** | Internet Control Message Protocol | Used by `ping` to test connectivity between devices. |
| **TLS** | Transport Layer Security | Encryption protocol that secures HTTPS connections. TLS 1.3 is the current standard. |
| **SSL** | Secure Sockets Layer | Older encryption protocol, replaced by TLS. Still commonly referenced. |

---

## Network Devices & Components

| Acronym | Full Name | Description |
|---------|-----------|-------------|
| **AP** | Access Point | Device that allows wireless devices to connect to a network (e.g., Wi-Fi router). |
| **LAN** | Local Area Network | Network covering a small area like a home, office, or building. |
| **WAN** | Wide Area Network | Network covering a large area, connecting multiple LANs (e.g., the internet). |
| **WLAN** | Wireless LAN | A LAN that uses Wi-Fi instead of wired connections. |
| **NAT** | Network Address Translation | Allows multiple devices on a private network to share one public IP address. |
| **MAC** | Media Access Control | Unique hardware address assigned to every network interface (e.g., `7C:76:35:A3:60:92`). |
| **IP** | Internet Protocol Address | Logical address assigned to a device on a network (e.g., `192.168.1.100`). |
| **Gateway** | Default Gateway | The router that connects your local network to the internet. |
| **DHCP** | Dynamic Host Configuration Protocol Server | Server that automatically assigns IP addresses to devices. |

---

## Network Ports

| Port | Protocol | Service |
|------|----------|---------|
| **21** | TCP | FTP (File Transfer Protocol) |
| **22** | TCP | SSH (Secure Shell) |
| **25** | TCP | SMTP (Email sending) |
| **53** | TCP/UDP | DNS (Domain Name System) |
| **80** | TCP | HTTP (Unencrypted web) |
| **443** | TCP | HTTPS (Encrypted web) |
| **3389** | TCP | RDP (Remote Desktop) |

---

## Threat Types in This Project

| Threat Type | Severity | What It Detects |
|-------------|----------|-----------------|
| **ARP Spoofing** | High | An attacker sends fake ARP messages to redirect network traffic through their machine. |
| **MAC Change** | Medium | A device changes its MAC address, possibly to impersonate another device. |
| **Port Scan** | Medium | A device probes multiple ports on another device, looking for open services (reconnaissance). |
| **DDoS Attempt** | Critical | A device floods a target with excessive traffic to overwhelm it. |
| **Malware Signature** | High | Traffic matches known malware domain patterns (phishing, botnet, ransomware). |
| **HTTP Insecure** | Medium | A device connects to an HTTP (not HTTPS) website, exposing data in plain text. |
| **Site Blocked** | High | A device attempted to access a domain that has been blocked by the administrator. |
| **Suspicious DNS** | Medium | A DNS query to a known malicious or suspicious domain. |
| **VPN Detection** | Low | Traffic to known VPN/proxy servers, indicating anonymized browsing. |
| **Excessive Connections** | Medium | A device makes an unusual number of connections, possibly scanning or exfiltrating data. |
| **Data Exfiltration** | High | An unusually large data transfer from a device, possibly leaking sensitive data. |
| **Brute Force** | High | Repeated failed login attempts, indicating password guessing attacks. |
| **External Site Visit** | Low | A device connects to an external website (for tracking/audit purposes). |

---

## Project-Specific Terms

| Term | Description |
|------|-------------|
| **Router Agent** | Python program that captures network packets using pyshark (Wireshark's Python library). |
| **Packet Capture** | The process of intercepting and logging network traffic passing through an interface. |
| **pyshark** | Python library that wraps Wireshark's tshark for packet capture and analysis. |
| **tshark** | Wireshark's command-line tool for packet capture. Required by pyshark. |
| **Ingest** | The process of the router agent sending captured data to the backend API. |
| **WebSocket** | A protocol for real-time bidirectional communication between client and server. |
| **UDP Broadcast** | Sending data to all devices on a network using UDP (used for real-time threat streaming). |
| **Threat Analysis** | The backend's process of examining captured traffic to identify security threats. |
| **DNS Cache** | The operating system's stored DNS lookup results, used to map IP addresses to domain names. |
| **Hosts File** | A local file (`C:\Windows\System32\drivers\etc\hosts`) that maps domains to IP addresses. Used for domain blocking. |
| **Active Window** | The time period used to filter "currently active" devices (e.g., devices seen in the last 2 minutes). |
| **Bandwidth** | The maximum rate of data transfer across a network, measured in bits per second (bps). |

---

## Data Units

| Unit | Symbol | Value |
|------|--------|-------|
| Bit | b | Smallest unit of data (0 or 1) |
| Byte | B | 8 bits |
| Kilobyte | KB | 1,000 bytes |
| Megabyte | MB | 1,000,000 bytes |
| Gigabyte | GB | 1,000,000,000 bytes |
| Kbps | Kbps | Kilobits per second |
| Mbps | Mbps | Megabits per second |
| Gbps | Gbps | Gigabits per second |

---

## Security Concepts

| Term | Description |
|------|-------------|
| **Man-in-the-Middle (MITM)** | An attacker intercepts communication between two devices. ARP spoofing is a common MITM attack. |
| **Denial of Service (DoS)** | Overwhelming a target with traffic to make it unavailable. DDoS uses multiple sources. |
| **Reconnaissance** | Gathering information about a target network before launching an attack (e.g., port scanning). |
| **Exfiltration** | Unauthorized transfer of data out of a network. |
| **Encryption** | Converting data into a code to prevent unauthorized access (e.g., TLS/HTTPS). |
| **Firewall** | A system that monitors and controls incoming/outgoing network traffic based on rules. |
| **Blacklist** | A list of blocked domains or IP addresses that are known to be malicious. |
| **Signature** | A pattern used to identify known threats (e.g., malware domain patterns). |
| **VLAN** | Virtual LAN — logically separates devices on the same physical network. |

---

## Abbreviations Used in the Dashboard

| Abbreviation | Meaning |
|--------------|---------|
| **src** | Source IP address (where the packet came from) |
| **dst** | Destination IP address (where the packet is going) |
| **pkt** | Packet |
| **bps** | Bits per second |
| **Mbps** | Megabits per second |
| **RTT** | Round-Trip Time (how long a request takes) |
| **Bps** | Bytes per second |
| **LAN** | Local Area Network |
| **WAN** | Wide Area Network |
| **AP** | Access Point |
| **FW** | Firewall |
| **IDS** | Intrusion Detection System |
| **IPS** | Intrusion Prevention System |
| **QoS** | Quality of Service |
| **PoE** | Power over Ethernet |
