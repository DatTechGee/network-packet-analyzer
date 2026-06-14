<?php

namespace App\Http\Controllers;

use App\Models\BandwidthSnapshot;
use App\Models\Device;
use App\Models\TrafficLog;
use App\Services\BandwidthCalculatorService;
use App\Services\ThreatAnalysisService;
use App\Models\Threat;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class TrafficController extends Controller
{
    private BandwidthCalculatorService $bandwidthService;
    private ThreatAnalysisService $threatService;

    public function __construct(BandwidthCalculatorService $bandwidthService, ThreatAnalysisService $threatService)
    {
        $this->bandwidthService = $bandwidthService;
        $this->threatService = $threatService;
    }

    /**
     * Ingest packet data from the router agent.
     */
    public function ingest(Request $request): JsonResponse
    {
        $bearerToken = $request->bearerToken();
        $expectedKey = config('services.router_agent.key', 'default_key');

        if (!$bearerToken || $bearerToken !== $expectedKey) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized router agent request',
            ], 401);
        }

        @ini_set('max_execution_time', '0');
        @set_time_limit(0);
        @ignore_user_abort(true);
        DB::connection()->disableQueryLog();

        $validated = $request->validate([
            'timestamp' => 'nullable|date',
            'packets' => 'nullable|array',
            'threats' => 'nullable|array',
            'device_stats' => 'nullable|array',
        ]);

        $deviceIdsByIp = [];
        $devicesUpdated = 0;
        $trafficLogsCreated = 0;
        $threatsCreated = 0;
        $snapshotsCreated = 0;
        $trafficLogRows = [];
        $threatRows = [];
        $now = now();

        foreach (($validated['device_stats'] ?? []) as $sourceIp => $stats) {
            if (!filter_var($sourceIp, FILTER_VALIDATE_IP)) {
                continue;
            }

            $statsArray = is_array($stats) ? $stats : [];

            // Ignore non-private stats records for snapshot creation, except the
            // hotspot gateway device which may be the local phone acting as NAT.
            if (!$this->isPrivateIp($sourceIp) && ($statsArray['device_type'] ?? '') !== 'hotspot_gateway') {
                continue;
            }

            $device = $this->resolveDeviceForIp($sourceIp, $statsArray);

            // Skip bandwidth snapshots for blocked devices
            if (($device->metadata['is_blocked'] ?? false) === true) {
                continue;
            }

            $deviceIdsByIp[$sourceIp] = $device->id;
            $devicesUpdated++;

            $snapshotsCreated += $this->storeBandwidthSnapshot($device, $statsArray);
        }

        $resolvedDevicesById = [];

        foreach (($validated['packets'] ?? []) as $packet) {
            if (!is_array($packet)) {
                continue;
            }

            $sourceIp = $packet['source_ip'] ?? null;
            $destinationIp = $packet['destination_ip'] ?? null;

            // Validate IPs
            if ($sourceIp && !filter_var($sourceIp, FILTER_VALIDATE_IP)) {
                $sourceIp = null;
            }
            if ($destinationIp && !filter_var($destinationIp, FILTER_VALIDATE_IP)) {
                $destinationIp = null;
            }

            // Choose the local/private side as the device identifier. Prefer source, then destination.
            $deviceIp = null;
            if ($sourceIp && $this->isPrivateIp($sourceIp)) {
                $deviceIp = $sourceIp;
            } elseif ($destinationIp && $this->isPrivateIp($destinationIp)) {
                $deviceIp = $destinationIp;
            } elseif ($sourceIp) {
                // Collapse all non-private traffic to one synthetic bucket to avoid
                // repeated external host resolution on large captures.
                $deviceIp = 'external-network';
            } else {
                continue;
            }

            if (!isset($deviceIdsByIp[$deviceIp])) {
                $device = $deviceIp === 'external-network'
                    ? $this->resolveDeviceForIp('8.8.8.8', [])
                    : $this->resolveDeviceForIp(
                        $deviceIp,
                        $validated['device_stats'][$deviceIp] ?? []
                    );
                $deviceIdsByIp[$deviceIp] = $device->id;
                $devicesUpdated++;
            }

            // Skip traffic from blocked devices
            $blockedDeviceIds = $blockedDeviceIds ?? ($this->getBlockedDeviceIds() ?? []);
            if (in_array($deviceIdsByIp[$deviceIp], $blockedDeviceIds)) {
                continue;
            }

            $trafficLogsCreated++;

            $destPort = (int) ($packet['destination_port'] ?? 0);
            $needsAnalysis = in_array($destPort, [80, 8080, 8081, 8443, 53])
                || !empty($packet['domain'])
                || !empty($packet['hostname']);

            $detectedThreats = [];
            if ($needsAnalysis) {
                $detectedThreats = $this->threatService->analyzeTraffic([
                'device_id' => $deviceIdsByIp[$deviceIp],
                'source_ip' => $sourceIp,
                'destination_ip' => $packet['destination_ip'] ?? null,
                'source_port' => $packet['source_port'] ?? null,
                'destination_port' => $packet['destination_port'] ?? null,
                'protocol' => $packet['protocol'] ?? 'Unknown',
                'bytes_sent' => (int) ($packet['size'] ?? 0),
                'bytes_received' => (int) ($packet['bytes_received'] ?? 0),
                'packet_count' => (int) ($packet['packet_count'] ?? 1),
                'domain' => $packet['domain'] ?? null,
                'hostname' => $packet['hostname'] ?? null,
                'browser' => $packet['browser'] ?? null,
                'user_agent' => $packet['user_agent'] ?? null,
                'app_name' => $packet['app_name'] ?? null,
            ]);
            }

            foreach ($detectedThreats as $dt) {
                $this->broadcastThreat($dt->toArray());
            }

            // Retrieve device details for live stream broadcast
            $deviceId = $deviceIdsByIp[$deviceIp];
            if (!isset($resolvedDevicesById[$deviceId])) {
                $resolvedDevicesById[$deviceId] = Device::find($deviceId);
            }
            $deviceModel = $resolvedDevicesById[$deviceId];

            $this->broadcastPacket([
                'id' => uniqid(),
                'source_ip' => $sourceIp,
                'destination_ip' => $packet['destination_ip'] ?? null,
                'source_port' => $packet['source_port'] ?? null,
                'destination_port' => $packet['destination_port'] ?? null,
                'protocol' => $packet['protocol'] ?? 'Unknown',
                'size_bytes' => (int) ($packet['size'] ?? 0),
                'domain' => $packet['domain'] ?? null,
                'url' => $packet['url'] ?? null,
                'timestamp' => $packet['timestamp'] ?? $now->toIso8601String(),
                'device' => $deviceModel ? [
                    'id' => $deviceModel->id,
                    'display_name' => $deviceModel->device_name ?: ($deviceModel->metadata['hostname'] ?? $deviceModel->ip_address),
                    'ip_address' => $deviceModel->ip_address,
                    'mac_address' => $deviceModel->mac_address,
                    'device_type' => $deviceModel->device_type,
                ] : null,
            ]);

            $trafficLogRows[] = [
                'device_id' => $deviceIdsByIp[$deviceIp],
                'source_ip' => $sourceIp,
                'destination_ip' => $packet['destination_ip'] ?? null,
                'source_port' => $packet['source_port'] ?? null,
                'destination_port' => $packet['destination_port'] ?? null,
                'protocol' => $packet['protocol'] ?? 'Unknown',
                'bytes_sent' => (int) ($packet['size'] ?? 0),
                'bytes_received' => (int) ($packet['bytes_received'] ?? 0),
                'packet_count' => (int) ($packet['packet_count'] ?? 1),
                'content_type' => $packet['content_type'] ?? null,
                'domain' => $packet['domain'] ?? null,
                'url' => $packet['url'] ?? null,
                'user_agent' => $packet['user_agent'] ?? null,
                'metadata' => json_encode([
                    'hostname' => $packet['hostname'] ?? null,
                    'browser' => $packet['browser'] ?? null,
                    'app_name' => $packet['app_name'] ?? null,
                ], JSON_UNESCAPED_SLASHES),
                'recorded_at' => $packet['timestamp'] ?? $now,
                'timestamp' => $packet['timestamp'] ?? $now,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (($validated['threats'] ?? []) as $threat) {
            if (!is_array($threat)) {
                continue;
            }

            $sourceIp = $threat['source_ip'] ?? null;
            if (!$sourceIp || !filter_var($sourceIp, FILTER_VALIDATE_IP)) {
                continue;
            }

            if (!isset($deviceIdsByIp[$sourceIp])) {
                $device = $this->resolveDeviceForIp(
                    $sourceIp,
                    $validated['device_stats'][$sourceIp] ?? []
                );
                $deviceIdsByIp[$sourceIp] = $device->id;
                $devicesUpdated++;
            }

            $threatsCreated++;
            
            // Create corresponding Alert notification
            $type = $threat['type'] ?? $threat['threat_type'] ?? 'anomalous_traffic';
            \App\Models\Alert::create([
                'type' => $type,
                'title' => 'Security Threat: ' . ucwords(str_replace('_', ' ', $type)),
                'message' => $threat['description'] ?? 'Detected security threat',
                'device_id' => $deviceIdsByIp[$sourceIp],
            ]);

            $threatType = ($threat['type'] ?? $threat['threat_type'] ?? 'anomalous_traffic');
            $threatRows[] = [
                'device_id' => $deviceIdsByIp[$sourceIp],
                'threat_type' => $threatType === 'external_site_visit'
                    ? 'anomalous_traffic'
                    : $threatType,
                'threat_level' => $threat['level'] ?? $threat['threat_level'] ?? 'medium',
                'description' => $threat['description'] ?? 'Detected by router agent',
                'source_ip' => $sourceIp,
                'destination_ip' => $threat['destination_ip'] ?? null,
                'source_port' => $threat['source_port'] ?? null,
                'destination_port' => $threat['destination_port'] ?? null,
                'blocked' => (bool) ($threat['blocked'] ?? false),
                'is_resolved' => (bool) ($threat['is_resolved'] ?? false),
                'detected_at' => $threat['timestamp'] ?? $now,
                'metadata' => json_encode($this->buildThreatMetadata($threat), JSON_UNESCAPED_SLASHES),
                'created_at' => $now,
                'updated_at' => $now,
            ];

            // Broadcast threat in real-time via WebSocket
            $this->broadcastThreat([
                'type' => 'threat',
                'event' => 'threat_detected',
                'data' => [
                    'id' => uniqid(),
                    'threat_type' => $threatType,
                    'threat_level' => $threat['level'] ?? $threat['threat_level'] ?? 'medium',
                    'description' => $threat['description'] ?? 'Detected by router agent',
                    'source_ip' => $sourceIp,
                    'destination_ip' => $threat['destination_ip'] ?? null,
                    'domain' => $threat['domain'] ?? $threat['metadata']['domain'] ?? null,
                    'hostname' => $threat['hostname'] ?? null,
                    'timestamp' => ($threat['timestamp'] ?? $now)->toIso8601String(),
                    'metadata' => $threat['metadata'] ?? [],
                ],
            ]);
        }

        DB::transaction(function () use ($trafficLogRows, $threatRows) {
            if (!empty($trafficLogRows)) {
                foreach (array_chunk($trafficLogRows, 500) as $chunk) {
                    TrafficLog::insert($chunk);
                }
            }
            if (!empty($threatRows)) {
                foreach (array_chunk($threatRows, 500) as $chunk) {
                    Threat::insert($chunk);
                }
            }
        });

        // Only scan for offline devices every 30 seconds (use cache)
        $offlineScanKey = 'offline_scan_last';
        if (!Cache::has($offlineScanKey)) {
            Cache::put($offlineScanKey, true, 30);
            $offlineThreshold = now()->subSeconds(30);
            $offlineDevices = Device::where('is_online', true)
                ->where('mac_address', '!=', 'external-network')
                ->where('last_seen', '<', $offlineThreshold)
                ->get();
            foreach ($offlineDevices as $offlineDevice) {
                $offlineDevice->update(['is_online' => false]);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Ingested router agent payload successfully',
            'data' => [
                'devices_updated' => count($deviceIdsByIp),
                'traffic_logs_created' => $trafficLogsCreated,
                'threats_created' => $threatsCreated,
                'bandwidth_snapshots_created' => $snapshotsCreated,
            ],
        ]);
    }

    /**
     * Record traffic data from router agent
     */
    public function recordTraffic(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'device_id' => 'required|integer',
            'source_ip' => 'required|ip',
            'destination_ip' => 'required|ip',
            'source_port' => 'required|integer',
            'destination_port' => 'required|integer',
            'protocol' => 'required|string',
            'bytes_sent' => 'required|integer',
            'bytes_received' => 'required|integer',
            'packet_count' => 'required|integer',
            'content_type' => 'nullable|string',
            'domain' => 'nullable|string',
            'url' => 'nullable|string',
            'user_agent' => 'nullable|string',
            'metadata' => 'nullable|array',
        ]);

        $log = TrafficLog::create([
            ...$validated,
            'timestamp' => now(),
        ]);

        return response()->json([
            'success' => true,
            'data' => $log,
        ], 201);
    }

    /**
     * Get traffic for device
     */
    public function getDeviceTraffic(int $deviceId, Request $request): JsonResponse
    {
        $period = $request->query('period', '24h');
        $limit = $request->query('limit', 100);

        $startDate = match ($period) {
            '5m' => now()->subMinutes(5),
            '1h' => now()->subHour(),
            '24h' => now()->subDay(),
            '7d' => now()->subDays(7),
            '30d' => now()->subDays(30),
            default => now()->subDay(),
        };

        $logs = TrafficLog::where('device_id', $deviceId)
            ->where('timestamp', '>=', $startDate)
            ->orderBy('timestamp', 'desc')
            ->limit($limit)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $logs,
            'count' => $logs->count(),
        ]);
    }

    /**
     * Get traffic statistics
     */
    public function getTrafficStats(Request $request): JsonResponse
    {
        $period = $request->query('period', '24h');
        $activeWindowMinutes = 1;

        $stats = Cache::remember("traffic:stats:{$period}:{$activeWindowMinutes}", 2, function () use ($period, $activeWindowMinutes) {
            $startDate = match ($period) {
                '1h' => now()->subHour(),
                '24h' => now()->subDay(),
                '7d' => now()->subDays(7),
                '30d' => now()->subDays(30),
                default => now()->subDay(),
            };

            $trafficAgg = TrafficLog::where('timestamp', '>=', $startDate)
                ->selectRaw('COALESCE(SUM(bytes_sent), 0) as total_sent, COALESCE(SUM(bytes_received), 0) as total_received, COALESCE(SUM(packet_count), 0) as total_packets')
                ->first();

            $windowStart = now()->subMinutes(max(15, $activeWindowMinutes));
            $recentDeviceIds = TrafficLog::where('timestamp', '>=', $windowStart)
                ->whereHas('device', function ($query) {
                    $query->where('mac_address', '!=', 'external-network');
                })
                ->distinct()
                ->pluck('device_id');

            $activeDevices = Device::where(function ($q) use ($recentDeviceIds, $windowStart) {
                    $q->whereIn('id', $recentDeviceIds)
                      ->orWhere(function ($b) use ($windowStart) {
                          $b->whereIn('device_type', ['router', 'hotspot_gateway'])
                            ->whereNotNull('last_seen')
                            ->where('last_seen', '>=', $windowStart);
                      });
                })
                ->where('mac_address', '!=', 'external-network')
                ->where('mac_address', '!=', 'ff:ff:ff:ff:ff:ff')
                ->where(function ($q) {
                    $q->whereRaw("LOWER(SUBSTR(mac_address, 1, 2)) NOT IN ('01', '33')")
                      ->orWhere('device_type', 'hotspot_gateway');
                })
                ->where(function ($q) {
                    $q->whereRaw("ip_address NOT LIKE '%.255'")
                      ->orWhereNull('ip_address')
                      ->orWhere('device_type', 'hotspot_gateway');
                })
                ->get()
                ->unique(function ($device) {
                    // Deduplicate by IP (same logic as DeviceController)
                    if (in_array($device->device_type ?? '', ['router', 'hotspot_gateway'])) {
                        return 'gateway:' . strtolower(trim((string) $device->ip_address));
                    }
                    $ip = trim((string) $device->ip_address);
                    return $ip !== '' ? 'ip:' . $ip : 'id:' . $device->id;
                })
                ->count();

            return [
                'total_traffic_mb' => round((((int) $trafficAgg->total_sent + (int) $trafficAgg->total_received) / (1024 * 1024)), 2),
                'total_packets' => (int) $trafficAgg->total_packets,
                'active_devices' => $activeDevices,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    /**
     * Get traffic timeline grouped by hour/day for charts.
     */
    public function getTrafficTimeline(Request $request): JsonResponse
    {
        @set_time_limit(5);

        $period = $request->query('period', '24h');
        if (!in_array($period, ['1h', '24h'])) {
            $period = '24h';
        }

        $startDate = match ($period) {
            '1h' => now()->subHour(),
            '24h' => now()->subDay(),
            default => now()->subDay(),
        };

        $data = Cache::remember("traffic:timeline:{$period}", 5, function () use ($startDate) {
            $connection = DB::connection()->getDriverName();
            $bucketExpr = match ($connection) {
                'sqlite' => "strftime('%Y-%m-%d %H:00:00', timestamp)",
                default => "DATE_FORMAT(timestamp, '%Y-%m-%d %H:00:00')",
            };

            $rows = TrafficLog::where('timestamp', '>=', $startDate)
                ->selectRaw("{$bucketExpr} as bucket")
                ->selectRaw('COALESCE(SUM(bytes_sent), 0) as upload_bytes')
                ->selectRaw('COALESCE(SUM(bytes_received), 0) as download_bytes')
                ->selectRaw('COALESCE(SUM(packet_count), 0) as packet_count')
                ->groupBy('bucket')
                ->orderBy('bucket')
                ->limit(24)
                ->get();

            return $rows->map(function ($row) {
                $parsed = Carbon::createFromFormat('Y-m-d H:i:s', $row->bucket);
                return [
                    'time' => $parsed->format('H:i'),
                    'upload' => round(((int) $row->upload_bytes) / (1024 * 1024), 2),
                    'download' => round(((int) $row->download_bytes) / (1024 * 1024), 2),
                    'packet_count' => (int) $row->packet_count,
                ];
            })->values();
        });

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Get the most recent packets for the live packets page.
     */
    public function getLivePackets(Request $request): JsonResponse
    {
        $period = $request->query('period', '1h');
        $limit = max(1, min(500, (int) $request->query('limit', 100)));

        $startDate = match ($period) {
            '5m' => now()->subMinutes(5),
            '1h' => now()->subHour(),
            '24h' => now()->subDay(),
            '7d' => now()->subDays(7),
            default => now()->subHour(),
        };

        $packets = TrafficLog::with('device')
            ->where('timestamp', '>=', $startDate)
            ->orderByDesc('timestamp')
            ->limit($limit)
            ->get()
            ->map(function (TrafficLog $log) {
                return [
                    'id' => $log->id,
                    'device' => $log->device ? [
                        'id' => $log->device->id,
                        'name' => $log->device->device_name,
                        'display_name' => $log->device->device_name ?: ($log->device->metadata['hostname'] ?? $log->device->ip_address),
                        'ip_address' => $log->device->ip_address,
                        'mac_address' => $log->device->mac_address,
                        'device_type' => $log->device->device_type,
                    ] : null,
                    'source_ip' => $log->source_ip,
                    'destination_ip' => $log->destination_ip ?? $log->dest_ip,
                    'source_port' => $log->source_port ?? null,
                    'destination_port' => $log->destination_port ?? $log->dest_port,
                    'protocol' => $log->protocol,
                    'size_bytes' => (int) $log->bytes_sent + (int) $log->bytes_received,
                    'packet_count' => (int) $log->packet_count,
                    'domain' => $log->domain,
                    'url' => $log->url,
                    'content_type' => $log->content_type,
                    'user_agent' => $log->user_agent,
                    'timestamp' => $log->timestamp?->toIso8601String() ?? $log->recorded_at?->toIso8601String(),
                    'metadata' => $log->metadata ?? [],
                ];
            })
            ->values();

        return response()->json([
            'success' => true,
            'data' => $packets,
            'count' => $packets->count(),
        ]);
    }

    /**
     * Return a simple network topology graph for the dashboard.
     */
    public function getTopology(Request $request): JsonResponse
    {
        $period = $request->query('period', '24h');
        $startDate = match ($period) {
            '5m' => now()->subMinutes(5),
            '1h' => now()->subHour(),
            '24h' => now()->subDay(),
            '7d' => now()->subDays(7),
            default => now()->subDay(),
        };

        $devices = Device::whereNotNull('last_seen')
            ->where('last_seen', '>=', $startDate)
            ->orderBy('device_type')
            ->orderBy('device_name')
            ->get();

        $gateway = $devices->firstWhere('device_type', 'router') ?? $devices->firstWhere('device_type', 'hotspot_gateway');
        $internetId = 'internet';
        $routerId = $gateway ? 'gateway-' . $gateway->id : 'router';

        $nodes = [
            [
                'id' => $internetId,
                'type' => 'input',
                'position' => ['x' => 420, 'y' => 20],
                'data' => ['label' => 'Internet'],
            ],
            [
                'id' => $routerId,
                'type' => 'default',
                'position' => ['x' => 400, 'y' => 140],
                'data' => ['label' => $gateway?->device_name ?: 'Router'],
            ],
        ];

        $edges = [
            [
                'id' => 'edge-internet-router',
                'source' => $internetId,
                'target' => $routerId,
                'animated' => true,
            ],
        ];

        $activeDevices = $devices->reject(fn ($device) => $device->device_type === 'router' || $device->device_type === 'hotspot_gateway')->values();
        $spacing = 220;
        $startX = max(80, 200 - (count($activeDevices) * $spacing) / 2);

        foreach ($activeDevices as $index => $device) {
            $nodeId = 'device-' . $device->id;
            $nodes[] = [
                'id' => $nodeId,
                'type' => 'default',
                'position' => ['x' => $startX + ($index * $spacing), 'y' => 320],
                'data' => [
                    'label' => $device->device_name ?: ($device->metadata['hostname'] ?? $device->ip_address),
                    'subLabel' => $device->ip_address,
                    'is_online' => $device->is_online ?? false,
                    'device_type' => $device->device_type,
                    'vendor' => $device->vendor,
                    'mac_address' => $device->mac_address,
                    'last_seen' => $device->last_seen?->toIso8601String(),
                ],
            ];

            $edges[] = [
                'id' => 'edge-' . $routerId . '-' . $nodeId,
                'source' => $routerId,
                'target' => $nodeId,
            ];
        }

        return response()->json([
            'success' => true,
            'data' => [
                'nodes' => $nodes,
                'edges' => $edges,
                'summary' => [
                    'devices' => $activeDevices->count(),
                    'online' => $activeDevices->where('is_online', true)->count(),
                    'gateway_present' => (bool) $gateway,
                    'window' => $period,
                ],
            ],
        ]);
    }

    /**
     * Return summary data for reports.
     */
    public function getReportsSummary(Request $request): JsonResponse
    {
        $ranges = [
            'daily' => now()->subDay(),
            'weekly' => now()->subDays(7),
            'monthly' => now()->subDays(30),
        ];

        $summary = [];
        foreach ($ranges as $label => $startDate) {
            $agg = TrafficLog::where('timestamp', '>=', $startDate)
                ->selectRaw('COALESCE(SUM(bytes_sent), 0) as total_sent')
                ->selectRaw('COALESCE(SUM(bytes_received), 0) as total_received')
                ->selectRaw('COALESCE(SUM(packet_count), 0) as total_packets')
                ->selectRaw('COUNT(DISTINCT device_id) as device_count')
                ->first();

            $threats = Threat::where('detected_at', '>=', $startDate)->count();

            $summary[$label] = [
                'traffic_mb' => round((((int) $agg->total_sent + (int) $agg->total_received) / (1024 * 1024)), 2),
                'packets' => (int) $agg->total_packets,
                'devices' => (int) $agg->device_count,
                'threats' => $threats,
            ];
        }

        $topDevices = TrafficLog::with('device')
            ->where('timestamp', '>=', now()->subDay())
            ->selectRaw('device_id, COALESCE(SUM(bytes_sent), 0) + COALESCE(SUM(bytes_received), 0) as total_bytes, COALESCE(SUM(packet_count), 0) as total_packets')
            ->groupBy('device_id')
            ->orderByDesc('total_bytes')
            ->limit(5)
            ->get()
            ->map(function ($row) {
                return [
                    'device_id' => $row->device_id,
                    'device_name' => $row->device?->device_name ?: ($row->device?->metadata['hostname'] ?? $row->device?->ip_address ?? 'Unknown device'),
                    'ip_address' => $row->device?->ip_address,
                    'total_mb' => round(((int) $row->total_bytes) / (1024 * 1024), 2),
                    'packets' => (int) $row->total_packets,
                ];
            })
            ->values();

        $protocols = TrafficLog::where('timestamp', '>=', now()->subDay())
            ->selectRaw('COALESCE(protocol, "Unknown") as protocol')
            ->selectRaw('COUNT(*) as packets')
            ->groupBy('protocol')
            ->orderByDesc('packets')
            ->limit(6)
            ->get()
            ->map(fn ($row) => [
                'protocol' => $row->protocol,
                'packets' => (int) $row->packets,
            ])
            ->values();

        return response()->json([
            'success' => true,
            'data' => [
                'summary' => $summary,
                'top_devices' => $topDevices,
                'protocols' => $protocols,
            ],
        ]);
    }

    /**
     * Get content type distribution
     */
    public function getContentDistribution(int $deviceId, Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->bandwidthService->getBandwidthByContent($deviceId, $request->query('period', '24h')),
        ]);
    }

    /**
     * Get top domains for a device.
     */
    public function getTopDomains(int $deviceId, Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->bandwidthService->getTopDomains(
                $deviceId,
                (int) $request->query('limit', 10),
                $request->query('period', '24h')
            ),
        ]);
    }

    private function resolveDeviceForIp(string $ip, array $stats = []): Device
    {
        // Only create individual Device records for local/private IPs.
        if (!$this->isPrivateIp($ip)) {
            return Device::firstOrCreate(
                ['mac_address' => 'external-network'],
                [
                    'ip_address'  => null,
                    'device_name' => 'External Hosts',
                    'device_type' => 'external',
                    'vendor'      => 'external',
                    'is_online'   => true,
                ]
            );
        }

        // Skip broadcast addresses entirely
        if (str_ends_with($ip, '.255') || $ip === '255.255.255.255') {
            return Device::firstOrCreate(
                ['mac_address' => 'external-network'],
                ['ip_address' => null, 'device_name' => 'External Hosts', 'device_type' => 'external', 'vendor' => 'external', 'is_online' => true]
            );
        }

        $macAddress = $this->normalizeMacAddress((string) ($stats['mac_address'] ?? ''));
        $isAutoMac  = str_starts_with($macAddress ?: '', 'auto-');

        // --- Step 1: Try to find an existing record for this IP ---
        // Priority: real MAC match > IP match (prefer real MAC over auto-generated)
        $device = null;

        if ($macAddress && !$isAutoMac) {
            // We have a real MAC — look it up first
            $device = Device::where('mac_address', $macAddress)->first();

            if (!$device) {
                // No real-MAC record; check if an auto-MAC record exists for same IP and upgrade it
                $autoDevice = Device::where('ip_address', $ip)
                    ->where('mac_address', 'like', 'auto-%')
                    ->orderByDesc('last_seen')
                    ->first();
                if ($autoDevice) {
                    // Upgrade the auto-MAC record to the real MAC
                    $autoDevice->mac_address = $macAddress;
                    $device = $autoDevice;
                }
            }
        }

        if (!$device) {
            // Fall back: look up by IP to avoid creating a duplicate
            $device = Device::where('ip_address', $ip)
                ->orderByDesc('last_seen')
                ->first();
        }

        $deviceExists = (bool) $device;

        if (!$device) {
            $device = new Device();
            $device->mac_address = $macAddress ?: $this->generateMacAddress($ip);
            $device->ip_address  = $ip;
            $device->first_seen  = now();
        }

        // Update IP if it has changed (DHCP re-assignment)
        if ($device->ip_address !== $ip) {
            $device->ip_address = $ip;
        }

        // Upgrade auto-MAC to real MAC when available
        if ($macAddress && !$isAutoMac) {
            $device->mac_address = $macAddress;
        }

        $lastSeen = isset($stats['last_seen']) && $stats['last_seen'] ? Carbon::parse($stats['last_seen']) : now();
        $isOnline = $lastSeen->diffInSeconds(now()) < 60;

        $fillData = [
            'device_name' => $this->resolveDeviceName($ip, $stats, $device),
            'device_type' => $stats['device_type'] ?? $device->device_type,
            'vendor'      => $stats['vendor'] ?? $device->vendor,
            'is_online'   => $isOnline,
            'last_seen'   => $lastSeen,
        ];

        $device->fill($fillData);

        $metadata = $device->metadata ?? [];
        if (!empty($stats['os_guess'])) {
            $metadata['os_guess'] = $stats['os_guess'];
        }
        if (!empty($stats['fingerprint'])) {
            $metadata['fingerprint'] = $stats['fingerprint'];
        }
        if (!empty($stats['mac_address']) && empty($metadata['mac_address'])) {
            $metadata['mac_address'] = $stats['mac_address'];
        }
        if (!empty($metadata)) {
            $device->metadata = $metadata;
        }

        if (!$device->first_seen) {
            $device->first_seen = now();
        }

        $device->save();

        // Delete any remaining auto-MAC duplicates for this IP now that we have a canonical record
        if ($macAddress && !$isAutoMac) {
            Device::where('ip_address', $ip)
                ->where('mac_address', 'like', 'auto-%')
                ->where('id', '!=', $device->id)
                ->delete();
        }

        if (!$deviceExists && $device->mac_address !== 'external-network') {
            \App\Models\Alert::create([
                'type'      => 'device_joined',
                'title'     => 'New Device Discovered',
                'message'   => 'A new device has joined the network: ' . ($device->device_name ?: 'Unknown device') . " ({$ip})",
                'device_id' => $device->id,
            ]);
        }

        return $device;
    }


    /**
     * Resolve a stable, human-readable label for a device.
     */
    private function resolveDeviceName(string $ip, array $stats, Device $device): string
    {
        $candidates = [
            $stats['device_name'] ?? null,
            $stats['hostname'] ?? null,
            $stats['host_name'] ?? null,
            $stats['computer_name'] ?? null,
            $stats['name'] ?? null,
            $device->device_name,
        ];

        foreach ($candidates as $candidate) {
            if (is_string($candidate)) {
                $candidate = trim($candidate);
                if ($candidate !== '' && !str_starts_with($candidate, 'Device ')) {
                    return $candidate;
                }
            }
        }

        // Try NetBIOS name resolution (nbtstat)
        if ($this->isPrivateIp($ip)) {
            $name = $this->resolveNetBIOSName($ip);
            if ($name) {
                return $name;
            }
        }

        return 'Device ' . $ip;
    }

    private function resolveNetBIOSName(string $ip): ?string
    {
        try {
            $output = shell_exec("nbtstat -A {$ip} 2>&1");
            if ($output && preg_match('/Registered\s+.*\s+(\S+)\s+<00>/', $output, $matches)) {
                $name = trim($matches[1]);
                if ($name !== '' && $name !== '__MSBROWSE__') {
                    return $name;
                }
            }
            if ($output && preg_match('/Registered\s+.*\s+(\S+)\s+<20>/', $output, $matches)) {
                $name = trim($matches[1]);
                if ($name !== '') {
                    return $name;
                }
            }
        } catch (\Exception $e) {
            // Ignore
        }
        return null;
    }

    /**
     * Determine if an IP is from a private/local range.
     */
    private function getBlockedDeviceIds(): array
    {
        try {
            return \App\Models\Device::whereJsonContains('metadata->is_blocked', true)
                ->pluck('id')
                ->toArray();
        } catch (\Exception $e) {
            return [];
        }
    }

    private function isPrivateIp(string $ip): bool
    {
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            return false;
        }

        // Only handle IPv4 here for simplicity; treat IPv6 non-ULA as public.
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $long = ip2long($ip);
            // 10.0.0.0/8
            if ($long >= ip2long('10.0.0.0') && $long <= ip2long('10.255.255.255')) {
                return true;
            }
            // 172.16.0.0/12
            if ($long >= ip2long('172.16.0.0') && $long <= ip2long('172.31.255.255')) {
                return true;
            }
            // 192.168.0.0/16
            if ($long >= ip2long('192.168.0.0') && $long <= ip2long('192.168.255.255')) {
                return true;
            }
            // Link-local 169.254.0.0/16
            if ($long >= ip2long('169.254.0.0') && $long <= ip2long('169.254.255.255')) {
                return true;
            }
            // Loopback
            if ($long >= ip2long('127.0.0.0') && $long <= ip2long('127.255.255.255')) {
                return true;
            }
            return false;
        }

        // IPv6: treat unique local addresses (fc00::/7) as private
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            return str_starts_with($ip, 'fc') || str_starts_with($ip, 'FD') || str_starts_with($ip, 'fd');
        }

        return false;
    }

    private function storeBandwidthSnapshot(Device $device, array $stats): int
    {
        $lastSnapshot = BandwidthSnapshot::where('device_id', $device->id)
            ->orderBy('recorded_at', 'desc')
            ->first();

        $uploadSpeed = 0;
        $downloadSpeed = 0;

        if ($lastSnapshot) {
            $seconds = max(1, now()->diffInSeconds($lastSnapshot->recorded_at));
            $sentDiff = max(0, ((int) ($stats['bytes_sent'] ?? 0)) - $lastSnapshot->total_bytes_sent);
            $recvDiff = max(0, ((int) ($stats['bytes_received'] ?? 0)) - $lastSnapshot->total_bytes_received);
            
            // Speed in kbps
            $uploadSpeed = ($sentDiff * 8) / $seconds / 1000;
            $downloadSpeed = ($recvDiff * 8) / $seconds / 1000;
            
            $limitMbps = $device->metadata['bandwidth_limit_mbps'] ?? null;
            if ($limitMbps) {
                $currentMbps = ($uploadSpeed + $downloadSpeed) / 1000;
                if ($currentMbps > $limitMbps) {
                    $cacheKey = "alert:bandwidth:{$device->id}";
                    if (!Cache::has($cacheKey)) {
                        Cache::put($cacheKey, true, now()->addMinutes(10));
                        \App\Models\Alert::create([
                            'type' => 'high_bandwidth',
                            'title' => 'High Bandwidth Usage Alert',
                            'message' => "Device " . ($device->device_name ?: $device->ip_address) . " exceeded its bandwidth limit of {$limitMbps} Mbps (current: " . round($currentMbps, 2) . " Mbps)",
                            'device_id' => $device->id,
                        ]);
                    }
                }
            }
        }

        BandwidthSnapshot::create([
            'device_id' => $device->id,
            'upload_speed_kbps' => round($uploadSpeed, 2),
            'download_speed_kbps' => round($downloadSpeed, 2),
            'total_bytes_sent' => (int) ($stats['bytes_sent'] ?? 0),
            'total_bytes_received' => (int) ($stats['bytes_received'] ?? 0),
            'packet_loss_percent' => 0,
            'latency_ms' => 0,
            'recorded_at' => now(),
            'measured_at' => now(),
        ]);

        return 1;
    }

    private function generateMacAddress(string $ip): string
    {
        return 'auto-' . substr(sha1($ip), 0, 12);
    }

    private function normalizeMacAddress(string $macAddress): string
    {
        $normalized = strtolower(trim(str_replace('-', ':', $macAddress)));

        if ($normalized === '' || $normalized === 'incomplete' || $normalized === 'unknown') {
            return '';
        }

        return $normalized;
    }

    private function buildThreatMetadata(array $threat): array
    {
        $packetInfo = is_array($threat['packet_info'] ?? null) ? $threat['packet_info'] : [];

        return array_filter([
            'domain' => $threat['domain'] ?? $packetInfo['domain'] ?? $packetInfo['hostname'] ?? null,
            'hostname' => $packetInfo['hostname'] ?? null,
            'browser' => $packetInfo['browser'] ?? null,
            'user_agent' => $packetInfo['user_agent'] ?? null,
            'app_name' => $packetInfo['app_name'] ?? null,
        ], static fn ($value) => $value !== null && $value !== '');
    }

    /**
     * Broadcast a packet JSON payload to the local WebSocket server via UDP.
     */
    private function broadcastPacket(array $packetData): void
    {
        try {
            $socket = @socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
            if ($socket) {
                $payload = json_encode($packetData);
                @socket_sendto($socket, $payload, strlen($payload), 0, '127.0.0.1', 6002);
                @socket_close($socket);
            }
        } catch (\Throwable $e) {
            // Ignore socket errors to keep ingestion working even if WS is stopped
        }
    }

    /**
     * Broadcast a threat JSON payload to the local WebSocket server via UDP.
     */
    private function broadcastThreat(array $threatData): void
    {
        try {
            $socket = @socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
            if ($socket) {
                $payload = json_encode($threatData);
                @socket_sendto($socket, $payload, strlen($payload), 0, '127.0.0.1', 6002);
                @socket_close($socket);
            }
        } catch (\Throwable $e) {
            // Ignore socket errors to keep ingestion working even if WS is stopped
        }
    }
}
