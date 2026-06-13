<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BandwidthSnapshot extends Model
{
    protected $fillable = [
        'device_id',
        'upload_speed_kbps',
        'download_speed_kbps',
        'total_bytes_sent',
        'total_bytes_received',
        'total_bytes_upload',
        'total_bytes_download',
        'packet_loss_percent',
        'latency_ms',
        'recorded_at',
        'measured_at',
    ];

    protected $casts = [
        'recorded_at' => 'datetime',
        'measured_at' => 'datetime',
        'upload_speed_kbps' => 'decimal:2',
        'download_speed_kbps' => 'decimal:2',
    ];

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }
}
