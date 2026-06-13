<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SpeedTestResult extends Model
{
    protected $fillable = [
        'download_mbps',
        'upload_mbps',
        'ping_ms',
        'jitter_ms',
        'server',
        'isp',
        'metadata',
    ];

    protected $casts = [
        'download_mbps' => 'float',
        'upload_mbps'   => 'float',
        'ping_ms'       => 'float',
        'jitter_ms'     => 'float',
        'metadata'      => 'array',
    ];
}
