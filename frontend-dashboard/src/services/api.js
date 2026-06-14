import axios from 'axios';

const API_BASE_URL = import.meta.env.VITE_API_URL || '/api';

const apiClient = axios.create({
  baseURL: API_BASE_URL,
  timeout: 30000,   // 30s for regular requests
  headers: {
    'Content-Type': 'application/json',
  },
});

// Separate client with long timeout for speed tests (download/upload take 30-60s)
const longApiClient = axios.create({
  baseURL: API_BASE_URL,
  timeout: 120000,  // 120s for speed tests
  headers: {
    'Content-Type': 'application/json',
  },
});

// Add authorization header to both clients
[apiClient, longApiClient].forEach(client => {
  client.interceptors.request.use((config) => {
    const token = localStorage.getItem('authToken');
    if (token) {
      config.headers.Authorization = `Bearer ${token}`;
    }
    return config;
  });
});

// Device API
export const deviceAPI = {
  getAll: (params = {}) => apiClient.get('/devices', { params }),
  getById: (id) => apiClient.get(`/devices/${id}`),
  register: (data) => apiClient.post('/devices/register', data),
  update: (id, data) => apiClient.patch(`/devices/${id}`, data),
  blockDomain: (id, domain) => apiClient.post(`/devices/${id}/block-domain`, { domain }),
  unblockDomain: (id, domain) => apiClient.post(`/devices/${id}/unblock-domain`, { domain }),
  blockDevice: (id) => apiClient.post(`/devices/${id}/block`),
  unblockDevice: (id) => apiClient.post(`/devices/${id}/unblock`),
  getBlockedDevices: () => apiClient.get('/devices/blocked/list'),
  setBandwidthLimit: (id, limitMbps, period) => apiClient.post(`/devices/${id}/bandwidth-limit`, { 
    limit_mbps: limitMbps,
    period: period || 'daily'
  }),
  getConnections: (id, params) => apiClient.get(`/devices/${id}/connections`, { params }),
};

// Traffic API
export const trafficAPI = {
  recordTraffic: (data) => apiClient.post('/traffic/record', data),
  getStats: (params) => apiClient.get('/traffic/stats', { params }),
  getTimeline: (params) => apiClient.get('/traffic/timeline', { params }),
  getLivePackets: (params) => apiClient.get('/traffic/live-packets', { params }),
  getDeviceTraffic: (deviceId, params) =>
    apiClient.get(`/traffic/device/${deviceId}`, { params }),
  getContentDistribution: (deviceId, params) =>
    apiClient.get(`/traffic/device/${deviceId}/content`, { params }),
  getTopDomains: (deviceId, params) =>
    apiClient.get(`/traffic/device/${deviceId}/top-domains`, { params }),
};

export const topologyAPI = {
  getTopology: (params) => apiClient.get('/topology', { params }),
};

export const reportAPI = {
  getSummary: (params) => apiClient.get('/reports/summary', { params }),
};

// Threat API
export const threatAPI = {
  getAll: (params) => apiClient.get('/threats', { params }),
  getStats: (params) => apiClient.get('/threats/stats', { params }),
  analyzeTraffic: (data) => apiClient.post('/threats/analyze', data),
  getDeviceThreats: (deviceId, params) =>
    apiClient.get(`/threats/device/${deviceId}`, { params }),
  blockThreat: (id) => apiClient.patch(`/threats/${id}/block`),
  resolveThreat: (id, data) => apiClient.patch(`/threats/${id}/resolve`, data),
};

// Alert API
export const alertAPI = {
  getAlerts: (params) => apiClient.get('/alerts', { params }),
  markAsRead: (id) => apiClient.patch(`/alerts/${id}/read`),
  markAllAsRead: () => apiClient.patch('/alerts/read-all'),  // PATCH matches route definition
  deleteAlert: (id) => apiClient.delete('/alerts/clear'),
};

// Speed Test API — uses long timeout client because download/upload take 30-60s
export const speedTestAPI = {
  runTest: () => longApiClient.post('/speedtest/run'),
  getHistory: (params) => apiClient.get('/speedtest/history', { params }),
};

export default apiClient;
