<?php

namespace App\Http\Controllers;

use App\Models\Threat;
use App\Services\ThreatAnalysisService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ThreatController extends Controller
{
    private ThreatAnalysisService $threatService;

    public function __construct(ThreatAnalysisService $threatService)
    {
        $this->threatService = $threatService;
    }

    /**
     * Get all threats
     */
    public function index(Request $request): JsonResponse
    {
        $level = $request->query('level');
        $period = $request->query('period', '24h');
        $limit = $request->query('limit', 50);

        $startDate = match ($period) {
            '5m' => now()->subMinutes(5),
            '15m' => now()->subMinutes(15),
            '30m' => now()->subMinutes(30),
            '1h' => now()->subHour(),
            '24h' => now()->subDay(),
            '7d' => now()->subDays(7),
            '30d' => now()->subDays(30),
            default => now()->subDay(),
        };

        $query = Threat::where('detected_at', '>=', $startDate)
            ->orderBy('detected_at', 'desc');

        if ($level && $level !== 'all') {
            $query->where('threat_level', $level);
        } else {
            $query->whereIn('threat_level', ['low', 'medium', 'high', 'critical']);
        }

        $threats = $query->limit($limit)->get();

        return response()->json([
            'success' => true,
            'data' => $threats,
            'count' => $threats->count(),
        ]);
    }

    /**
     * Get threats for device
     */
    public function deviceThreats(int $deviceId, Request $request): JsonResponse
    {
        $period = $request->query('period', '24h');
        $limit = $request->query('limit', 50);

        $startDate = match ($period) {
            '5m' => now()->subMinutes(5),
            '15m' => now()->subMinutes(15),
            '30m' => now()->subMinutes(30),
            '1h' => now()->subHour(),
            '24h' => now()->subDay(),
            '7d' => now()->subDays(7),
            '30d' => now()->subDays(30),
            default => now()->subDay(),
        };

        $threats = Threat::where('device_id', $deviceId)
            ->where('detected_at', '>=', $startDate)
            ->orderBy('detected_at', 'desc')
            ->limit($limit)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $threats,
            'count' => $threats->count(),
        ]);
    }

    /**
     * Analyze traffic and detect threats
     */
    public function analyzeTraffic(Request $request): JsonResponse
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
            'domain' => 'nullable|string',
            'hostname' => 'nullable|string',
            'browser' => 'nullable|string',
            'user_agent' => 'nullable|string',
            'app_name' => 'nullable|string',
        ]);

        $detectedThreats = $this->threatService->analyzeTraffic($validated);

        return response()->json([
            'success' => true,
            'threats_detected' => count($detectedThreats),
            'data' => $detectedThreats,
        ]);
    }

    /**
     * Get threat statistics
     */
    public function getStats(Request $request): JsonResponse
    {
        $period = $request->query('period', '24h');

        $stats = $this->threatService->getThreatStats($period);

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    /**
     * Block a threat
     */
    public function blockThreat(int $threatId): JsonResponse
    {
        $threat = Threat::findOrFail($threatId);
        $threat->update(['blocked' => true, 'is_resolved' => true]);

        return response()->json([
            'success' => true,
            'message' => 'Threat blocked successfully',
            'data' => $threat,
        ]);
    }

    /**
     * Resolve threat
     */
    public function resolveThreat(int $threatId, Request $request): JsonResponse
    {
        $threat = Threat::findOrFail($threatId);
        $threat->update([
            'is_resolved' => true,
            'blocked' => $request->boolean('block', false),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Threat resolved',
            'data' => $threat,
        ]);
    }
}
