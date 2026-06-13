<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('traffic_logs', function (Blueprint $table) {
            if (!Schema::hasColumn('traffic_logs', 'destination_ip')) {
                $table->string('destination_ip')->nullable()->after('source_ip');
            }

            if (!Schema::hasColumn('traffic_logs', 'destination_port')) {
                $table->integer('destination_port')->nullable()->after('source_port');
            }

            if (!Schema::hasColumn('traffic_logs', 'timestamp')) {
                $table->timestamp('timestamp')->nullable()->after('recorded_at');
            }
        });

        Schema::table('threats', function (Blueprint $table) {
            if (!Schema::hasColumn('threats', 'destination_ip')) {
                $table->string('destination_ip')->nullable()->after('source_ip');
            }

            if (!Schema::hasColumn('threats', 'destination_port')) {
                $table->integer('destination_port')->nullable()->after('source_port');
            }

            if (!Schema::hasColumn('threats', 'threat_level')) {
                $table->string('threat_level')->nullable()->after('threat_type');
            }

            if (!Schema::hasColumn('threats', 'blocked')) {
                $table->boolean('blocked')->default(false)->after('is_blocked');
            }
        });
    }

    public function down(): void
    {
        Schema::table('traffic_logs', function (Blueprint $table) {
            if (Schema::hasColumn('traffic_logs', 'timestamp')) {
                $table->dropColumn('timestamp');
            }

            if (Schema::hasColumn('traffic_logs', 'destination_port')) {
                $table->dropColumn('destination_port');
            }

            if (Schema::hasColumn('traffic_logs', 'destination_ip')) {
                $table->dropColumn('destination_ip');
            }
        });

        Schema::table('threats', function (Blueprint $table) {
            if (Schema::hasColumn('threats', 'blocked')) {
                $table->dropColumn('blocked');
            }

            if (Schema::hasColumn('threats', 'threat_level')) {
                $table->dropColumn('threat_level');
            }

            if (Schema::hasColumn('threats', 'destination_port')) {
                $table->dropColumn('destination_port');
            }

            if (Schema::hasColumn('threats', 'destination_ip')) {
                $table->dropColumn('destination_ip');
            }
        });
    }
};