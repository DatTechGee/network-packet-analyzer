<?php

namespace App\Services;

use App\Models\BandwidthSnapshot;
use App\Models\Device;
use App\Models\TrafficLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

class BandwidthCalculatorService
{
    /**
     * Calculate real-time bandwidth for a device
     */
    public function calculateRealTimeBandwidth(int $deviceId, int $intervalSeconds = 30): array
    {
        return Cache::remember("bandwidth:realtime:{$deviceId}:{$intervalSeconds}", 5, function () use ($deviceId, $intervalSeconds) {
            $startTime = Carbon::now()->subSeconds($intervalSeconds);

            $metrics = TrafficLog::where('device_id', $deviceId)
                ->where('recorded_at', '>=', $startTime)
                ->selectRaw('COALESCE(SUM(bytes_sent), 0) as total_upload_bytes, COALESCE(SUM(bytes_received), 0) as total_download_bytes, COALESCE(SUM(packet_count), 0) as packet_count')
                ->first();

            $totalUpload = (int) ($metrics->total_upload_bytes ?? 0);
            $totalDownload = (int) ($metrics->total_download_bytes ?? 0);

            // Convert to kbps
            $uploadKbps = ($totalUpload * 8) / $intervalSeconds / 1000;
            $downloadKbps = ($totalDownload * 8) / $intervalSeconds / 1000;

            return [
                'upload_kbps' => round($uploadKbps, 2),
                'download_kbps' => round($downloadKbps, 2),
                'total_upload_bytes' => $totalUpload,
                'total_download_bytes' => $totalDownload,
                'packet_count' => (int) ($metrics->packet_count ?? 0),
            ];
        });
    }

    /**
     * Get bandwidth per application/service
     */
    public function getBandwidthByContent(int $deviceId, string $period = '24h'): array
    {
        return Cache::remember("bandwidth:content:{$deviceId}:{$period}", 10, function () use ($deviceId, $period) {
            $startDate = $this->getPeriodDate($period);

            $data = TrafficLog::where('device_id', $deviceId)
                ->where('timestamp', '>=', $startDate)
                ->groupBy('content_type')
                ->selectRaw('content_type, SUM(bytes_sent + bytes_received) as total_bytes, COUNT(*) as packet_count')
                ->orderByRaw('total_bytes DESC')
                ->get()
                ->map(function ($item) {
                    return [
                        'content_type' => $item->content_type ?? 'Unknown',
                        'total_bytes' => (int) $item->total_bytes,
                        'total_mb' => round($item->total_bytes / (1024 * 1024), 2),
                        'percent' => 0,
                        'packet_count' => $item->packet_count,
                    ];
                });

            $totalBytes = $data->sum('total_bytes');
            $totalBytes = $totalBytes > 0 ? $totalBytes : 1;

            return $data->map(function ($item) use ($totalBytes) {
                $item['percent'] = round(($item['total_bytes'] / $totalBytes) * 100, 2);
                unset($item['total_bytes']);
                return $item;
            })->toArray();
        });
    }

    /**
     * Get top domains/services by bandwidth
     */
    public function getTopDomains(int $deviceId, int $limit = 10, string $period = '24h'): array
    {
        return Cache::remember("bandwidth:domains:{$deviceId}:{$limit}:{$period}", 10, function () use ($deviceId, $limit, $period) {
            $startDate = $this->getPeriodDate($period);
            $connection = TrafficLog::query()->getConnection()->getDriverName();
            $domainExpr = match ($connection) {
                'sqlite' => "COALESCE(NULLIF(domain, ''), NULLIF(url, ''), NULLIF(json_extract(metadata, '$.hostname'), ''))",
                default => "COALESCE(NULLIF(domain, ''), NULLIF(url, ''), NULLIF(JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.hostname')), ''))",
            };

            return TrafficLog::where('device_id', $deviceId)
                ->where('recorded_at', '>=', $startDate)
                ->whereRaw("{$domainExpr} IS NOT NULL")
                ->groupByRaw($domainExpr)
                ->selectRaw("{$domainExpr} as domain, SUM(bytes_sent + bytes_received) as total_bytes, COUNT(*) as hit_count, MAX(recorded_at) as last_seen")
                ->orderByRaw('total_bytes DESC')
                ->limit($limit)
                ->get()
                ->map(function ($item) {
                    return [
                        'domain' => $item->domain,
                        'total_mb' => round($item->total_bytes / (1024 * 1024), 2),
                        'hit_count' => (int) $item->hit_count,
                        'last_seen' => $item->last_seen ? Carbon::parse($item->last_seen)->toIso8601String() : null,
                    ];
                })
                ->toArray();
        });
    }

    /**
     * Record bandwidth snapshot
     */
    public function recordBandwidthSnapshot(int $deviceId, array $metrics): BandwidthSnapshot
    {
        return BandwidthSnapshot::create([
            'device_id' => $deviceId,
            'upload_speed_kbps' => $metrics['upload_kbps'] ?? 0,
            'download_speed_kbps' => $metrics['download_kbps'] ?? 0,
            'total_bytes_sent' => $metrics['total_upload_bytes'] ?? 0,
            'total_bytes_received' => $metrics['total_download_bytes'] ?? 0,
            'packet_loss_percent' => $metrics['packet_loss'] ?? 0,
            'latency_ms' => $metrics['latency'] ?? 0,
            'recorded_at' => now(),
            'measured_at' => now(),
        ]);
    }

    /**
     * Get average bandwidth over period
     */
    public function getAverageBandwidth(int $deviceId, string $period = '24h'): array
    {
        return Cache::remember("bandwidth:avg:{$deviceId}:{$period}", 10, function () use ($deviceId, $period) {
            $startDate = $this->getPeriodDate($period);

            $snapshotAgg = BandwidthSnapshot::where('device_id', $deviceId)
                ->whereRaw('COALESCE(measured_at, recorded_at) >= ?', [$startDate])
                ->selectRaw('COALESCE(AVG(upload_speed_kbps), 0) as avg_upload_kbps, COALESCE(AVG(download_speed_kbps), 0) as avg_download_kbps, COALESCE(MAX(upload_speed_kbps), 0) as peak_upload_kbps, COALESCE(MAX(download_speed_kbps), 0) as peak_download_kbps, COALESCE(AVG(latency_ms), 0) as avg_latency_ms')
                ->first();

            return [
                'avg_upload_kbps' => round((float) ($snapshotAgg->avg_upload_kbps ?? 0), 2),
                'avg_download_kbps' => round((float) ($snapshotAgg->avg_download_kbps ?? 0), 2),
                'peak_upload_kbps' => round((float) ($snapshotAgg->peak_upload_kbps ?? 0), 2),
                'peak_download_kbps' => round((float) ($snapshotAgg->peak_download_kbps ?? 0), 2),
                'avg_latency_ms' => round((float) ($snapshotAgg->avg_latency_ms ?? 0), 2),
            ];
        });
    }

    /**
     * Helper: Get start date from period string
     */
    private function getPeriodDate(string $period): Carbon
    {
        return match ($period) {
            '5m' => Carbon::now()->subMinutes(5),
            '1h' => Carbon::now()->subHour(),
            '24h' => Carbon::now()->subDay(),
            '7d' => Carbon::now()->subDays(7),
            '30d' => Carbon::now()->subDays(30),
            default => Carbon::now()->subDay(),
        };
    }
}
