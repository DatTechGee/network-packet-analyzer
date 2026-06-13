<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Device extends Model
{
    protected $fillable = [
        'mac_address',
        'ip_address',
        'device_name',
        'device_type',
        'vendor',
        'first_seen',
        'last_seen',
        'is_online',
        'user_id',
        'metadata',
    ];

    protected $casts = [
        'is_online' => 'boolean',
        'first_seen' => 'datetime',
        'last_seen' => 'datetime',
        'metadata' => 'array',
    ];

    public function trafficLogs(): HasMany
    {
        return $this->hasMany(TrafficLog::class);
    }

    public function threats(): HasMany
    {
        return $this->hasMany(Threat::class);
    }

    public function bandwidthSnapshots(): HasMany
    {
        return $this->hasMany(BandwidthSnapshot::class);
    }
}
