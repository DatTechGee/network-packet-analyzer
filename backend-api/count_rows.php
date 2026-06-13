<?php
require __DIR__.'/bootstrap/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\TrafficLog;
use App\Models\Threat;
use App\Models\Device;

echo "Device count: " . Device::count() . "\n";
echo "Traffic Log count: " . TrafficLog::count() . "\n";
echo "Threat count: " . Threat::count() . "\n";
