<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bandwidth_snapshots', function (Blueprint $table) {
            if (!Schema::hasColumn('bandwidth_snapshots', 'total_bytes_upload')) {
                $table->bigInteger('total_bytes_upload')->default(0)->after('total_bytes_sent');
            }

            if (!Schema::hasColumn('bandwidth_snapshots', 'total_bytes_download')) {
                $table->bigInteger('total_bytes_download')->default(0)->after('total_bytes_received');
            }

            if (!Schema::hasColumn('bandwidth_snapshots', 'recorded_at')) {
                $table->timestamp('recorded_at')->nullable()->after('latency_ms');
            }
        });
    }

    public function down(): void
    {
        Schema::table('bandwidth_snapshots', function (Blueprint $table) {
            if (Schema::hasColumn('bandwidth_snapshots', 'recorded_at')) {
                $table->dropColumn('recorded_at');
            }

            if (Schema::hasColumn('bandwidth_snapshots', 'total_bytes_download')) {
                $table->dropColumn('total_bytes_download');
            }

            if (Schema::hasColumn('bandwidth_snapshots', 'total_bytes_upload')) {
                $table->dropColumn('total_bytes_upload');
            }
        });
    }
};