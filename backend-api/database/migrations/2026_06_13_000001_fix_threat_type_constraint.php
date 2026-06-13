<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // SQLite stores enum as TEXT with CHECK constraint.
        // We need to recreate the table with the expanded threat_type list.
        // Step 1: Create new table with correct CHECK constraint
        DB::statement('CREATE TABLE threats_new (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            device_id INTEGER NOT NULL,
            threat_type VARCHAR NOT NULL CHECK(threat_type IN (
                "port_scan", "ddos_attempt", "malware_signature", "suspicious_dns",
                "brute_force", "data_exfiltration", "vpn_detection", "anomalous_traffic",
                "arp_spoofing", "mac_change", "excessive_connections",
                "site_visit", "http_insecure", "external_site_visit"
            )),
            threat_level VARCHAR NOT NULL DEFAULT "medium" CHECK(threat_level IN ("critical", "high", "medium", "low")),
            description TEXT,
            source_ip VARCHAR,
            destination_ip VARCHAR,
            source_port INTEGER,
            destination_port INTEGER,
            signature VARCHAR,
            blocked BOOLEAN DEFAULT 0,
            is_resolved BOOLEAN DEFAULT 0,
            resolution_notes TEXT,
            metadata TEXT,
            detected_at TIMESTAMP,
            resolved_at TIMESTAMP,
            created_at TIMESTAMP,
            updated_at TIMESTAMP,
            FOREIGN KEY (device_id) REFERENCES devices(id) ON DELETE CASCADE
        )');

        // Step 2: Copy data
        DB::statement('INSERT INTO threats_new (id, device_id, threat_type, threat_level, description, source_ip, destination_ip, source_port, destination_port, signature, blocked, is_resolved, resolution_notes, metadata, detected_at, resolved_at, created_at, updated_at) SELECT id, device_id, threat_type, threat_level, description, source_ip, destination_ip, source_port, destination_port, signature, is_blocked, is_resolved, resolution_notes, metadata, detected_at, resolved_at, created_at, updated_at FROM threats');

        // Step 3: Drop old table and rename
        DB::statement('DROP TABLE threats');
        DB::statement('ALTER TABLE threats_new RENAME TO threats');

        // Step 4: Recreate indexes
        DB::statement('CREATE INDEX idx_threats_timestamp ON threats(detected_at)');
        DB::statement('CREATE INDEX idx_threats_device_timestamp ON threats(device_id, detected_at)');
        DB::statement('CREATE INDEX idx_threats_type ON threats(threat_type)');
        DB::statement('CREATE INDEX idx_threats_level ON threats(threat_level)');
    }

    public function down(): void
    {
        // Revert to original constraint (without new types)
        DB::statement('CREATE TABLE threats_orig (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            device_id INTEGER NOT NULL,
            threat_type VARCHAR NOT NULL CHECK(threat_type IN (
                "port_scan", "ddos_attempt", "malware_signature", "suspicious_dns",
                "brute_force", "data_exfiltration", "vpn_detection", "anomalous_traffic"
            )),
            threat_level VARCHAR NOT NULL DEFAULT "medium" CHECK(threat_level IN ("critical", "high", "medium", "low")),
            description TEXT,
            source_ip VARCHAR,
            destination_ip VARCHAR,
            source_port INTEGER,
            destination_port INTEGER,
            signature VARCHAR,
            is_blocked BOOLEAN DEFAULT 0,
            is_resolved BOOLEAN DEFAULT 0,
            resolution_notes TEXT,
            metadata TEXT,
            detected_at TIMESTAMP,
            resolved_at TIMESTAMP,
            created_at TIMESTAMP,
            updated_at TIMESTAMP,
            FOREIGN KEY (device_id) REFERENCES devices(id) ON DELETE CASCADE
        )');

        DB::statement('INSERT INTO threats_orig (id, device_id, threat_type, threat_level, description, source_ip, destination_ip, source_port, destination_port, signature, is_blocked, is_resolved, resolution_notes, metadata, detected_at, resolved_at, created_at, updated_at) SELECT id, device_id, threat_type, threat_level, description, source_ip, destination_ip, source_port, destination_port, signature, is_blocked, is_resolved, resolution_notes, metadata, detected_at, resolved_at, created_at, updated_at FROM threats WHERE threat_type IN ("port_scan","ddos_attempt","malware_signature","suspicious_dns","brute_force","data_exfiltration","vpn_detection","anomalous_traffic","arp_spoofing","mac_change","excessive_connections","site_visit","http_insecure","external_site_visit")');

        DB::statement('DROP TABLE threats');
        DB::statement('ALTER TABLE threats_orig RENAME TO threats');
    }
};
