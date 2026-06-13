<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Threat extends Model
{
    protected $fillable = [
        'device_id',
        'threat_type',
        'threat_level',
        'description',
        'source_ip',
        'destination_ip',
        'source_port',
        'destination_port',
        'signature',
        'is_resolved',
        'blocked',
        'detected_at',
        'metadata',
    ];

    protected $casts = [
        'is_resolved' => 'boolean',
        'blocked' => 'boolean',
        'detected_at' => 'datetime',
        'metadata' => 'array',
    ];

    const THREAT_LEVELS = ['low', 'medium', 'high', 'critical'];
    const THREAT_TYPES = [
        'port_scan',
        'ddos_attempt',
        'malware_signature',
        'external_site_visit',
        'site_visit',
        'http_insecure',
        'site_blocked',
        'suspicious_dns',
        'brute_force',
        'data_exfiltration',
        'vpn_detection',
        'anomalous_traffic',
        'arp_spoofing',
        'mac_change',
        'excessive_connections'
    ];

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }
}
