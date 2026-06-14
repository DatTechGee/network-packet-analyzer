import React, { useEffect, useState, useRef } from 'react';
import { deviceAPI, threatAPI } from '../services/api';
import { AlertTriangle, AlertCircle, CheckCircle, Shield, Wifi, WifiOff, Ban, Unlock } from 'lucide-react';

export default function SecurityCenter() {
  const [threats, setThreats] = useState([]);
  const [filters, setFilters] = useState({ level: 'all', period: '7d', type: 'all' });
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [wsStatus, setWsStatus] = useState('connecting');
  const wsRef = useRef(null);
  const [blockedDomains, setBlockedDomains] = useState([]);

  useEffect(() => {
    fetchThreats();
    fetchBlockedDomains();
    const blockInterval = setInterval(fetchBlockedDomains, 15000);

    const connectWs = () => {
      try {
        const wsUrl = `ws://${window.location.hostname}:6001`;
        const ws = new WebSocket(wsUrl);

        ws.onopen = () => setWsStatus('connected');

        ws.onmessage = (event) => {
          try {
            const msg = JSON.parse(event.data);
            if (msg.type === 'threat' || msg.event === 'threat_detected') {
              const threatData = msg.data || msg;
              setThreats((prev) => {
                const exists = prev.some((t) => t.id === threatData.id);
                if (exists) return prev;
                return [threatData, ...prev].slice(0, 200);
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
      clearInterval(blockInterval);
      if (wsRef.current) wsRef.current.close();
    };
  }, []);

  useEffect(() => {
    fetchThreats();
  }, [filters]);

  const fetchThreats = async () => {
    try {
      setLoading(true);
      const params = {
        period: filters.period,
        limit: 200,
      };
      if (filters.level !== 'all') params.level = filters.level;

      const response = await threatAPI.getAll(params);
      let data = response.data.data || [];
      if (filters.type !== 'all') {
        data = data.filter((t) => t.threat_type === filters.type);
      }
      setThreats(data);
      setError(null);
    } catch (err) {
      console.error('Error fetching threats:', err);
      setError('Failed to load threats');
    } finally {
      setLoading(false);
    }
  };

  const handleBlockThreat = async (threatId) => {
    try {
      await threatAPI.blockThreat(threatId);
      fetchThreats();
    } catch (err) {
      console.error('Error blocking threat:', err);
    }
  };

  const handleResolveThreat = async (threatId, block = false) => {
    try {
      await threatAPI.resolveThreat(threatId, { block });
      fetchThreats();
    } catch (err) {
      console.error('Error resolving threat:', err);
    }
  };

  const handleBlockSite = async (threat) => {
    const domain = threat.metadata?.domain || threat.metadata?.hostname;
    if (!domain || !threat.device_id) {
      return;
    }

    try {
      await deviceAPI.blockDomain(threat.device_id, domain);
      await threatAPI.blockThreat(threat.id);
      fetchThreats();
      fetchBlockedDomains();
    } catch (err) {
      console.error('Error blocking site:', err);
    }
  };

  const fetchBlockedDomains = async () => {
    try {
      const res = await deviceAPI.getAll({ per_page: 100, active_window_minutes: 60 });
      const devices = res.data?.data || [];
      const allBlocked = [];
      devices.forEach((dev) => {
        const blocked = dev.metadata?.blocked_domains || [];
        blocked.forEach((domain) => {
          allBlocked.push({ domain, deviceId: dev.id, deviceName: dev.display_name || dev.ip_address });
        });
      });
      setBlockedDomains(allBlocked);
    } catch (err) {
      console.error('Error fetching blocked domains:', err);
    }
  };

  const handleUnblockSite = async (domain, deviceId) => {
    try {
      await deviceAPI.unblockDomain(deviceId, domain);
      fetchBlockedDomains();
      fetchThreats();
    } catch (err) {
      console.error('Error unblocking site:', err);
    }
  };

  const getThreatIcon = (level) => {
    switch (level) {
      case 'critical':
        return <AlertTriangle className="w-5 h-5 text-red-500" />;
      case 'high':
        return <AlertCircle className="w-5 h-5 text-orange-500" />;
      case 'medium':
        return <AlertCircle className="w-5 h-5 text-yellow-500" />;
      default:
        return <Shield className="w-5 h-5 text-blue-500" />;
    }
  };

  const getThreatBgColor = (level) => {
    const colors = {
      critical: 'bg-red-900/30 border-red-700',
      high: 'bg-orange-900/30 border-orange-700',
      medium: 'bg-yellow-900/30 border-yellow-700',
      low: 'bg-blue-900/30 border-blue-700',
    };
    return colors[level] || 'bg-slate-700';
  };

  return (
    <div className="min-h-screen bg-gradient-to-br from-slate-950 via-slate-900 to-slate-800 p-8 page-shell">
      <div className="max-w-7xl mx-auto">
        <div className="mb-8 flex items-end justify-between gap-4 flex-wrap">
          <div>
            <h1 className="text-4xl font-bold text-white mb-2">Security Center</h1>
            <p className="text-slate-400">Monitor and manage network threats</p>
          </div>
          <div className={`flex items-center gap-1.5 px-3 py-1 rounded-full text-sm font-medium ${
            wsStatus === 'connected' ? 'bg-green-500/20 text-green-400 border border-green-500/30' :
            wsStatus === 'connecting' ? 'bg-yellow-500/20 text-yellow-400 border border-yellow-500/30' :
            'bg-red-500/20 text-red-400 border border-red-500/30'
          }`}>
            {wsStatus === 'connected' ? <Wifi className="w-4 h-4" /> : <WifiOff className="w-4 h-4" />}
            {wsStatus === 'connected' ? 'Live Stream' : wsStatus === 'connecting' ? 'Connecting...' : 'Polling Fallback'}
          </div>
        </div>

        {error && (
          <div className="bg-red-900 border border-red-700 text-red-100 px-4 py-3 rounded mb-6">
            {error}
          </div>
        )}

        {/* Filters */}
        <div className="bg-slate-800 rounded-lg p-6 border border-slate-700 mb-6 surface-card hover:shadow-2xl">
          <div className="flex gap-4 flex-wrap">
            <div>
              <label className="block text-sm font-medium text-slate-300 mb-2">Threat Level</label>
              <select
                value={filters.level}
                onChange={(e) => setFilters({ ...filters, level: e.target.value })}
                className="bg-slate-700 text-white px-4 py-2 rounded border border-slate-600"
              >
                <option value="all">All Levels</option>
                <option value="critical">Critical</option>
                <option value="high">High</option>
                <option value="medium">Medium</option>
                <option value="low">Low</option>
              </select>
            </div>
            <div>
              <label className="block text-sm font-medium text-slate-300 mb-2">Period</label>
              <select
                value={filters.period}
                onChange={(e) => setFilters({ ...filters, period: e.target.value })}
                className="bg-slate-700 text-white px-4 py-2 rounded border border-slate-600"
              >
                <option value="5m">Last 5 Minutes</option>
                <option value="15m">Last 15 Minutes</option>
                <option value="30m">Last 30 Minutes</option>
                <option value="1h">Last Hour</option>
                <option value="24h">Last 24 Hours</option>
                <option value="7d">Last 7 Days</option>
              </select>
            </div>
            <div>
              <label className="block text-sm font-medium text-slate-300 mb-2">Type</label>
              <select
                value={filters.type}
                onChange={(e) => setFilters({ ...filters, type: e.target.value })}
                className="bg-slate-700 text-white px-4 py-2 rounded border border-slate-600"
              >
                <option value="all">All Types</option>
                <option value="site_visit">Website Visits</option>
                <option value="http_insecure">Insecure HTTP Sites</option>
                <option value="site_blocked">Blocked Site Access</option>
                <option value="malware_signature">Blacklisted / Unsafe Sites</option>
                <option value="suspicious_dns">Suspicious DNS</option>
                <option value="vpn_detection">VPN Detection</option>
                <option value="port_scan">Port Scans</option>
                <option value="ddos_attempt">DDoS Attempts</option>
                <option value="anomalous_traffic">Anomalous Traffic</option>
                <option value="arp_spoofing">ARP Spoofing</option>
                <option value="excessive_connections">Excessive Connections</option>
              </select>
            </div>
          </div>
        </div>

        {/* Blocked Domains */}
        {blockedDomains.length > 0 && (
          <div className="bg-slate-800 rounded-lg p-6 border border-slate-700 mb-6">
            <div className="flex items-center mb-4">
              <Ban className="w-5 h-5 text-orange-400 mr-2" />
              <h2 className="text-xl font-semibold text-white">Blocked Domains</h2>
              <span className="ml-2 px-2 py-0.5 bg-orange-500/20 text-orange-400 text-xs rounded-full">{blockedDomains.length}</span>
            </div>
            <div className="space-y-2 max-h-48 overflow-y-auto">
              {blockedDomains.map((item, idx) => (
                <div key={idx} className="flex justify-between items-center bg-slate-700 p-3 rounded">
                  <div className="flex-1 min-w-0">
                    <p className="text-white truncate font-mono text-sm">{item.domain}</p>
                    <p className="text-slate-400 text-xs">{item.deviceName}</p>
                  </div>
                  <button
                    onClick={() => handleUnblockSite(item.domain, item.deviceId)}
                    className="ml-3 px-4 py-1.5 bg-green-600 hover:bg-green-700 text-white text-sm rounded font-medium transition flex items-center gap-1.5"
                  >
                    <Unlock className="w-3.5 h-3.5" />
                    Unblock
                  </button>
                </div>
              ))}
            </div>
          </div>
        )}

        {/* Threats List */}
        <div className="space-y-4">
          {loading ? (
            <div className="text-center text-slate-400">Loading threats...</div>
          ) : threats.length > 0 ? (
            threats.map((threat) => (
              <div
                key={threat.id}
                className={`${getThreatBgColor(threat.threat_level)} border rounded-lg p-4 surface-card hover:shadow-2xl`}
              >
                <div className="flex items-start justify-between">
                  <div className="flex items-start gap-4 flex-1">
                    <div className="mt-1">{getThreatIcon(threat.threat_level)}</div>
                    <div className="flex-1">
                      <h3 className="text-white font-semibold text-lg">
                        {(threat.metadata?.event_type || threat.threat_type).replace('_', ' ').toUpperCase()}
                      </h3>
                      <p className="text-slate-300 mt-1">{threat.description}</p>
                      {(threat.metadata?.domain || threat.metadata?.browser || threat.metadata?.app_name) && (
                        <div className="flex flex-wrap gap-2 mt-2 text-xs text-slate-400">
                          {threat.metadata?.domain && <span className="px-2 py-1 rounded bg-slate-700">Site: {threat.metadata.domain}</span>}
                          {threat.metadata?.browser && <span className="px-2 py-1 rounded bg-slate-700">Browser: {threat.metadata.browser}</span>}
                          {threat.metadata?.app_name && !threat.metadata?.browser && <span className="px-2 py-1 rounded bg-slate-700">App: {threat.metadata.app_name}</span>}
                        </div>
                      )}
                      <div className="flex gap-6 mt-3 text-sm text-slate-400">
                        <span>From: {threat.source_ip}</span>
                        <span>To: {threat.destination_ip}</span>
                        <span>Port: {threat.destination_port || 'N/A'}</span>
                        <span>{new Date(threat.detected_at).toLocaleString()}</span>
                      </div>
                    </div>
                  </div>

                  {/* Actions */}
                  <div className="flex gap-2 ml-4">
                    {(threat.metadata?.domain || threat.metadata?.hostname) && !threat.blocked && (
                      <button
                        onClick={() => handleBlockSite(threat)}
                        className="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded text-sm font-medium transition"
                      >
                        Block Site
                      </button>
                    )}
                    {!threat.blocked && (
                      <button
                        onClick={() => handleBlockThreat(threat.id)}
                        className="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded text-sm font-medium transition"
                      >
                        Block
                      </button>
                    )}
                    {!threat.is_resolved && (
                      <button
                        onClick={() => handleResolveThreat(threat.id)}
                        className="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded text-sm font-medium transition"
                      >
                        Resolve
                      </button>
                    )}
                    {threat.is_resolved && (
                      <div className="flex items-center gap-2 text-green-400">
                        <CheckCircle className="w-5 h-5" />
                        <span className="text-sm font-medium">Resolved</span>
                      </div>
                    )}
                  </div>
                </div>
              </div>
            ))
          ) : (
            <div className="bg-slate-800 rounded-lg p-8 border border-slate-700 text-center">
              <Shield className="w-12 h-12 text-green-500 mx-auto mb-3 opacity-50" />
              <p className="text-slate-400">No threats detected</p>
            </div>
          )}
        </div>
      </div>
    </div>
  );
}
