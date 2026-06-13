<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrafficLog extends Model
{
    protected $fillable = [
        'device_id',
        'source_ip',
        'destination_ip',
        'dest_ip',
        'source_port',
        'destination_port',
        'dest_port',
        'protocol',
        'bytes_sent',
        'bytes_received',
        'packet_count',
        'content_type',
        'domain',
        'recorded_at',
        'timestamp',
        'user_agent',
        'http_method',
        'url',
        'metadata',
    ];

    protected $casts = [
        'recorded_at' => 'datetime',
        'timestamp' => 'datetime',
        'bytes_sent' => 'integer',
        'bytes_received' => 'integer',
        'packet_count' => 'integer',
        'metadata' => 'array',
    ];

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }
}
