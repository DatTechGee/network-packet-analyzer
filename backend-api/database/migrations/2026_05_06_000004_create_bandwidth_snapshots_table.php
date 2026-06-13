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
        Schema::create('bandwidth_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained('devices')->onDelete('cascade');
            $table->decimal('upload_speed_kbps', 12, 2)->default(0)->comment('Kilobits per second');
            $table->decimal('download_speed_kbps', 12, 2)->default(0)->comment('Kilobits per second');
            $table->bigInteger('total_bytes_sent')->default(0);
            $table->bigInteger('total_bytes_received')->default(0);
            $table->decimal('packet_loss_percent', 5, 2)->default(0);
            $table->integer('latency_ms')->default(0)->comment('milliseconds');
            $table->integer('jitter_ms')->default(0)->comment('milliseconds');
            $table->timestamp('measured_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            
            $table->index('device_id');
            $table->index('measured_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bandwidth_snapshots');
    }
};
