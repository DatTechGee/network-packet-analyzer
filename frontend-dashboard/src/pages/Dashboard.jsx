import React, { useEffect, useState, useRef } from 'react';
import { useNavigate } from 'react-router-dom';
import { AreaChart, Area, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer } from 'recharts';
import { Zap, AlertTriangle, Wifi, Shield, RefreshCw, Activity } from 'lucide-react';
import { deviceAPI, trafficAPI, threatAPI } from '../services/api';

const DEFAULT_REFRESH_INTERVAL_SECONDS = 30;
const MAX_ACTIVE_DEVICE_PAGE_SIZE = 1000;

function getDeviceLabel(device) {
  return device.display_name || device.device_name || device.metadata?.hostname || `Device ${device.ip_address}`;
}

function isDeviceConnected(device, activeWindowMinutes) {
  const deviceType = (device.device_type || '').toLowerCase();

  if (deviceType === 'router' || deviceType === 'external' || deviceType === 'hotspot_gateway') {
    return false;
  }

  // If explicitly online/active, trust that immediately.
  if (device.is_active === true || device.is_online === true) return true;

  // Otherwise, fall back to the recent seen window so stale backend flags do not hide real clients.
  const lastSeenAt = device.last_seen ? new Date(device.last_seen).getTime() : 0;
  const activeWindowMs = activeWindowMinutes * 60 * 1000;
  return Boolean(lastSeenAt && Date.now() - lastSeenAt <= activeWindowMs);
}


function normalizeDeviceList(payload) {
  const list = Array.isArray(payload?.data)
    ? payload.data
    : Array.isArray(payload)
      ? payload
      : [];

  return [...list].sort((left, right) => {
    const leftSeen = left?.last_seen ? new Date(left.last_seen).getTime() : 0;
    const rightSeen = right?.last_seen ? new Date(right.last_seen).getTime() : 0;
    return rightSeen - leftSeen;
  });
}

export default function Dashboard() {
  const navigate = useNavigate();
  const REALTIME_ACTIVE_WINDOW_MINUTES = 2;
  const [stats, setStats] = useState({
    activeDevices: 0,
    totalTraffic: 0,
    threats: 0,
    avgBandwidth: 0,
    totalPackets: 0,
  });
  const [devices, setDevices] = useState([]);
  const [threats, setThreats] = useState([]);
  const [trafficData, setTrafficData] = useState([]);
  const [loading, setLoading] = useState(true);
  const [isRefreshing, setIsRefreshing] = useState(false);
  const [lastUpdated, setLastUpdated] = useState(null);
  const [hasLoadedOnce, setHasLoadedOnce] = useState(false);
  const [error, setError] = useState(null);
  const [activeWindowMinutes, setActiveWindowMinutes] = useState(15);
  const [refreshInterval, setRefreshInterval] = useState(30);
  const [wsStatus, setWsStatus] = useState('connecting');
  const wsRef = useRef(null);

  useEffect(() => {
    const storedWindow = Number(localStorage.getItem('networkAnalyzer.activeWindowMinutes')) || REALTIME_ACTIVE_WINDOW_MINUTES;
    const storedRefresh = Number(localStorage.getItem('networkAnalyzer.refreshInterval')) || DEFAULT_REFRESH_INTERVAL_SECONDS;

    setActiveWindowMinutes(storedWindow);
    setRefreshInterval(storedRefresh);
  }, []);

  useEffect(() => {
    fetchDashboardData();
    const interval = setInterval(() => fetchDashboardData(), refreshInterval * 1000);

    const handleVisibilityRefresh = () => {
      if (document.visibilityState === 'visible') {
        fetchDashboardData();
      }
    };

    window.addEventListener('focus', fetchDashboardData);
    document.addEventListener('visibilitychange', handleVisibilityRefresh);

    // Setup WebSocket
    const connectWs = () => {
      try {
        const wsUrl = `ws://${window.location.hostname}:6001`;
        const ws = new WebSocket(wsUrl);
        
        ws.onopen = () => setWsStatus('connected');
        
        ws.onmessage = (event) => {
          try {
            const data = JSON.parse(event.data);
            if (data.type === 'packet' || data.event === 'packet_captured') {
              const packet = data.data || data;
              setStats(prev => ({
                ...prev,
                totalPackets: prev.totalPackets + 1,
                totalTraffic: prev.totalTraffic + (packet.size_bytes || 0) / (1024 * 1024)
              }));
            } else if (data.type === 'threat' || data.event === 'threat_detected') {
              const threatData = data.data || data;
              setThreats(prev => {
                const exists = prev.some((t) => t.id === threatData.id);
                if (exists) return prev;
                const updated = [threatData, ...prev].slice(0, 20);
                setStats(prevStats => ({ ...prevStats, threats: updated.length }));
                return updated;
              });
            }
          } catch (e) {}
        };
        
        ws.onclose = () => {
          setWsStatus('disconnected');
          setTimeout(connectWs, 5000);
        };
        
        ws.onerror = () => setWsStatus('disconnected');
        wsRef.current = ws;
      } catch (e) {
        setWsStatus('disconnected');
      }
    };
    
    connectWs();

    return () => {
      clearInterval(interval);
      window.removeEventListener('focus', fetchDashboardData);
      document.removeEventListener('visibilitychange', handleVisibilityRefresh);
      if (wsRef.current) wsRef.current.close();
    };
  }, [activeWindowMinutes, hasLoadedOnce, refreshInterval]);

  const fetchDashboardData = async () => {
    try {
      if (!hasLoadedOnce) {
        setLoading(true);
      }
      setIsRefreshing(true);

      const [devicesRes, trafficRes, timelineRes, threatsRes] = await Promise.allSettled([
        deviceAPI.getAll({ per_page: MAX_ACTIVE_DEVICE_PAGE_SIZE, active_window_minutes: REALTIME_ACTIVE_WINDOW_MINUTES }),
        trafficAPI.getStats({ period: '24h', active_window_minutes: REALTIME_ACTIVE_WINDOW_MINUTES }),
        trafficAPI.getTimeline({ period: '24h' }),
        threatAPI.getAll({ period: '7d', limit: 20 }),
      ]);

      let resolvedConnectedDevices = [];

      if (devicesRes.status === 'fulfilled') {
        const normalizedDevices = normalizeDeviceList(devicesRes.value.data);
        resolvedConnectedDevices = normalizedDevices.filter((device) => isDeviceConnected(device, REALTIME_ACTIVE_WINDOW_MINUTES));
        setDevices(resolvedConnectedDevices);
        setStats((prev) => ({
          ...prev,
          activeDevices: resolvedConnectedDevices.length,
        }));
      } else {
        console.error('Error fetching devices:', devicesRes.reason);
        setDevices([]);
      }

      if (trafficRes.status === 'fulfilled') {
        const trafficStats = trafficRes.value.data.data || {};
        const totalTrafficMb = trafficStats.total_traffic_mb || 0;
        setStats((prev) => ({
          ...prev,
          // Keep activeDevices from the device list — don't override with trafficStats
          totalTraffic: totalTrafficMb,
          avgBandwidth: Number((totalTrafficMb / 24).toFixed(2)),
          totalPackets: Number(trafficStats.total_packets || 0),
        }));
      } else {
        console.error('Error fetching traffic stats:', trafficRes.reason);
      }

      if (timelineRes.status === 'fulfilled') {
        setTrafficData(timelineRes.value.data.data || []);
      } else {
        console.error('Error fetching traffic timeline:', timelineRes.reason);
        setTrafficData([]);
      }

      if (threatsRes.status === 'fulfilled') {
        setThreats(threatsRes.value.data.data || []);
      } else {
        console.error('Error fetching threats:', threatsRes.reason);
        setThreats([]);
      }

      setStats((prev) => ({
        ...prev,
        threats: threatsRes.status === 'fulfilled' ? (threatsRes.value.data.data?.length || 0) : 0,
      }));

      setLastUpdated(new Date());

      const failedRequests = [devicesRes, trafficRes, timelineRes, threatsRes]
        .filter((result) => result.status === 'rejected').length;
      setError(failedRequests === 4 ? 'Failed to load dashboard data' : null);
      if (!hasLoadedOnce) {
        setHasLoadedOnce(true);
      }
    } catch (err) {
      console.error('Error fetching dashboard data:', err);
      setError('Failed to load dashboard data');
    } finally {
      setLoading(false);
      setIsRefreshing(false);
    }
  };

  return (
    <div className="min-h-screen bg-gradient-to-br from-slate-950 via-slate-900 to-slate-800 p-8 page-shell">
      <div className="max-w-7xl mx-auto">
        {/* Header */}
        <div className="mb-8 flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
          <h1 className="text-4xl font-bold text-white mb-2">G2 Project — Network Monitor</h1>
          <div className="text-slate-400 flex flex-col items-start md:items-end gap-1">
            <div className="flex items-center gap-3">
              <p>Real-time network monitoring</p>
              <div className={`flex items-center gap-1.5 px-2 py-0.5 rounded text-xs font-medium ${
                wsStatus === 'connected' ? 'bg-green-500/20 text-green-400 border border-green-500/30' :
                wsStatus === 'connecting' ? 'bg-yellow-500/20 text-yellow-400 border border-yellow-500/30' :
                'bg-red-500/20 text-red-400 border border-red-500/30'
              }`}>
                {wsStatus === 'connected' ? <Wifi className="w-3 h-3" /> : <Wifi className="w-3 h-3 opacity-50" />}
                {wsStatus === 'connected' ? 'Live' : 'Polling'}
              </div>
            </div>
            <p className="text-xs text-slate-500 flex items-center gap-2">
              <RefreshCw className={`w-3.5 h-3.5 ${isRefreshing ? 'animate-spin text-blue-400' : 'text-slate-500'}`} />
              {lastUpdated ? `Last updated ${lastUpdated.toLocaleTimeString()}` : 'Waiting for live data'}
            </p>
          </div>
        </div>

        {/* Error Alert */}
        {error && (
          <div className="bg-red-900 border border-red-700 text-red-100 px-4 py-3 rounded mb-6">
            {error}
          </div>
        )}

        {/* Stats Grid */}
        <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-5 gap-6 mb-8">
          <StatCard
            icon={<Wifi className="w-6 h-6" />}
            label="Active Devices"
            value={stats.activeDevices}
            color="blue"
          />
          <StatCard
            icon={<Zap className="w-6 h-6" />}
            label="Total Traffic (MB)"
            value={stats.totalTraffic.toFixed(2)}
            color="green"
          />
          <StatCard
            icon={<AlertTriangle className="w-6 h-6" />}
            label="Threats Detected"
            value={stats.threats}
            color="red"
          />
          <StatCard
            icon={<Shield className="w-6 h-6" />}
            label="Avg Bandwidth (Mbps)"
            value={stats.avgBandwidth}
            color="purple"
          />
          <StatCard
            icon={<Activity className="w-6 h-6" />}
            label="Packets Passed"
            value={stats.totalPackets}
            color="blue"
          />
        </div>

        {/* Charts Row */}
        <div className="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
          {/* Traffic Chart */}
          <div className="lg:col-span-2 bg-slate-800 rounded-lg p-6 border border-slate-700 surface-card hover:shadow-2xl">
            <h2 className="text-xl font-semibold text-white mb-4">Traffic Trend (24h)</h2>
            <ResponsiveContainer width="100%" height={300}>
              <AreaChart data={trafficData}>
                <defs>
                  <linearGradient id="colorUpload" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="5%" stopColor="#3b82f6" stopOpacity={0.8}/>
                    <stop offset="95%" stopColor="#3b82f6" stopOpacity={0}/>
                  </linearGradient>
                  <linearGradient id="colorDownload" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="5%" stopColor="#10b981" stopOpacity={0.8}/>
                    <stop offset="95%" stopColor="#10b981" stopOpacity={0}/>
                  </linearGradient>
                </defs>
                <CartesianGrid strokeDasharray="3 3" stroke="#475569" />
                <XAxis dataKey="time" stroke="#94a3b8" />
                <YAxis stroke="#94a3b8" />
                <Tooltip contentStyle={{ backgroundColor: '#1e293b', border: '1px solid #475569' }} />
                <Area type="monotone" dataKey="upload" stackId="1" stroke="#3b82f6" fill="url(#colorUpload)" />
                <Area type="monotone" dataKey="download" stackId="1" stroke="#10b981" fill="url(#colorDownload)" />
              </AreaChart>
            </ResponsiveContainer>
            {!loading && trafficData.length === 0 && (
              <p className="text-slate-400 text-sm mt-3">No traffic samples yet. Start router-agent capture to populate this chart.</p>
            )}
          </div>

          {/* Threats Alert */}
          <div className="bg-slate-800 rounded-lg p-6 border border-slate-700 surface-card hover:shadow-2xl">
            <div className="flex items-center justify-between mb-4">
              <h2 className="text-xl font-semibold text-white">Recent Threats</h2>
              <button onClick={() => navigate('/security')} className="text-sm text-blue-400 hover:text-blue-300">View All</button>
            </div>
            <div className="space-y-3 max-h-96 overflow-y-auto">
              {threats.length > 0 ? (
                threats.map((threat) => (
                  <div key={threat.id} className={`bg-slate-700 p-3 rounded border-l-4 ${
                    threat.threat_level === 'critical' ? 'border-red-500' :
                    threat.threat_level === 'high' ? 'border-orange-500' :
                    threat.threat_level === 'medium' ? 'border-yellow-500' :
                    'border-blue-500'
                  } surface-card hover:bg-slate-600/90`}>
                    <div className="flex items-start justify-between">
                      <div className="flex-1">
                        <p className="text-sm font-semibold text-white">
                          {threat.threat_type === 'site_visit' ? 'Website Visit' :
                           threat.threat_type === 'http_insecure' ? 'Insecure HTTP Site' :
                           threat.threat_type.replace(/_/g, ' ').toUpperCase()}
                        </p>
                        <p className="text-xs text-slate-400 mt-1">{threat.description}</p>
                        {threat.metadata?.domain && (
                          <div className="flex flex-wrap gap-2 mt-2">
                            <span className="px-2 py-1 rounded bg-slate-800 text-[11px] text-slate-300">
                              {threat.threat_type === 'http_insecure' ? '🌐' : '🔗'} {threat.metadata.domain}
                            </span>
                            {threat.metadata?.browser && (
                              <span className="px-2 py-1 rounded bg-slate-800 text-[11px] text-slate-300">
                                {threat.metadata.browser}
                              </span>
                            )}
                          </div>
                        )}
                      </div>
                      <span className={`text-[10px] font-semibold px-2 py-0.5 rounded ${
                        threat.threat_level === 'critical' ? 'bg-red-900/50 text-red-300' :
                        threat.threat_level === 'high' ? 'bg-orange-900/50 text-orange-300' :
                        threat.threat_level === 'medium' ? 'bg-yellow-900/50 text-yellow-300' :
                        'bg-blue-900/50 text-blue-300'
                      }`}>
                        {threat.threat_level}
                      </span>
                    </div>
                    <p className="text-[11px] text-slate-500 mt-1">{new Date(threat.detected_at).toLocaleTimeString()}</p>
                  </div>
                ))
              ) : (
                <p className="text-slate-400 text-sm">No threats detected</p>
              )}
            </div>
          </div>
        </div>

        {/* Devices Table */}
        <div className="bg-slate-800 rounded-lg p-6 border border-slate-700 surface-card hover:shadow-2xl">
          <h2 className="text-xl font-semibold text-white mb-4">Connected Devices</h2>
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead>
                <tr className="border-b border-slate-700">
                  <th className="text-left py-3 px-4 text-slate-300 font-semibold">Device Name</th>
                  <th className="text-left py-3 px-4 text-slate-300 font-semibold">IP Address</th>
                  <th className="text-left py-3 px-4 text-slate-300 font-semibold">MAC Address</th>
                  <th className="text-left py-3 px-4 text-slate-300 font-semibold">Status</th>
                  <th className="text-left py-3 px-4 text-slate-300 font-semibold">Last Seen</th>
                </tr>
              </thead>
              <tbody>
                {devices.length > 0 ? (
                  devices.map((device) => {
                    const isConnected = isDeviceConnected(device, REALTIME_ACTIVE_WINDOW_MINUTES);
                    const deviceLabel = getDeviceLabel(device);

                    return (
                      <tr
                        key={device.id}
                        onClick={() => navigate(`/device/${device.id}`)}
                        className="border-b border-slate-700 hover:bg-slate-700/50 transition cursor-pointer"
                      >
                        <td className="py-3 px-4 text-slate-100">
                          {deviceLabel}
                        </td>
                        <td className="py-3 px-4 text-slate-400">
                          {device.ip_address || device.metadata?.ip_address || device.metadata?.last_ip || device.metadata?.hostname || 'Unknown'}
                        </td>
                        <td className="py-3 px-4 text-slate-400 font-mono text-xs">{device.mac_address}</td>
                        <td className="py-3 px-4">
                          <span className={`px-2 py-1 rounded text-xs font-semibold ${isConnected ? 'bg-green-900 text-green-200' : 'bg-slate-700 text-slate-400'}`}>
                            {isConnected ? 'Connected' : 'Offline'}
                          </span>
                        </td>
                        <td className="py-3 px-4 text-slate-400 text-xs">
                          {device.last_seen ? new Date(device.last_seen).toLocaleTimeString() : 'Unknown'}
                        </td>
                      </tr>
                    );
                  })
                ) : (
                  <tr>
                    <td colSpan="5" className="py-8 px-4 text-center text-slate-400">
                      No connected devices detected yet. If router-agent is running, increase the active window in Settings or verify hotspot packets are reaching the backend.
                    </td>
                  </tr>
                )}
              </tbody>
            </table>
          </div>
          <div className="flex items-center justify-between mt-4 text-sm text-slate-400">
            <p>Showing {devices.length} live devices in the active window</p>
            <p>{stats.activeDevices} connected right now</p>
          </div>
        </div>
      </div>
    </div>
  );
}

function StatCard({ icon, label, value, color }) {
  const colorClasses = {
    blue: 'from-blue-600 to-blue-700 border-blue-500',
    green: 'from-green-600 to-green-700 border-green-500',
    red: 'from-red-600 to-red-700 border-red-500',
    purple: 'from-purple-600 to-purple-700 border-purple-500',
  };

  return (
    <div className={`bg-gradient-to-br ${colorClasses[color]} rounded-lg p-6 border ${colorClasses[color].split(' ').pop()} shadow-lg surface-card`}>
      <div className="flex items-center justify-between">
        <div>
          <p className="text-blue-100 text-sm font-medium">{label}</p>
          <p className="text-white text-3xl font-bold mt-2">{value}</p>
        </div>
        <div className="text-blue-100 opacity-50">{icon}</div>
      </div>
    </div>
  );
}

function getThreatColor(level) {
  const colors = {
    critical: 'text-red-400',
    high: 'text-orange-400',
    medium: 'text-yellow-400',
    low: 'text-blue-400',
  };
  return colors[level] || 'text-slate-400';
}
