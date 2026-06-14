<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Models\TrafficLog;
use App\Services\BandwidthCalculatorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class DeviceController extends Controller
{
    private BandwidthCalculatorService $bandwidthService;

    public function __construct(BandwidthCalculatorService $bandwidthService)
    {
        $this->bandwidthService = $bandwidthService;
    }

    /**
     * Get all active devices
     */
    public function index(Request $request): JsonResponse
    {
        // Accept active_window_minutes from frontend; default 15 min so hotspot devices show up
        $activeWindowMinutes = max(0.25, min(60, (float) $request->query('active_window_minutes', 15)));
        $perPage = max(1, min(1000, (int) $request->query('per_page', 100)));
        $windowStart = now()->subMinutes($activeWindowMinutes);

        $cacheKey = "devices:active:{$activeWindowMinutes}:{$perPage}";
        $result = Cache::remember($cacheKey, 2, function () use ($windowStart, $perPage, $activeWindowMinutes) {
            // Simply get devices seen recently — no slow TrafficLog subquery
            $devices = Device::whereNotNull('last_seen')
                ->where('last_seen', '>=', $windowStart)
                ->where('mac_address', '!=', 'external-network')
                ->where('mac_address', '!=', 'ff:ff:ff:ff:ff:ff')
                ->where(function ($q) {
                    $q->whereRaw("LOWER(SUBSTR(mac_address, 1, 2)) NOT IN ('01', '33')");
                })
                ->where(function ($q) {
                    $q->whereRaw("ip_address NOT LIKE '%.255'")
                        ->orWhereNull('ip_address');
                })
                ->orderByDesc('last_seen')
                ->get()
                ->unique(fn ($device) => $this->deviceIdentityKey($device))
                ->reject(fn ($device) => $this->isInfrastructureDevice($device))
                ->values();
            $total = $devices->count();
            $devices = $devices->take($perPage)
                ->map(fn ($device) => $this->formatDevice($device, $activeWindowMinutes));

            return [$total, $devices];
        });

        [$total, $devices] = $result;

        return response()->json([
            'success' => true,
            'data' => $devices,
            'count' => $devices->count(),
            'total' => $total,
            'page' => 1,
            'per_page' => $perPage,
            'pages' => 1,
        ]);
    }

    /**
     * Get device details
     */
    public function show(int $deviceId): JsonResponse
    {
        $device = Device::findOrFail($deviceId);

        $bandwidth = $this->bandwidthService->calculateRealTimeBandwidth($deviceId);
        $topDomains = $this->bandwidthService->getTopDomains($deviceId, 5);
        $avgBandwidth = $this->bandwidthService->getAverageBandwidth($deviceId, '24h');

        return response()->json([
            'success' => true,
            'data' => [
                'device' => $this->formatDevice($device),
                'bandwidth' => $bandwidth,
                'top_domains' => $topDomains,
                'average_bandwidth_24h' => $avgBandwidth,
            ],
        ]);
    }

    /**
     * Update device information
     */
    public function update(Request $request, int $deviceId): JsonResponse
    {
        $device = Device::findOrFail($deviceId);

        $validated = $request->validate([
            'device_name' => 'nullable|string|max:255',
            'device_type' => 'nullable|string|max:50',
            'user_id' => 'nullable|integer',
        ]);

        $device->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Device updated successfully',
            'data' => $this->formatDevice($device),
        ]);
    }

    /**
     * Register new device
     */
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'mac_address' => 'required|string|unique:devices',
            'ip_address' => 'required|ip',
            'device_name' => 'nullable|string',
            'vendor' => 'nullable|string',
        ]);

        $device = Device::create([
            ...$validated,
            'is_online' => true,
            'first_seen' => now(),
            'last_seen' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Device registered successfully',
            'data' => $this->formatDevice($device),
        ], 201);
    }

    /**
     * Block a domain for a device
     */
    public function blockDomain(Request $request, int $deviceId): JsonResponse
    {
        $validated = $request->validate([
            'domain' => 'required|string',
        ]);

        $device = Device::findOrFail($deviceId);
        
        $metadata = $device->metadata ?? [];
        $blocked = $metadata['blocked_domains'] ?? [];
        if (!in_array($validated['domain'], $blocked)) {
            $blocked[] = $validated['domain'];
            $metadata['blocked_domains'] = $blocked;
            $device->metadata = $metadata;
            $device->save();
        }

        return response()->json([
            'success' => true,
            'message' => "Domain {$validated['domain']} blocked for device",
            'blocked_domains' => $blocked,
        ]);
    }

    /**
     * Unblock a domain for a device
     */
    public function unblockDomain(Request $request, int $deviceId): JsonResponse
    {
        $validated = $request->validate([
            'domain' => 'required|string',
        ]);

        $device = Device::findOrFail($deviceId);
        
        $metadata = $device->metadata ?? [];
        $blocked = $metadata['blocked_domains'] ?? [];
        $blocked = array_filter($blocked, fn($d) => $d !== $validated['domain']);
        $metadata['blocked_domains'] = array_values($blocked);
        $device->metadata = $metadata;
        $device->save();

        return response()->json([
            'success' => true,
            'message' => "Domain {$validated['domain']} unblocked for device",
            'blocked_domains' => array_values($blocked),
        ]);
    }

    /**
     * Get active connections for a device
     */
    public function connections(int $deviceId, Request $request): JsonResponse
    {
        $device = Device::findOrFail($deviceId);
        
        $period = $request->query('period', '15m');
        $startTime = match($period) {
            '5m' => now()->subMinutes(5),
            '15m' => now()->subMinutes(15),
            '1h' => now()->subHour(),
            '24h' => now()->subDay(),
            default => now()->subMinutes(15),
        };

        $connections = TrafficLog::where('device_id', $deviceId)
            ->where('timestamp', '>=', $startTime)
            ->whereNotNull('destination_ip')
            ->selectRaw('destination_ip, destination_port, protocol, COALESCE(SUM(bytes_sent + bytes_received), 0) as total_bytes, MAX(timestamp) as last_seen')
            ->groupBy('destination_ip', 'destination_port', 'protocol')
            ->orderByDesc('last_seen')
            ->limit(100)
            ->get()
            ->map(function ($conn) {
                return [
                    'destination_ip' => $conn->destination_ip,
                    'destination_port' => $conn->destination_port,
                    'protocol' => $conn->protocol,
                    'total_mb' => round($conn->total_bytes / (1024 * 1024), 3),
                    'last_seen' => $conn->last_seen ? $conn->last_seen->toIso8601String() : null,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $connections,
        ]);
    }

    /**
     * Set bandwidth limit for a device
     */
    public function setBandwidthLimit(Request $request, int $deviceId): JsonResponse
    {
        $validated = $request->validate([
            'limit_mbps' => 'required|numeric|min:0.1',
            'period' => 'nullable|in:hourly,daily,monthly',
        ]);

        $device = Device::findOrFail($deviceId);
        
        $metadata = $device->metadata ?? [];
        $metadata['bandwidth_limit_mbps'] = $validated['limit_mbps'];
        $metadata['limit_period'] = $validated['period'] ?? 'daily';
        
        $device->metadata = $metadata;
        $device->save();

        return response()->json([
            'success' => true,
            'message' => "Bandwidth limit set to {$validated['limit_mbps']} Mbps",
            'bandwidth_limit' => $validated['limit_mbps'],
        ]);
    }

    /**
     * Block a device from the network using Windows Firewall
     */
    public function blockDevice(int $deviceId): JsonResponse
    {
        $device = Device::findOrFail($deviceId);

        if ($this->isInfrastructureDevice($device)) {
            return response()->json(['success' => false, 'message' => 'Cannot block infrastructure devices'], 400);
        }

        $ip = $device->ip_address;
        $mac = $device->mac_address;
        $name = $device->device_name ?: $device->metadata['hostname'] ?: "Device {$ip}";
        $ruleName = "NetworkAnalyzer_Block_Device_{$deviceId}";

        $metadata = $device->metadata ?? [];
        $metadata['is_blocked'] = true;
        $metadata['blocked_at'] = now()->toIso8601String();
        $device->metadata = $metadata;
        $device->save();

        $firewallApplied = false;
        $firewallOutput = '';

        if ($ip) {
            $commands = [
                "netsh advfirewall firewall add rule name=\"{$ruleName}\" dir=In action=Block remoteip={$ip} enable=yes",
                "netsh advfirewall firewall add rule name=\"{$ruleName}_Out\" dir=Out action=Block remoteip={$ip} enable=yes",
            ];
            foreach ($commands as $cmd) {
                $output = [];
                $exitCode = 0;
                exec($cmd . ' 2>&1', $output, $exitCode);
                if ($exitCode === 0) {
                    $firewallApplied = true;
                } else {
                    $firewallOutput .= implode("\n", $output) . "\n";
                }
            }
        }

        return response()->json([
            'success' => true,
            'message' => "Device {$name} blocked",
            'is_blocked' => true,
            'firewall_applied' => $firewallApplied,
            'firewall_output' => $firewallOutput ?: null,
        ]);
    }

    /**
     * Unblock a previously blocked device
     */
    public function unblockDevice(int $deviceId): JsonResponse
    {
        $device = Device::findOrFail($deviceId);

        $metadata = $device->metadata ?? [];
        $metadata['is_blocked'] = false;
        $metadata['unblocked_at'] = now()->toIso8601String();
        $device->metadata = $metadata;
        $device->save();

        $ruleName = "NetworkAnalyzer_Block_Device_{$deviceId}";
        $commands = [
            "netsh advfirewall firewall delete rule name=\"{$ruleName}\"",
            "netsh advfirewall firewall delete rule name=\"{$ruleName}_Out\"",
        ];
        $output = [];
        $exitCode = 0;
        foreach ($commands as $cmd) {
            exec($cmd . ' 2>&1', $output, $exitCode);
        }

        return response()->json([
            'success' => true,
            'message' => 'Device unblocked',
            'is_blocked' => false,
        ]);
    }

    /**
     * Get list of all blocked device IDs
     */
    public function getBlockedDevices(): JsonResponse
    {
        $blocked = Device::whereJsonContains('metadata->is_blocked', true)
            ->pluck('id')
            ->toArray();

        return response()->json([
            'success' => true,
            'blocked_ids' => $blocked,
        ]);
    }

    /**
     * Format device for response
     */
    private function formatDevice(Device $device, float $activeWindowMinutes = 15): array
    {
        $isActive = $device->last_seen !== null
            && $device->last_seen->greaterThanOrEqualTo(now()->subMinutes($activeWindowMinutes));
        $displayName = $device->device_name
            ?: ($device->metadata['hostname'] ?? null)
            ?: ($device->vendor ?? null)
            ?: ($device->ip_address ? 'Device ' . $device->ip_address : 'Unknown device');

        return [
            'id' => $device->id,
            'mac_address' => $device->mac_address,
            'ip_address' => $device->ip_address,
            'device_name' => $device->device_name,
            'display_name' => $displayName,
            'device_type' => $device->device_type,
            'vendor' => $device->vendor,
            'is_online' => $device->is_online,
            'is_active' => $isActive,
            'is_blocked' => ($device->metadata['is_blocked'] ?? false) === true,
            'first_seen' => $device->first_seen?->toIso8601String(),
            'last_seen' => $device->last_seen?->toIso8601String(),
            'metadata' => $device->metadata ?? [],
        ];
    }

    private function deviceIdentityKey(Device $device): string
    {
        // Gateways/routers: key by IP so duplicates across interfaces collapse
        if (in_array($device->device_type ?? '', ['router'])) {
            return 'gateway:' . strtolower(trim((string) $device->ip_address));
        }

        $macAddress = strtolower(trim((string) $device->mac_address));
        if ($macAddress !== '' && !str_starts_with($macAddress, 'auto-') && $macAddress !== 'external-network') {
            return 'mac:' . $macAddress;
        }

        // Fall back to IP address when we do not have a stable real MAC.
        $ip = trim((string) $device->ip_address);
        if ($ip !== '') {
            return 'ip:' . $ip;
        }

        return 'id:' . $device->id;
    }

    private function isInfrastructureDevice(Device $device): bool
    {
        $deviceType = strtolower(trim((string) $device->device_type));
        $deviceName = strtolower(trim((string) $device->device_name));
        $metadataName = strtolower(trim((string) ($device->metadata['hostname'] ?? '')));

        if (in_array($deviceType, ['router', 'external', 'hotspot_gateway'], true)) {
            return true;
        }

        if ($device->mac_address === 'external-network') {
            return true;
        }

        foreach ([$deviceName, $metadataName] as $label) {
            if ($label !== '' && (str_contains($label, 'gateway') || str_contains($label, 'router') || str_contains($label, 'external hosts'))) {
                return true;
            }
        }

        return false;
    }
}
