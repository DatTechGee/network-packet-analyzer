<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Uses createIfNotExists to be safe when the table already exists.
     */
    public function up(): void
    {
        if (!Schema::hasTable('alerts')) {
            Schema::create('alerts', function (Blueprint $table) {
                $table->id();
                $table->string('type'); // device_joined, device_disconnected, port_scan, high_bandwidth, arp_spoofing
                $table->string('title');
                $table->text('message');
                $table->boolean('is_read')->default(false);
                $table->unsignedBigInteger('device_id')->nullable();
                $table->timestamps();

                $table->foreign('device_id')->references('id')->on('devices')->onDelete('cascade');
                $table->index('is_read');
                $table->index('created_at');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('alerts');
    }
};
