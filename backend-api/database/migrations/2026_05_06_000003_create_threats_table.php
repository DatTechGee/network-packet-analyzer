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
        Schema::create('threats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained('devices')->onDelete('cascade');
            $table->enum('threat_type', [
                'port_scan',
                'ddos_attempt',
                'malware_signature',
                'suspicious_dns',
                'brute_force',
                'data_exfiltration',
                'vpn_detection',
                'anomalous_traffic'
            ]);
            $table->enum('threat_level', ['critical', 'high', 'medium', 'low'])->default('medium');
            $table->text('description')->nullable();
            $table->string('source_ip')->nullable();
            $table->string('destination_ip')->nullable();
            $table->integer('source_port')->nullable();
            $table->integer('destination_port')->nullable();
            $table->string('signature')->nullable();
            $table->boolean('is_blocked')->default(false);
            $table->boolean('is_resolved')->default(false);
            $table->text('resolution_notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('detected_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            
            $table->index('device_id');
            $table->index('threat_type');
            $table->index('threat_level');
            $table->index('is_blocked');
            $table->index('is_resolved');
            $table->index('detected_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('threats');
    }
};
