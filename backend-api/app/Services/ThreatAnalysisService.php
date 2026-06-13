<?php

namespace App\Services;

use App\Models\Device;
use App\Models\Threat;
use App\Models\TrafficLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ThreatAnalysisService
{
    private const PERSISTED_THREAT_TYPES = [
        'port_scan',
        'ddos_attempt',
        'malware_signature',
        'external_site_visit',
        'site_visit',
        'http_insecure',
        'suspicious_dns',
        'brute_force',
        'data_exfiltration',
        'vpn_detection',
        'anomalous_traffic',
    ];

    /**
     * Analyze incoming traffic for threats
     */
    public function analyzeTraffic(array $trafficData): array
    {
        $threats = [];

        // Check for port scanning
        if ($this->isPortScan($trafficData)) {
            $threats[] = $this->createThreat($trafficData, 'port_scan', 'high');
        }

        // Check for DDoS patterns
        if ($this->isDDoSAttempt($trafficData)) {
            $threats[] = $this->createThreat($trafficData, 'ddos_attempt', 'critical');
        }

        // Check for suspicious DNS queries
        if ($this->isSuspiciousDNS($trafficData)) {
            $threats[] = $this->createThreat($trafficData, 'suspicious_dns', 'medium');
        }

        // Check for unsafe sites visited through HTTP/TLS hostnames too.
        if ($this->isUnsafeSite($trafficData)) {
            $threats[] = $this->createThreat($trafficData, 'malware_signature', 'high');
        }

        // Check for anomalous traffic patterns
        if ($this->isAnomalousTraffic($trafficData)) {
            $threats[] = $this->createThreat($trafficData, 'anomalous_traffic', 'medium');
        }

        // Check for VPN/Proxy usage
        if ($this->isVPNDetected($trafficData)) {
            $threats[] = $this->createThreat($trafficData, 'vpn_detection', 'low');
        }

        // Check for blacklisted/suspicious domains
        if ($this->isBlacklistedSite($trafficData)) {
            $threats[] = $this->createThreat($trafficData, 'malware_signature', 'high');
        }

        // Check for HTTP-only (unencrypted) site visits — this is the main website threat
        if ($this->isHttpOnlySite($trafficData)) {
            $threats[] = $this->createThreat($trafficData, 'http_insecure', 'medium');
        }

        // Check blacklisted domains
        if ($this->isBlacklistedSite($trafficData)) {
            $threats[] = $this->createThreat($trafficData, 'malware_signature', 'high');
        }

        return $threats;
    }

    /**
     * Detect port scanning activity
     */
    private function isPortScan(array $traffic): bool
    {
        // A single packet can never be a port scan — we need per-device history.
        // The Python agent already does stateful port-scan detection.
        // Here we only flag if a single aggregated log has an extreme destination_port spread
        // which would only happen if it was pre-aggregated by the agent.
        $portCount = (int) ($traffic['unique_ports'] ?? 0);
        return $portCount > 40;
    }

    /**
     * Detect DDoS attack patterns
     */
    private function isDDoSAttempt(array $traffic): bool
    {
        $packetRate = $traffic['packet_count'] ?? 0;
        $size = $traffic['bytes_sent'] ?? 0;

        // High packet rate with small packet size = suspicious
        return $packetRate > 1000 && $size < 100;
    }

    /**
     * Detect suspicious DNS queries
     */
    private function isSuspiciousDNS(array $traffic): bool
    {
        $domain = strtolower((string) ($traffic['domain'] ?? ''));
        $protocol = $traffic['protocol'] ?? '';

        // Only analyse DNS packets with a non-empty domain
        if (strtoupper($protocol) !== 'DNS' || $domain === '') {
            return false;
        }

        $suspicious = ['malware', 'botnet', 'phishing', 'ransomware', 'trojan', 'exploit'];
        foreach ($suspicious as $pattern) {
            if (str_contains($domain, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Detect unsafe websites from visited domains or hostnames.
     */
    private function isUnsafeSite(array $traffic): bool
    {
        $domain = strtolower(trim((string) ($traffic['domain'] ?? $traffic['hostname'] ?? '')));
        if ($domain === '') {
            return false;
        }

        $unsafePatterns = [
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
        ];

        foreach ($unsafePatterns as $pattern) {
            if (str_contains($domain, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Detect HTTP-only sites (not HTTPS) - potentially unsafe connections
     */
    private function isHttpOnlySite(array $traffic): bool
    {
        $destPort = $traffic['destination_port'] ?? null;
        $domain = strtolower(trim((string) ($traffic['domain'] ?? $traffic['hostname'] ?? '')));
        $destIp = $traffic['destination_ip'] ?? '';

        // Port 80 = HTTP only (not encrypted) — threat even without domain resolved
        if ($destPort == 80) {
            // If we have a domain, check it's not private
            if ($domain !== '' && $this->isPrivateHost($domain)) {
                return false;
            }
            // If no domain, check the destination IP is not private
            if ($domain === '' && $this->isPrivateHost($destIp)) {
                return false;
            }
            return true;
        }

        return false;
    }

    /**
     * Check domain against known blacklist patterns
     */
    private function isBlacklistedSite(array $traffic): bool
    {
        $domain = strtolower(trim((string) ($traffic['domain'] ?? $traffic['hostname'] ?? '')));
        if ($domain === '' || $this->isPrivateHost($domain)) {
            return false;
        }

        // Common suspicious/blacklisted domain patterns
        $blacklistPatterns = [
            'phishing', 'malware', 'ransomware', 'trojan', 'virus',
            'exploit', 'shellcode', 'botnet', 'keylogger', 'spyware',
            'adware', 'rootkit', 'backdoor', 'cryptominer', 'mining',
            'fake-login', 'secure-update', 'verify-account', 'account-update',
            'banking-login', 'paypal-secure', 'apple-id', 'microsoft-login',
            'free-download', 'crack', 'keygen', 'warez', 'torrent-downloads',
            'xxx', 'porn', 'adult', 'gambling', 'casino', 'bet365',
            'drug', 'viagra', 'cialis', 'pharmacy',
            'ddos', 'stresser', 'booter', 'sqli', 'xss',
        ];

        foreach ($blacklistPatterns as $pattern) {
            if (str_contains($domain, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Detect anomalous traffic patterns
     */
    private function isAnomalousTraffic(array $traffic): bool
    {
        $deviceId = $traffic['device_id'] ?? null;
        if (!$deviceId) {
            return false;
        }

        // Get historical average over 7 days
        $avgTraffic = TrafficLog::where('device_id', $deviceId)
            ->whereDate('timestamp', '>=', Carbon::now()->subDays(7))
            ->avg('bytes_sent');

        if (!$avgTraffic || $avgTraffic < 1000) {
            // Not enough history to establish a baseline — skip
            return false;
        }

        $currentTraffic = $traffic['bytes_sent'] ?? 0;

        // Flag only if current is 20x higher than average (very conservative to avoid false positives)
        return $currentTraffic > ($avgTraffic * 20);
    }

    /**
     * Detect VPN/Proxy usage
     */
    private function isVPNDetected(array $traffic): bool
    {
        // Only well-known VPN-specific ports — exclude 443 (normal HTTPS)
        $vpnOnlyPorts = [1194, 500, 1701, 1723, 4500];
        $vpnProtocols = ['OpenVPN', 'WireGuard', 'PPTP', 'L2TP', 'IKEv2'];

        $destPort = $traffic['destination_port'] ?? null;
        $protocol = $traffic['protocol'] ?? '';

        $portMatch = $destPort && in_array((int) $destPort, $vpnOnlyPorts, true);
        $protocolMatch = in_array($protocol, $vpnProtocols, true);

        return $portMatch || $protocolMatch;
    }

    /**
     * Detect website visits from HTTP/TLS hostnames - tracks all site visits
     */
    private function isWebsiteVisit(array $traffic): bool
    {
        $domain = strtolower(trim((string) ($traffic['domain'] ?? $traffic['hostname'] ?? '')));
        if ($domain === '') {
            return false;
        }

        // Skip local/private hostnames
        if ($this->isPrivateHost($domain)) {
            return false;
        }

        // Any traffic with a valid public domain = website visit
        // Protocol is TCP/UDP, domain presence means HTTP/HTTPS/DNS
        return true;
    }

    /**
     * Create and save threat record
     */
    private function createThreat(array $traffic, string $type, string $level): Threat
    {
        $storedType = $this->normalizeThreatType($type);
        $signature = $traffic['signature'] ?? md5(json_encode([
            $traffic['device_id'] ?? null,
            $storedType,
            $traffic['source_ip'] ?? null,
            $traffic['destination_ip'] ?? null,
            $traffic['domain'] ?? $traffic['hostname'] ?? null,
        ]));

        $recentThreat = Threat::where('signature', $signature)
            ->where('detected_at', '>=', Carbon::now()->subMinutes(2))
            ->latest('detected_at')
            ->first();

        if ($recentThreat) {
            return $recentThreat;
        }

        return Threat::create([
            'device_id' => $traffic['device_id'] ?? null,
            'threat_type' => $storedType,
            'threat_level' => $level,
            'description' => $this->getThreatDescription($type, $traffic),
            'source_ip' => $traffic['source_ip'] ?? null,
            'destination_ip' => $traffic['destination_ip'] ?? null,
            'source_port' => $traffic['source_port'] ?? null,
            'destination_port' => $traffic['destination_port'] ?? null,
            'signature' => $signature,
            'blocked' => false,
            'detected_at' => now(),
            'metadata' => array_merge($this->buildThreatMetadata($traffic), [
                'event_type' => $type,
            ]),
        ]);
    }

    /**
     * Keep the domain/browser context with the threat record.
     */
    private function buildThreatMetadata(array $traffic): array
    {
        return array_filter([
            'event_type' => $traffic['event_type'] ?? $traffic['threat_type'] ?? null,
            'domain' => $traffic['domain'] ?? $traffic['hostname'] ?? null,
            'hostname' => $traffic['hostname'] ?? null,
            'browser' => $traffic['browser'] ?? null,
            'user_agent' => $traffic['user_agent'] ?? null,
            'app_name' => $traffic['app_name'] ?? null,
        ], static fn ($value) => $value !== null && $value !== '');
    }

    private function normalizeThreatType(string $type): string
    {
        if ($type === 'external_site_visit') {
            return 'anomalous_traffic';
        }

        return in_array($type, self::PERSISTED_THREAT_TYPES, true) ? $type : 'anomalous_traffic';
    }

    /**
     * Get human-readable threat description
     */
    private function getThreatDescription(string $type, array $traffic): string
    {
        $descriptions = [
            'port_scan' => "Port scanning detected from {$traffic['source_ip']}",
            'ddos_attempt' => "Potential DDoS attack from {$traffic['source_ip']}",
            'suspicious_dns' => "Suspicious DNS query to {$traffic['domain']}",
            'anomalous_traffic' => "Unusual traffic pattern detected",
            'vpn_detection' => "VPN/Proxy usage detected",
            'site_visit' => isset($traffic['domain'])
                ? "Site visited: {$traffic['domain']}"
                : 'Site visited',
            'http_insecure' => isset($traffic['domain'])
                ? "Insecure HTTP site: {$traffic['domain']}"
                : 'Insecure HTTP connection detected',
            'external_site_visit' => isset($traffic['domain'])
                ? "External site visited: {$traffic['domain']}"
                : 'External site visited',
            'malware_signature' => isset($traffic['domain'])
                ? "Unsafe site detected: {$traffic['domain']}"
                : 'Unsafe site detected',
        ];

        return $descriptions[$type] ?? "Unknown threat detected";
    }

    /**
     * Get threat statistics
     */
    public function getThreatStats(string $period = '24h'): array
    {
        $startDate = match ($period) {
            '24h' => Carbon::now()->subDay(),
            '7d' => Carbon::now()->subDays(7),
            '30d' => Carbon::now()->subDays(30),
            default => Carbon::now()->subDay(),
        };

        return [
            'total_threats' => Threat::where('detected_at', '>=', $startDate)->count(),
            'critical' => Threat::where('detected_at', '>=', $startDate)->where('threat_level', 'critical')->count(),
            'high' => Threat::where('detected_at', '>=', $startDate)->where('threat_level', 'high')->count(),
            'blocked' => Threat::where('detected_at', '>=', $startDate)->where('blocked', true)->count(),
        ];
    }

    private function isPrivateHost(string $host): bool
    {
        return str_ends_with($host, '.local')
            || str_ends_with($host, '.lan')
            || str_contains($host, 'localhost')
            || str_contains($host, 'in-addr.arpa')
            || str_contains($host, 'ip6.arpa');
    }
}
