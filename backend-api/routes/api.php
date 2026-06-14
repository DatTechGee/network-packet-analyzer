<?php

use App\Http\Controllers\DeviceController;
use App\Http\Controllers\ThreatController;
use App\Http\Controllers\TrafficController;
use App\Http\Controllers\SpeedTestController;
use App\Http\Controllers\AlertController;
use Illuminate\Support\Facades\Route;

// Device routes
Route::prefix('devices')->group(function () {
    Route::get('/', [DeviceController::class, 'index']);
    Route::post('/register', [DeviceController::class, 'register']);
    Route::get('/{deviceId}', [DeviceController::class, 'show']);
    Route::patch('/{deviceId}', [DeviceController::class, 'update']);
    Route::post('/{deviceId}/block-domain', [DeviceController::class, 'blockDomain']);
    Route::post('/{deviceId}/unblock-domain', [DeviceController::class, 'unblockDomain']);
    Route::post('/{deviceId}/block', [DeviceController::class, 'blockDevice']);
    Route::post('/{deviceId}/unblock', [DeviceController::class, 'unblockDevice']);
    Route::get('/blocked/list', [DeviceController::class, 'getBlockedDevices']);
    Route::post('/{deviceId}/bandwidth-limit', [DeviceController::class, 'setBandwidthLimit']);
    Route::get('/{deviceId}/connections', [DeviceController::class, 'connections']);
});

// Traffic routes
Route::prefix('traffic')->group(function () {
    Route::post('/record', [TrafficController::class, 'recordTraffic']);
    Route::get('/stats', [TrafficController::class, 'getTrafficStats']);
    Route::get('/timeline', [TrafficController::class, 'getTrafficTimeline']);
    Route::get('/live-packets', [TrafficController::class, 'getLivePackets']);
    Route::get('/device/{deviceId}', [TrafficController::class, 'getDeviceTraffic']);
    Route::get('/device/{deviceId}/content', [TrafficController::class, 'getContentDistribution']);
    Route::get('/device/{deviceId}/top-domains', [TrafficController::class, 'getTopDomains']);
});

Route::get('/topology', [TrafficController::class, 'getTopology']);
Route::get('/reports/summary', [TrafficController::class, 'getReportsSummary']);

Route::post('/data/ingest', [TrafficController::class, 'ingest'])->withoutMiddleware('throttle:api');

// Threat routes
Route::prefix('threats')->group(function () {
    Route::get('/', [ThreatController::class, 'index']);
    Route::get('/stats', [ThreatController::class, 'getStats']);
    Route::post('/analyze', [ThreatController::class, 'analyzeTraffic']);
    Route::get('/device/{deviceId}', [ThreatController::class, 'deviceThreats']);
    Route::patch('/{threatId}/block', [ThreatController::class, 'blockThreat']);
    Route::patch('/{threatId}/resolve', [ThreatController::class, 'resolveThreat']);
});

// Speed test routes
Route::prefix('speedtest')->group(function () {
    Route::get('/ping', [SpeedTestController::class, 'ping']);
    Route::get('/download', [SpeedTestController::class, 'download']);
    Route::post('/upload', [SpeedTestController::class, 'upload']);
    Route::post('/run', [SpeedTestController::class, 'run']);
    Route::get('/history', [SpeedTestController::class, 'history']);
});

// Alerts/Notifications routes
Route::prefix('alerts')->group(function () {
    Route::get('/', [AlertController::class, 'index']);
    Route::patch('/read-all', [AlertController::class, 'markAllAsRead']);
    Route::patch('/{id}/read', [AlertController::class, 'markAsRead']);
    Route::delete('/clear', [AlertController::class, 'destroy']);
});

// Blocked domains endpoint (for router agent enforcement)
Route::get('/blocked-domains', function () {
    try {
        $blocked = [];
        $devices = \App\Models\Device::all(['id', 'metadata']);
        foreach ($devices as $device) {
            $metadata = $device->metadata;
            if (!is_array($metadata)) {
                continue;
            }
            $domains = $metadata['blocked_domains'] ?? [];
            if (!is_array($domains)) {
                continue;
            }
            foreach ($domains as $domain) {
                if (is_string($domain) && $domain !== '') {
                    $blocked[$domain] = $device->id;
                }
            }
        }
        return response()->json(['blocked_domains' => array_keys($blocked)]);
    } catch (\Exception $e) {
        return response()->json(['blocked_domains' => [], 'error' => $e->getMessage()]);
    }
})->withoutMiddleware('throttle:api');

// Health check
Route::get('/health', function () {
    return response()->json(['status' => 'ok']);
})->withoutMiddleware('throttle:api');
