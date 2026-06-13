<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Ensure critical indexes exist for fast queries
        if (!Schema::hasTable('devices')) {
            return;
        }

        Schema::table('devices', function (Blueprint $table) {
            // Composite index for device list queries
            $table->index(['mac_address', 'is_online', 'last_seen'], 'idx_devices_active');
            // Single index for last_seen range queries
            $table->index('last_seen', 'idx_devices_last_seen');
        });

        if (!Schema::hasTable('traffic_logs')) {
            return;
        }

        Schema::table('traffic_logs', function (Blueprint $table) {
            // Index for timeline aggregation queries
            $table->index('timestamp', 'idx_traffic_timestamp');
            // Index for per-device traffic queries
            $table->index(['device_id', 'timestamp'], 'idx_traffic_device_timestamp');
        });

        if (!Schema::hasTable('threats')) {
            return;
        }

        Schema::table('threats', function (Blueprint $table) {
            // Index for threat queries
            $table->index('timestamp', 'idx_threats_timestamp');
            $table->index(['device_id', 'timestamp'], 'idx_threats_device_timestamp');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('devices')) {
            Schema::table('devices', function (Blueprint $table) {
                $table->dropIndex('idx_devices_active');
                $table->dropIndex('idx_devices_last_seen');
            });
        }

        if (Schema::hasTable('traffic_logs')) {
            Schema::table('traffic_logs', function (Blueprint $table) {
                $table->dropIndex('idx_traffic_timestamp');
                $table->dropIndex('idx_traffic_device_timestamp');
            });
        }

        if (Schema::hasTable('threats')) {
            Schema::table('threats', function (Blueprint $table) {
                $table->dropIndex('idx_threats_timestamp');
                $table->dropIndex('idx_threats_device_timestamp');
            });
        }
    }
};
