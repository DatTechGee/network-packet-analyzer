<?php
require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "Traffic logs (last 5 min): " . App\Models\TrafficLog::where('created_at', '>=', now()->subMinutes(5))->count() . PHP_EOL;
echo "Traffic logs (last 1 min): " . App\Models\TrafficLog::where('created_at', '>=', now()->subMinutes(1))->count() . PHP_EOL;
echo "Devices updated (last 5 min): " . App\Models\Device::where('last_seen', '>=', now()->subMinutes(5))->count() . PHP_EOL;
echo "Devices updated (last 1 min): " . App\Models\Device::where('last_seen', '>=', now()->subMinutes(1))->count() . PHP_EOL;

$latest = App\Models\TrafficLog::orderBy('id', 'desc')->first();
if ($latest) {
    echo "Latest traffic log ID: {$latest->id} created at: {$latest->created_at} (" . $latest->created_at->diffInMinutes(now()) . " min ago)" . PHP_EOL;
    echo "  device_id={$latest->device_id} src={$latest->src_ip} dst={$latest->dst_ip} ts={$latest->timestamp}" . PHP_EOL;
}

echo PHP_EOL . "=== All devices (any last_seen) ===" . PHP_EOL;
$devices = App\Models\Device::orderBy('last_seen', 'desc')->take(10)->get();
foreach ($devices as $d) {
    $diff = $d->last_seen ? $d->last_seen->diffInSeconds(now()) . 's ago' : 'never';
    echo "{$d->id}: {$d->device_name} IP={$d->ip_address} last_seen={$d->last_seen} ({$diff}) online=" . ($d->is_online ? 'Y' : 'N') . PHP_EOL;
}
