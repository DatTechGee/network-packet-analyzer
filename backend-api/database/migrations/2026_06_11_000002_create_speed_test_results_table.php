<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('speed_test_results', function (Blueprint $table) {
            $table->id();
            $table->float('download_mbps')->default(0);
            $table->float('upload_mbps')->default(0);
            $table->float('ping_ms')->default(0);
            $table->float('jitter_ms')->default(0);
            $table->string('server')->nullable();
            $table->string('isp')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('speed_test_results');
    }
};
