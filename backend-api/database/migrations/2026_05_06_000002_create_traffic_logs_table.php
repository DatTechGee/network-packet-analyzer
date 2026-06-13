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
        Schema::create('traffic_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained('devices')->onDelete('cascade');
            $table->string('source_ip')->nullable();
            $table->string('dest_ip')->nullable();
            $table->integer('source_port')->nullable();
            $table->integer('dest_port')->nullable();
            $table->string('protocol')->comment('TCP, UDP, ICMP, DNS, HTTP, HTTPS, etc');
            $table->bigInteger('bytes_sent')->default(0);
            $table->bigInteger('bytes_received')->default(0);
            $table->integer('packet_count')->default(0);
            $table->string('content_type')->nullable()->comment('video, audio, image, document, social_media, streaming, etc');
            $table->string('domain')->nullable();
            $table->string('user_agent')->nullable();
            $table->text('http_method')->nullable();
            $table->text('url')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('recorded_at')->nullable();
            $table->timestamps();
            
            $table->index('device_id');
            $table->index('source_ip');
            $table->index('dest_ip');
            $table->index('protocol');
            $table->index('content_type');
            $table->index('domain');
            $table->index('recorded_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('traffic_logs');
    }
};
