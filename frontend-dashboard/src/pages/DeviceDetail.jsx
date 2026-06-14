import React, { useEffect, useRef, useState } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { ArrowLeft, AlertTriangle, Globe, Zap, Shield, Ban, Network } from 'lucide-react';
import { deviceAPI, trafficAPI, threatAPI } from '../services/api';

function isDeviceConnected(device) {
  const lastSeenAt = device.last_seen ? new Date(device.last_seen).getTime() : 0;
  return Boolean(device.is_active || (lastSeenAt && Date.now() - lastSeenAt <= 15 * 60 * 1000));
}

export default function DeviceDetail() {
  const { deviceId } = useParams();
  const navigate = useNavigate();
  const [device, setDevice] = useState(null);
  const [sites, setSites] = useState([]);
  const [selectedSite, setSelectedSite] = useState(null);
  const [sitePeriod, setSitePeriod] = useState('24h');
  const [bandwidth, setBandwidth] = useState(null);
  const [threats, setThreats] = useState([]);
  const [connections, setConnections] = useState([]);
  const [loading, setLoading] = useState(true);
  const [lastUpdated, setLastUpdated] = useState(null);
  const [error, setError] = useState(null);
  const initialLoadDone = useRef(false);

  useEffect(() => {
    fetchDeviceDetails();
    const interval = setInterval(fetchDeviceDetails, 600000);
    return () => clearInterval(interval);
  }, [deviceId, sitePeriod]);

  const fetchDeviceDetails = async () => {
    try {
      if (!initialLoadDone.current) setLoading(true);
      setError(null);

      const [devRes, siteRes, threatRes, connRes] = await Promise.allSettled([
        deviceAPI.getById(deviceId),
        trafficAPI.getTopDomains(deviceId, { limit: 20, period: sitePeriod }),
        threatAPI.getDeviceThreats(deviceId, { period: '24h' }),
        deviceAPI.getConnections(deviceId, { limit: 50 }),
      ]);

      if (devRes.status === 'fulfilled') {
        const devData = devRes.value.data.data || {};
        setDevice(devData.device || {});
        setBandwidth(devData.bandwidth || {});
      }

      if (siteRes.status === 'fulfilled') {
        const domainSites = siteRes.value.data.data || [];
        setSites(domainSites);
        setSelectedSite((current) => {
          if (!current) {
            return domainSites[0] || null;
          }

          return domainSites.find((site) => site.domain === current.domain) || domainSites[0] || null;
        });
      }

      if (threatRes.status === 'fulfilled') {
        setThreats(threatRes.value.data.data || []);
      }

      if (connRes.status === 'fulfilled') {
        setConnections(connRes.value.data.data || []);
      }

      initialLoadDone.current = true;
      setLastUpdated(new Date());
    } catch (err) {
      setError(err.message);
    } finally {
      setLoading(false);
    }
  };

  const handleBlockSite = async (domain) => {
    try {
      await deviceAPI.blockDomain(deviceId, domain);
      alert(`Blocked ${domain}`);
      // Refresh threats to show update
      fetchDeviceDetails();
    } catch (err) {
      alert(`Error blocking domain: ${err.message}`);
    }
  };

  const handleUnblockSite = async (domain) => {
    try {
      await deviceAPI.unblockDomain(deviceId, domain);
      alert(`Unblocked ${domain}`);
      // Refresh to show update
      fetchDeviceDetails();
    } catch (err) {
      alert(`Error unblocking domain: ${err.message}`);
    }
  };

  const handleLimitBandwidth = async () => {
    const mbps = prompt('Enter bandwidth limit (Mbps):', '10');
    if (!mbps) return;
    
    try {
      await deviceAPI.setBandwidthLimit(deviceId, parseFloat(mbps), 'daily');
      alert(`Bandwidth limited to ${mbps} Mbps`);
    } catch (err) {
      alert(`Error limiting bandwidth: ${err.message}`);
    }
  };

  const handleSelectSite = (site) => {
    setSelectedSite(site);
  };

  if (loading) {
    return (
      <div className="min-h-screen bg-gray-900 flex items-center justify-center">
        <div className="text-white">Loading device details...</div>
      </div>
    );
  }

  if (error || !device) {
    return (
      <div className="min-h-screen bg-gray-900 text-white p-6">
        <button onClick={() => navigate(-1)} className="flex items-center text-blue-400 hover:text-blue-300 mb-4">
          <ArrowLeft className="w-5 h-5 mr-2" />
          Back
        </button>
        <div className="text-red-400">Error loading device: {error}</div>
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-gradient-to-br from-slate-950 via-slate-900 to-slate-800 text-white p-6 page-shell">
      {/* Header */}
      <button onClick={() => navigate(-1)} className="flex items-center text-blue-400 hover:text-blue-300 mb-6 transition-all duration-200 ease-out hover:translate-x-[-2px]">
        <ArrowLeft className="w-5 h-5 mr-2" />
        Back to Dashboard
      </button>

      {/* Device Info Card */}
      <div className="bg-slate-800 rounded-lg p-6 mb-6 border border-slate-700 surface-card hover:shadow-2xl">
        <div className="flex items-start justify-between mb-4">
          <div>
            <h1 className="text-3xl font-bold mb-2">
              {device.display_name || device.device_name || device.metadata?.hostname || `Device ${device.ip_address}`}
            </h1>
            <p className="text-gray-400">{device.ip_address}</p>
            <p className="text-gray-500 text-sm">MAC: {device.mac_address}</p>
          </div>
          <div className="text-right">
            <p className={`text-lg font-semibold ${isDeviceConnected(device) ? 'text-green-400' : 'text-red-400'}`}>
              {isDeviceConnected(device) ? '● Connected' : '● Offline'}
            </p>
            <p className="text-gray-400 text-sm">Last seen: {device.last_seen ? new Date(device.last_seen).toLocaleString() : 'Unknown'}</p>
            <div className="flex items-center gap-2 mt-1 justify-end">
              <p className="text-gray-500 text-xs">
                {lastUpdated ? `Updated ${lastUpdated.toLocaleTimeString()}` : 'Refreshing live'}
              </p>
              <button onClick={fetchDeviceDetails} className="text-xs text-blue-400 hover:text-blue-300 underline">Refresh</button>
            </div>
          </div>
        </div>
      </div>

      {/* Threats Alert */}
      {threats.length > 0 && (
        <div className="bg-red-900 bg-opacity-20 border border-red-500 rounded-lg p-4 mb-6">
          <div className="flex items-center mb-2">
            <AlertTriangle className="w-5 h-5 text-red-400 mr-2" />
            <span className="font-semibold text-red-400">{threats.length} Active Threats</span>
          </div>
          {threats.slice(0, 3).map((threat, idx) => (
            <div key={idx} className="text-sm text-red-300 ml-7 mb-1">
              {threat.threat_type} ({threat.threat_level}) - {threat.description}
              {threat.metadata?.domain ? ` · ${threat.metadata.domain}` : ''}
            </div>
          ))}
        </div>
      )}

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
        {/* Bandwidth Stats */}
        <div className="bg-slate-800 rounded-lg p-6 border border-slate-700 surface-card hover:shadow-2xl">
          <div className="flex items-center mb-4">
            <Zap className="w-5 h-5 text-yellow-400 mr-2" />
            <h2 className="text-xl font-semibold">Bandwidth</h2>
          </div>
          <div className="space-y-2">
            <div>
              <p className="text-gray-400 text-sm">Upload</p>
              <p className="text-2xl font-bold text-yellow-400">{bandwidth?.upload_kbps || 0} kbps</p>
            </div>
            <div>
              <p className="text-gray-400 text-sm">Download</p>
              <p className="text-2xl font-bold text-yellow-400">{bandwidth?.download_kbps || 0} kbps</p>
            </div>
            <div>
              <p className="text-gray-400 text-sm">Packets</p>
              <p className="text-2xl font-bold text-yellow-400">{bandwidth?.packet_count || 0}</p>
            </div>
            <div className="pt-2 border-t border-gray-700">
              <p className="text-gray-400 text-sm">Total Data (24h)</p>
              <p className="text-xl font-bold">
                {(((bandwidth?.total_upload_bytes || 0) + (bandwidth?.total_download_bytes || 0)) / (1024 * 1024)).toFixed(2)} MB
              </p>
              <p className="text-xs text-gray-400 mt-1">
                Upload: {((bandwidth?.total_upload_bytes || 0) / (1024 * 1024)).toFixed(2)} MB · Download: {((bandwidth?.total_download_bytes || 0) / (1024 * 1024)).toFixed(2)} MB
              </p>
            </div>
          </div>
          <button
            onClick={handleLimitBandwidth}
            className="mt-4 w-full bg-yellow-600 hover:bg-yellow-700 text-white py-2 rounded flex items-center justify-center"
          >
            <Ban className="w-4 h-4 mr-2" />
            Limit Bandwidth
          </button>
        </div>

        {/* Sites Visited */}
        <div className="bg-slate-800 rounded-lg p-6 border border-slate-700 lg:col-span-2 surface-card hover:shadow-2xl">
          <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-4 gap-3">
          <div className="flex items-center">
            <Globe className="w-5 h-5 text-blue-400 mr-2" />
            <h2 className="text-xl font-semibold">Top Sites and Controls</h2>
          </div>
          <div className="flex items-center gap-2 text-sm text-gray-300">
            <label htmlFor="site-period" className="text-gray-400">Window:</label>
            <select
              id="site-period"
              value={sitePeriod}
              onChange={(e) => setSitePeriod(e.target.value)}
              className="bg-gray-700 text-white px-3 py-2 rounded border border-gray-600"
            >
              <option value="5m">Last 5 minutes</option>
              <option value="1h">Last 1 hour</option>
              <option value="24h">Last 24 hours</option>
              <option value="7d">Last 7 days</option>
            </select>
          </div>
        </div>
          <div className="space-y-2 max-h-64 overflow-y-auto">
            {sites.length > 0 ? (
              sites.map((site, idx) => (
                <div
                  key={idx}
                  onClick={() => handleSelectSite(site)}
                  className={`flex justify-between items-center bg-slate-700 p-3 rounded hover:bg-slate-600 cursor-pointer border transition-all duration-200 ease-out ${selectedSite?.domain === site.domain ? 'border-blue-500' : 'border-transparent'} hover:-translate-y-0.5`}
                >
                  <div className="flex-1 min-w-0">
                    <p className="text-white truncate font-mono text-sm">{site.domain}</p>
                    <p className="text-gray-400 text-xs">{site.total_mb} MB · {site.hit_count || 0} hits</p>
                  </div>
                  <button
                    onClick={(e) => {
                      e.stopPropagation();
                      handleBlockSite(site.domain);
                    }}
                    className="ml-2 px-3 py-1 bg-red-600 hover:bg-red-700 text-white text-xs rounded"
                  >
                    Block
                  </button>
                </div>
              ))
            ) : (
              <div className="text-gray-400 text-center py-4">
                <p>No sites captured yet</p>
                <p className="text-xs mt-1 text-gray-500">Sites appear when this device browses the web</p>
              </div>
            )}
          </div>
        </div>
      </div>

      {selectedSite && (
        <div className="bg-slate-800 rounded-lg p-6 border border-slate-700 mb-6 surface-card hover:shadow-2xl">
          <div className="flex items-center justify-between mb-3">
            <div>
              <h2 className="text-xl font-semibold">Site Details</h2>
              <p className="text-gray-400 text-sm">Selected site analytics and action controls</p>
            </div>
            <button
              onClick={() => handleBlockSite(selectedSite.domain)}
              className="px-4 py-2 bg-red-600 hover:bg-red-700 text-white text-sm rounded"
            >
              Block This Site
            </button>
          </div>
          <div className="grid grid-cols-1 md:grid-cols-4 gap-4 text-sm">
            <div className="bg-gray-700 rounded p-3">
              <p className="text-gray-400">Domain</p>
              <p className="text-white font-mono break-all">{selectedSite.domain}</p>
            </div>
            <div className="bg-gray-700 rounded p-3">
              <p className="text-gray-400">Data Used</p>
              <p className="text-white">{selectedSite.total_mb} MB</p>
            </div>
            <div className="bg-gray-700 rounded p-3">
              <p className="text-gray-400">Hits</p>
              <p className="text-white">{selectedSite.hit_count || 0}</p>
            </div>
            <div className="bg-gray-700 rounded p-3">
              <p className="text-gray-400">Last Seen</p>
              <p className="text-white">{selectedSite.last_seen ? new Date(selectedSite.last_seen).toLocaleString() : 'Unknown'}</p>
            </div>
          </div>
        </div>
      )}

      {/* Threat History and Blocked Domains */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {/* Blocked Domains */}
        <div className="bg-gray-800 rounded-lg p-6 border border-gray-700">
          <div className="flex items-center mb-4">
            <Ban className="w-5 h-5 text-orange-400 mr-2" />
            <h2 className="text-xl font-semibold">Blocked Domains</h2>
          </div>
          <div className="space-y-2 max-h-48 overflow-y-auto">
            {device?.metadata?.blocked_domains?.length > 0 ? (
              device.metadata.blocked_domains.map((domain, idx) => (
                <div key={idx} className="flex justify-between items-center bg-gray-700 p-3 rounded">
                  <p className="text-white truncate font-mono text-sm">{domain}</p>
                  <button
                    onClick={() => handleUnblockSite(domain)}
                    className="ml-2 px-3 py-1 bg-green-600 hover:bg-green-700 text-white text-xs rounded"
                  >
                    Unblock
                  </button>
                </div>
              ))
            ) : (
              <div className="text-gray-400 text-center py-4">No blocked domains</div>
            )}
          </div>
        </div>

        {/* Threat History */}
        {threats.length > 0 && (
          <div className="bg-gray-800 rounded-lg p-6 border border-gray-700">
            <div className="flex items-center mb-4">
              <Shield className="w-5 h-5 text-red-400 mr-2" />
              <h2 className="text-xl font-semibold">Threat History (24h)</h2>
            </div>
            <div className="space-y-2 max-h-48 overflow-y-auto">
              {threats.map((threat, idx) => (
                <div
                  key={idx}
                  className={`p-3 rounded border ${
                    threat.threat_level === 'high'
                      ? 'bg-red-900 bg-opacity-30 border-red-500'
                      : threat.threat_level === 'medium'
                      ? 'bg-yellow-900 bg-opacity-30 border-yellow-500'
                      : 'bg-blue-900 bg-opacity-30 border-blue-500'
                  }`}
                >
                  <div className="flex justify-between items-start">
                    <div>
                      <p className="font-semibold">{threat.threat_type}</p>
                      <p className="text-gray-300 text-sm">{threat.description}</p>
                    </div>
                    <span className={`text-xs font-bold px-2 py-1 rounded ${
                      threat.threat_level === 'high'
                        ? 'bg-red-600 text-white'
                        : threat.threat_level === 'medium'
                        ? 'bg-yellow-600 text-white'
                        : 'bg-blue-600 text-white'
                    }`}>
                      {threat.threat_level.toUpperCase()}
                    </span>
                  </div>
                  <p className="text-gray-400 text-xs mt-2">
                    {new Date(threat.detected_at).toLocaleString()}
                  </p>
                </div>
              ))}
            </div>
          </div>
        )}
      </div>

      {/* Connection Tree Tracker */}
      <div className="bg-slate-800 rounded-lg p-6 border border-slate-700 mt-6 surface-card hover:shadow-2xl">
        <div className="flex items-center mb-4">
          <Network className="w-5 h-5 text-blue-400 mr-2" />
          <h2 className="text-xl font-semibold">Live Connection Tree</h2>
        </div>
        <div className="bg-slate-900 rounded p-4 font-mono text-sm text-slate-300 overflow-x-auto">
          <div>{device.ip_address}</div>
          {connections.length > 0 ? (
            connections.map((conn, idx) => {
              const isLast = idx === connections.length - 1;
              const prefix = isLast ? ' └── ' : ' ├── ';
              return (
                <div key={idx} className="flex whitespace-nowrap hover:bg-slate-800 px-2 py-1 rounded">
                  <span className="text-slate-500">{prefix}</span>
                  <span className={conn.protocol === 'HTTPS' ? 'text-green-400' : 'text-blue-400'}>
                    {conn.remote_ip}:{conn.remote_port}
                  </span>
                  {conn.domain && <span className="ml-2 text-slate-500">({conn.domain})</span>}
                  <span className="ml-auto pl-4 text-xs text-slate-600">{conn.state || 'ESTABLISHED'}</span>
                </div>
              );
            })
          ) : (
            <div className="text-slate-500 italic pl-4"> └── No active connections tracked</div>
          )}
        </div>
      </div>
    </div>
  );
}
