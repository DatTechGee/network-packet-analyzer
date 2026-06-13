<?php
require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Carbon\Carbon;
use App\Models\Device;

$window = Carbon::now()->subMinutes(15);
echo "Active window: " . $window . "\n\n";

// Simulate the updated controller query (same as DeviceController::index)
$devices = Device::where(function ($builder) use ($window) {
        $builder->orWhere(function ($b) use ($window) {
                $b->whereIn('device_type', ['router', 'hotspot_gateway'])
                    ->whereNotNull('last_seen')
                    ->where('last_seen', '>=', $window);
            })
            ->orWhere(function ($b) use ($window) {
                $b->whereNotNull('last_seen')
                    ->where('last_seen', '>=', $window)
                    ->where('mac_address', '!=', 'external-network');
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
    ->orderByDesc('last_seen')
    ->get(['id', 'mac_address', 'ip_address', 'device_name', 'device_type', 'last_seen']);

echo "Devices after all filters (" . count($devices) . " total):\n";
foreach ($devices as $d) {
    echo "  ID={$d->id} | MAC={$d->mac_address} | IP={$d->ip_address} | Name={$d->device_name} | Type={$d->device_type}\n";
}

echo "\n--- Deduplication by IP (gateway by IP, others by IP) ---\n";
$seen = [];
$unique = [];
foreach ($devices as $d) {
    if (in_array($d->device_type, ['router', 'hotspot_gateway'])) {
        $key = 'gateway:' . strtolower(trim((string)$d->ip_address));
    } else {
        $ip = trim((string)$d->ip_address);
        $key = $ip !== '' ? 'ip:' . $ip : 'id:' . $d->id;
    }
    echo "  ID={$d->id} => key={$key}\n";
    if (!isset($seen[$key])) {
        $seen[$key] = true;
        $unique[$key] = $d;
    }
}
echo "\nFINAL: " . count($unique) . " unique active devices\n";
foreach ($unique as $key => $d) {
    echo "  $key => ID={$d->id}, MAC={$d->mac_address}, IP={$d->ip_address}, Name={$d->device_name}\n";
}
