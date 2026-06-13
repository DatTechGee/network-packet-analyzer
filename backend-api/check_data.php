<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$kernel->handle($request = Illuminate\Http\Request::capture());

use App\Models\TrafficLog;
use App\Models\Device;
use App\Models\Threat;

echo "=== DEVICES (last 5) ===\n";
$devices = Device::orderBy('created_at', 'desc')->limit(5)->get();
foreach ($devices as $d) {
    echo "ID: {$d->id} | IP: {$d->ip_address} | Name: {$d->device_name} | Last Seen: {$d->last_seen}\n";
}

echo "\n=== TRAFFIC LOGS WITH DOMAINS (last 20) ===\n";
$logs = TrafficLog::whereNotNull('domain')->orderBy('created_at', 'desc')->limit(20)->get();
echo "Found " . $logs->count() . " logs with domains\n";
foreach ($logs as $log) {
    echo "Device: {$log->device_id} | Domain: {$log->domain} | Protocol: {$log->protocol} | Size: {$log->bytes_sent} | Time: {$log->timestamp}\n";
}

echo "\n=== ALL TRAFFIC LOGS (last 10) ===\n";
$all_logs = TrafficLog::orderBy('created_at', 'desc')->limit(10)->get();
echo "Found " . $all_logs->count() . " traffic logs total\n";
foreach ($all_logs as $log) {
    echo "Device: {$log->device_id} | Protocol: {$log->protocol} | Domain: {$log->domain} | Bytes: {$log->bytes_sent}\n";
}

echo "\n=== THREATS (last 10) ===\n";
$threats = Threat::orderBy('created_at', 'desc')->limit(10)->get();
echo "Found " . $threats->count() . " threats\n";
foreach ($threats as $t) {
    echo "Device: {$t->device_id} | Type: {$t->threat_type} | Level: {$t->threat_level} | Desc: {$t->description}\n";
}

echo "\nDone.\n";
