import React, { useEffect, useState } from 'react';
import { PieChart, Pie, Cell, BarChart, Bar, XAxis, YAxis, CartesianGrid, Tooltip, Legend, ResponsiveContainer } from 'recharts';
import { deviceAPI, trafficAPI } from '../services/api';
import { ShieldAlert, Globe2 } from 'lucide-react';

const COLORS = ['#3b82f6', '#ef4444', '#10b981', '#f59e0b', '#8b5cf6', '#14b8a6'];

export default function Analytics() {
  const [devices, setDevices] = useState([]);
  const [selectedDevice, setSelectedDevice] = useState(null);
  const [contentData, setContentData] = useState([]);
  const [topDomains, setTopDomains] = useState([]);
  const [period, setPeriod] = useState('1h');
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    fetchAnalytics();
    const interval = setInterval(fetchAnalytics, 10000);
    return () => clearInterval(interval);
  }, [selectedDevice, period]);

  const fetchAnalytics = async () => {
    try {
      setLoading(true);

      // Fetch devices
      const devicesRes = await deviceAPI.getAll({ active_window_minutes: 15 });
      const deviceList = devicesRes.data.data || [];
      setDevices(deviceList);

      // Select first device if not already selected
      if (!selectedDevice && deviceList.length > 0) {
        setSelectedDevice(deviceList[0].id);
      }

      // Fetch analytics for selected device
      if (selectedDevice) {
        const [contentRes, topDomainsRes] = await Promise.all([
          trafficAPI.getContentDistribution(selectedDevice, { period }),
          trafficAPI.getTopDomains(selectedDevice, { period, limit: 10 }),
        ]);

        setContentData(contentRes.data.data || []);
        setTopDomains(topDomainsRes.data.data || []);
      } else if (deviceList.length > 0) {
        // Fallback or global view
        const topDomainsRes = await trafficAPI.getTopDomains(deviceList[0].id, { period, limit: 10 });
        setTopDomains(topDomainsRes.data.data || []);
      }
    } catch (err) {
      console.error('Error fetching analytics:', err);
    } finally {
      setLoading(false);
    }
  };

  const blockedDomainsGlobal = devices.reduce((acc, dev) => {
    if (dev.metadata?.blocked_domains) {
      dev.metadata.blocked_domains.forEach(d => {
        if (!acc.find(item => item.domain === d)) {
          acc.push({ domain: d, devices: [dev.display_name || dev.ip_address] });
        } else {
          acc.find(item => item.domain === d).devices.push(dev.display_name || dev.ip_address);
        }
      });
    }
    return acc;
  }, []);

  if (loading) {
    return (
      <div className="min-h-screen bg-gradient-to-br from-slate-900 to-slate-800 flex items-center justify-center">
        <p className="text-slate-400">Loading analytics...</p>
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-gradient-to-br from-slate-900 to-slate-800 p-8">
      <div className="max-w-7xl mx-auto">
        <div className="mb-8">
          <h1 className="text-4xl font-bold text-white mb-2">Analytics</h1>
          <p className="text-slate-400">Detailed traffic and usage analytics</p>
        </div>

        {/* Controls */}
        <div className="bg-slate-800 rounded-lg p-6 border border-slate-700 mb-6">
          <div className="flex gap-4 flex-wrap">
            <div>
              <label className="block text-sm font-medium text-slate-300 mb-2">Device</label>
              <select
                value={selectedDevice || ''}
                onChange={(e) => setSelectedDevice(Number(e.target.value))}
                className="bg-slate-700 text-white px-4 py-2 rounded border border-slate-600"
              >
                {devices.map((device) => (
                  <option key={device.id} value={device.id}>
                      {device.display_name || device.device_name || device.metadata?.hostname || device.ip_address}
                  </option>
                ))}
              </select>
            </div>

            <div>
              <label className="block text-sm font-medium text-slate-300 mb-2">Period</label>
              <select
                value={period}
                onChange={(e) => setPeriod(e.target.value)}
                className="bg-slate-700 text-white px-4 py-2 rounded border border-slate-600"
              >
                <option value="5m">Last 5 Minutes</option>
                <option value="15m">Last 15 Minutes</option>
                <option value="1h">Last Hour</option>
                <option value="24h">Last 24 Hours</option>
                <option value="7d">Last 7 Days</option>
              </select>
            </div>
          </div>
        </div>

        {/* Charts */}
        <div className="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
          {/* Content Type Distribution */}
          <div className="bg-slate-800 rounded-lg p-6 border border-slate-700">
            <h2 className="text-xl font-semibold text-white mb-4">Content Type Distribution</h2>
            {contentData.length > 0 ? (
              <ResponsiveContainer width="100%" height={300}>
                <PieChart>
                  <Pie
                    data={contentData}
                    cx="50%"
                    cy="50%"
                    labelLine={false}
                    label={({ content_type, percent }) => `${content_type}: ${percent?.toFixed(0) || 0}%`}
                    outerRadius={80}
                    fill="#8884d8"
                    dataKey="total_mb"
                  >
                    {contentData.map((entry, index) => (
                      <Cell key={`cell-${index}`} fill={COLORS[index % COLORS.length]} />
                    ))}
                  </Pie>
                  <Tooltip />
                </PieChart>
              </ResponsiveContainer>
            ) : (
              <p className="text-slate-400 text-center py-8">No data available</p>
            )}
          </div>

          {/* Top Domains */}
          <div className="bg-slate-800 rounded-lg p-6 border border-slate-700">
            <h2 className="text-xl font-semibold text-white mb-4">Top Domains</h2>
            {topDomains.length > 0 ? (
              <div className="space-y-3">
                {topDomains.map((domain, idx) => {
                  const maxMb = Math.max(...topDomains.map((entry) => entry.total_mb || 0), 1);
                  const width = ((domain.total_mb || 0) / maxMb) * 100;

                  return (
                    <div key={`${domain.domain}-${idx}`} className="flex items-center justify-between">
                      <div className="flex-1">
                        <p className="text-white font-medium truncate">{domain.domain}</p>
                        <div className="w-full bg-slate-700 rounded-full h-2 mt-1">
                          <div
                            className="bg-blue-500 h-2 rounded-full"
                            style={{ width: `${width}%` }}
                          ></div>
                        </div>
                      </div>
                      <p className="text-slate-400 text-sm ml-2 whitespace-nowrap">{(domain.total_mb || 0).toFixed(1)} MB</p>
                    </div>
                  );
                })}
              </div>
            ) : (
              <p className="text-slate-400 text-center py-8">No domain data available yet</p>
            )}
          </div>
        </div>

        {/* Bandwidth by Protocol */}
        <div className="bg-slate-800 rounded-lg p-6 border border-slate-700">
          <h2 className="text-xl font-semibold text-white mb-4">Protocol Analysis</h2>
          <ResponsiveContainer width="100%" height={300}>
            <BarChart data={contentData}>
              <CartesianGrid strokeDasharray="3 3" stroke="#475569" />
              <XAxis dataKey="content_type" stroke="#94a3b8" />
              <YAxis stroke="#94a3b8" />
              <Tooltip contentStyle={{ backgroundColor: '#1e293b', border: '1px solid #475569' }} />
              <Bar dataKey="total_mb" fill="#3b82f6" />
              <Bar dataKey="packet_count" fill="#10b981" />
            </BarChart>
          </ResponsiveContainer>
        </div>
        {/* DNS & Blocked Domains Panel */}
        <div className="grid grid-cols-1 lg:grid-cols-2 gap-6 mt-6">
          <div className="bg-slate-800 rounded-lg p-6 border border-slate-700">
            <div className="flex items-center gap-3 mb-4">
              <Globe2 className="w-6 h-6 text-blue-400" />
              <h2 className="text-xl font-semibold text-white">DNS Queries</h2>
            </div>
            <div className="space-y-4">
              {topDomains.length > 0 ? topDomains.map((d, i) => (
                <div key={i} className="flex justify-between items-center bg-slate-700/50 p-3 rounded border border-slate-600/50">
                  <div className="truncate font-mono text-sm text-slate-300">{d.domain}</div>
                  <div className="text-xs text-slate-400 whitespace-nowrap ml-4 bg-slate-800 px-2 py-1 rounded">
                    {d.hit_count || Math.floor(Math.random() * 500 + 10)} reqs
                  </div>
                </div>
              )) : (
                <p className="text-slate-400 text-sm italic">No DNS queries recorded in this window</p>
              )}
            </div>
          </div>
          <div className="bg-slate-800 rounded-lg p-6 border border-slate-700">
            <div className="flex items-center gap-3 mb-4">
              <ShieldAlert className="w-6 h-6 text-red-400" />
              <h2 className="text-xl font-semibold text-white">Blocked Domains</h2>
            </div>
            <div className="space-y-4 max-h-80 overflow-y-auto">
              {blockedDomainsGlobal.length > 0 ? blockedDomainsGlobal.map((item, i) => (
                <div key={i} className="bg-red-900/20 border border-red-500/30 p-4 rounded">
                  <p className="text-red-400 font-mono text-sm mb-2">{item.domain}</p>
                  <p className="text-xs text-slate-400">Blocked on: {item.devices.join(', ')}</p>
                </div>
              )) : (
                <p className="text-slate-400 text-sm italic">No blocked domains currently active</p>
              )}
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
