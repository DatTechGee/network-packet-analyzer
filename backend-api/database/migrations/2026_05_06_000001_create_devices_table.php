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
        Schema::create('devices', function (Blueprint $table) {
            $table->id();
            $table->string('mac_address')->unique();
            $table->string('ip_address')->nullable();
            $table->string('device_name')->nullable();
            $table->string('device_type')->nullable()->comment('phone, laptop, tablet, iot, router, etc');
            $table->string('vendor')->nullable();
            $table->boolean('is_online')->default(true);
            $table->timestamp('first_seen')->nullable();
            $table->timestamp('last_seen')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            
            $table->index('mac_address');
            $table->index('ip_address');
            $table->index('is_online');
            $table->index('last_seen');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('devices');
    }
};
