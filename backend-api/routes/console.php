<?php

use Illuminate\Support\Facades\Artisan;

Artisan::command('devices:list', function () {
    $devices = \App\Models\Device::orderByDesc('last_seen')->get();
    $this->info('PHP now: ' . now()->toIso8601String());
    $this->info('PHP timezone: ' . date_default_timezone_get());
    $this->table(
        ['ID', 'IP', 'MAC', 'Type', 'Name', 'Online', 'Last Seen'],
        $devices->map(fn($d) => [
            $d->id,
            $d->ip_address ?? '',
            $d->mac_address ?? '',
            $d->device_type ?? '',
            $d->device_name ?? '',
            $d->is_online ? 'Y' : 'N',
            (string)$d->last_seen,
        ])->toArray()
    );
})->describe('List all devices in the database');
