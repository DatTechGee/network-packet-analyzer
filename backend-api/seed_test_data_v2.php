<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);

$status = $kernel->call('tinker', [
    '--execute' => <<<'PHP'
use App\Models\Device;
use App\Models\TrafficLog;
use App\Models\BandwidthSnapshot;
use Carbon\Carbon;

// Clear existing data
Device::truncate();
TrafficLog::truncate();
BandwidthSnapshot::truncate();

// Create sample devices
$device1 = Device::create([
    'mac_address' => '00:1A:2B:3C:4D:5E',
    'ip_address' => '192.168.1.100',
    'device_name' => "John's Laptop",
    'device_type' => 'laptop',
    'vendor' => 'Apple',
    'is_active' => true,
    'first_seen' => now()->subHours(24),
    'last_seen' => now(),
]);

$device2 = Device::create([
    'mac_address' => '00:1A:2B:3C:4D:5F',
    'ip_address' => '192.168.1.101',
    'device_name' => 'Work Desktop',
    'device_type' => 'desktop',
    'vendor' => 'Dell',
    'is_active' => true,
    'first_seen' => now()->subHours(24),
    'last_seen' => now(),
]);

$device3 = Device::create([
    'mac_address' => '00:1A:2B:3C:4D:5G',
    'ip_address' => '192.168.1.102',
    'device_name' => 'Mobile Phone',
    'device_type' => 'mobile',
    'vendor' => 'Samsung',
    'is_active' => true,
    'first_seen' => now()->subHours(24),
    'last_seen' => now(),
]);

// Create traffic logs for the past 24 hours
$trafficCount = 0;
$snapshotCount = 0;

for ($i = 24; $i > 0; $i--) {
    $time = now()->subHours($i);
    
    // Device 1 traffic
    for ($j = 0; $j < 3; $j++) {
        TrafficLog::create([
            'device_id' => $device1->id,
            'source_ip' => '192.168.1.100',
            'destination_ip' => '8.8.' . rand(1, 254) . '.' . rand(1, 254),
            'protocol' => ['TCP', 'UDP'][rand(0, 1)],
            'bytes_sent' => rand(1000, 10000),
            'bytes_received' => rand(5000, 50000),
            'packet_count' => rand(10, 100),
            'timestamp' => $time->copy()->addMinutes(rand(0, 59)),
        ]);
        $trafficCount++;
    }
    
    // Device 2 traffic
    for ($j = 0; $j < 2; $j++) {
        TrafficLog::create([
            'device_id' => $device2->id,
            'source_ip' => '192.168.1.101',
            'destination_ip' => '8.8.' . rand(1, 254) . '.' . rand(1, 254),
            'protocol' => ['TCP', 'UDP'][rand(0, 1)],
            'bytes_sent' => rand(2000, 20000),
            'bytes_received' => rand(10000, 100000),
            'packet_count' => rand(20, 200),
            'timestamp' => $time->copy()->addMinutes(rand(0, 59)),
        ]);
        $trafficCount++;
    }
    
    // Device 3 traffic
    TrafficLog::create([
        'device_id' => $device3->id,
        'source_ip' => '192.168.1.102',
        'destination_ip' => '8.8.' . rand(1, 254) . '.' . rand(1, 254),
        'protocol' => ['TCP', 'UDP'][rand(0, 1)],
        'bytes_sent' => rand(500, 5000),
        'bytes_received' => rand(1000, 10000),
        'packet_count' => rand(5, 50),
        'timestamp' => $time->copy()->addMinutes(rand(0, 59)),
    ]);
    $trafficCount++;
    
    // Create bandwidth snapshots
    BandwidthSnapshot::create([
        'device_id' => $device1->id,
        'upload_kbps' => rand(50, 500),
        'download_kbps' => rand(100, 1000),
        'measured_at' => $time,
    ]);
    
    BandwidthSnapshot::create([
        'device_id' => $device2->id,
        'upload_kbps' => rand(100, 1000),
        'download_kbps' => rand(200, 2000),
        'measured_at' => $time,
    ]);
    
    BandwidthSnapshot::create([
        'device_id' => $device3->id,
        'upload_kbps' => rand(20, 200),
        'download_kbps' => rand(50, 500),
        'measured_at' => $time,
    ]);
    
    $snapshotCount += 3;
}

echo "✓ Test data seeded successfully!\n";
echo "  - Devices: 3\n";
echo "  - Traffic logs: {$trafficCount}\n";
echo "  - Bandwidth snapshots: {$snapshotCount}\n";
PHP
]);

exit($status);
