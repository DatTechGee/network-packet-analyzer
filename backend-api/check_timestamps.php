<?php

require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

// Get current time
$now = Carbon::now();
$oneDayAgo = $now->copy()->subDay();

echo "Current time: " . $now->format('Y-m-d H:i:s') . " UTC" . PHP_EOL;
echo "One day ago: " . $oneDayAgo->format('Y-m-d H:i:s') . " UTC" . PHP_EOL;
echo PHP_EOL;

// Get traffic logs with timestamps
$logs = DB::table('traffic_logs')
    ->select('id', 'device_id', 'timestamp', 'bytes_sent', 'bytes_received')
    ->orderBy('timestamp', 'desc')
    ->limit(5)
    ->get();

echo "=== RECENT TRAFFIC LOGS ===\n";
foreach ($logs as $log) {
    $logTime = Carbon::parse($log->timestamp);
    $daysOld = $logTime->diffInHours($now) / 24;
    echo "ID: {$log->id} | Device: {$log->device_id} | Time: {$log->timestamp} | Bytes: {$log->bytes_sent}/{$log->bytes_received} | Age: {$daysOld} days\n";
}

// Count logs in past 24 hours
$logsIn24h = DB::table('traffic_logs')
    ->where('timestamp', '>=', $oneDayAgo)
    ->count();

echo "\nTraffic logs in past 24 hours: " . $logsIn24h . PHP_EOL;

// Get sum of bytes
$stats = DB::table('traffic_logs')
    ->where('timestamp', '>=', $oneDayAgo)
    ->selectRaw('SUM(bytes_sent) as total_sent, SUM(bytes_received) as total_received')
    ->first();

echo "Total sent: " . $stats->total_sent . " bytes = " . round($stats->total_sent / (1024 * 1024), 2) . " MB\n";
echo "Total received: " . $stats->total_received . " bytes = " . round($stats->total_received / (1024 * 1024), 2) . " MB\n";
