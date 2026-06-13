# API Documentation

## Base URL
```
http://localhost:8000/api
```

## Authentication
All requests should include:
```
Authorization: Bearer YOUR_TOKEN
Content-Type: application/json
```

---

## Device Management

### List All Devices
```http
GET /devices
```

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "mac_address": "00:1A:2B:3C:4D:5E",
      "ip_address": "192.168.1.100",
      "device_name": "John's Laptop",
      "device_type": "laptop",
      "vendor": "Apple",
      "is_active": true,
      "first_seen": "2024-05-06T10:30:00Z",
      "last_seen": "2024-05-06T15:45:00Z"
    }
  ],
  "count": 1
}
```

### Register Device
```http
POST /devices/register
```

**Request Body:**
```json
{
  "mac_address": "00:1A:2B:3C:4D:5E",
  "ip_address": "192.168.1.100",
  "device_name": "My Device",
  "vendor": "Apple"
}
```

**Response:**
```json
{
  "success": true,
  "message": "Device registered successfully",
  "data": {
    "id": 1,
    "mac_address": "00:1A:2B:3C:4D:5E",
    "ip_address": "192.168.1.100",
    "device_name": "My Device",
    "is_active": true
  }
}
```

### Get Device Details
```http
GET /devices/{deviceId}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "device": {
      "id": 1,
      "mac_address": "00:1A:2B:3C:4D:5E",
      "ip_address": "192.168.1.100",
      "device_name": "John's Laptop",
      "device_type": "laptop",
      "vendor": "Apple",
      "is_active": true,
      "first_seen": "2024-05-06T10:30:00Z",
      "last_seen": "2024-05-06T15:45:00Z"
    },
    "bandwidth": {
      "upload_kbps": 45.23,
      "download_kbps": 120.56,
      "total_upload_bytes": 45000000,
      "total_download_bytes": 120000000,
      "packet_count": 15000
    },
    "top_domains": [
      {
        "domain": "youtube.com",
        "total_mb": 256.5
      },
      {
        "domain": "google.com",
        "total_mb": 145.3
      }
    ],
    "average_bandwidth_24h": {
      "avg_upload_kbps": 42.15,
      "avg_download_kbps": 115.80,
      "peak_upload_kbps": 89.50,
      "peak_download_kbps": 250.60,
      "avg_latency_ms": 23.5
    }
  }
}
```

### Update Device
```http
PATCH /devices/{deviceId}
```

**Request Body:**
```json
{
  "device_name": "Updated Name",
  "device_type": "desktop"
}
```

---

## Traffic Monitoring

### Record Traffic
```http
POST /traffic/record
```

**Request Body:**
```json
{
  "device_id": 1,
  "source_ip": "192.168.1.100",
  "destination_ip": "8.8.8.8",
  "source_port": 54321,
  "destination_port": 443,
  "protocol": "TCP",
  "bytes_sent": 1024,
  "bytes_received": 2048,
  "packet_count": 10,
  "content_type": "Video Streaming",
  "domain": "youtube.com"
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "device_id": 1,
    "source_ip": "192.168.1.100",
    "destination_ip": "8.8.8.8",
    "protocol": "TCP",
    "bytes_sent": 1024,
    "bytes_received": 2048,
    "timestamp": "2024-05-06T15:45:00Z"
  }
}
```

### Get Traffic Statistics
```http
GET /traffic/stats?period=24h
```

**Query Parameters:**
- `period` (optional): `1h`, `24h`, `7d`, `30d` (default: `24h`)

**Response:**
```json
{
  "success": true,
  "data": {
    "total_traffic_mb": 512.5,
    "total_packets": 50000,
    "active_devices": 5
  }
}
```

### Get Device Traffic
```http
GET /traffic/device/{deviceId}?period=24h&limit=100
```

**Query Parameters:**
- `period`: `1h`, `24h`, `7d`, `30d`
- `limit`: Number of records (default: 100)

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "device_id": 1,
      "source_ip": "192.168.1.100",
      "destination_ip": "8.8.8.8",
      "protocol": "TCP",
      "bytes_sent": 1024,
      "bytes_received": 2048,
      "content_type": "Video Streaming",
      "domain": "youtube.com",
      "timestamp": "2024-05-06T15:45:00Z"
    }
  ],
  "count": 1
}
```

### Get Content Distribution
```http
GET /traffic/device/{deviceId}/content?period=24h
```

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "content_type": "Video Streaming",
      "total_mb": 256.5,
      "percent": 45.2,
      "packet_count": 15000
    },
    {
      "content_type": "Social Media",
      "total_mb": 145.3,
      "percent": 25.6,
      "packet_count": 8900
    }
  ]
}
```

---

## Threat Detection

### List Threats
```http
GET /threats?level=high&limit=50
```

**Query Parameters:**
- `level` (optional): `critical`, `high`, `medium`, `low`
- `limit` (optional): Number of records (default: 50)

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "device_id": 1,
      "threat_type": "port_scan",
      "threat_level": "high",
      "description": "Port scanning from 192.168.1.100",
      "source_ip": "192.168.1.100",
      "destination_ip": "8.8.8.8",
      "source_port": 54321,
      "destination_port": 22,
      "protocol": "TCP",
      "blocked": false,
      "is_resolved": false,
      "detected_at": "2024-05-06T15:45:00Z"
    }
  ],
  "count": 1
}
```

### Get Threat Statistics
```http
GET /threats/stats?period=24h
```

**Query Parameters:**
- `period` (optional): `24h`, `7d`, `30d` (default: `24h`)

**Response:**
```json
{
  "success": true,
  "data": {
    "total_threats": 15,
    "critical": 2,
    "high": 5,
    "medium": 5,
    "low": 3,
    "blocked": 8
  }
}
```

### Analyze Traffic
```http
POST /threats/analyze
```

**Request Body:**
```json
{
  "device_id": 1,
  "source_ip": "192.168.1.100",
  "destination_ip": "8.8.8.8",
  "source_port": 54321,
  "destination_port": 22,
  "protocol": "TCP",
  "bytes_sent": 100,
  "bytes_received": 100,
  "packet_count": 1500,
  "domain": "suspicious-domain.tk"
}
```

**Response:**
```json
{
  "success": true,
  "threats_detected": 2,
  "data": [
    {
      "type": "port_scan",
      "level": "high",
      "description": "Port scanning from 192.168.1.100"
    },
    {
      "type": "suspicious_dns",
      "level": "medium",
      "description": "Suspicious DNS query to suspicious-domain.tk"
    }
  ]
}
```

### Get Device Threats
```http
GET /threats/device/{deviceId}?period=24h&limit=50
```

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "device_id": 1,
      "threat_type": "ddos_attempt",
      "threat_level": "critical",
      "description": "Potential DDoS attack from 192.168.1.100",
      "blocked": true,
      "is_resolved": true,
      "detected_at": "2024-05-06T15:45:00Z"
    }
  ],
  "count": 1
}
```

### Block Threat
```http
PATCH /threats/{threatId}/block
```

**Response:**
```json
{
  "success": true,
  "message": "Threat blocked successfully",
  "data": {
    "id": 1,
    "blocked": true,
    "is_resolved": true
  }
}
```

### Resolve Threat
```http
PATCH /threats/{threatId}/resolve
```

**Request Body:**
```json
{
  "block": true
}
```

**Response:**
```json
{
  "success": true,
  "message": "Threat resolved",
  "data": {
    "id": 1,
    "is_resolved": true,
    "blocked": true
  }
}
```

---

## Health Check

### API Status
```http
GET /health
```

**Response:**
```json
{
  "status": "ok"
}
```

---

## Error Responses

All errors follow this format:

```json
{
  "success": false,
  "message": "Error description",
  "errors": {
    "field_name": ["Error message"]
  }
}
```

### Common HTTP Status Codes
- `200` - OK
- `201` - Created
- `400` - Bad Request
- `401` - Unauthorized
- `403` - Forbidden
- `404` - Not Found
- `422` - Unprocessable Entity
- `500` - Internal Server Error

---

## Rate Limiting

- Limit: 100 requests per minute per IP
- Headers returned:
  - `X-RateLimit-Limit`: 100
  - `X-RateLimit-Remaining`: 87
  - `X-RateLimit-Reset`: 1620000000

---

For more information, see [README.md](../README.md)
