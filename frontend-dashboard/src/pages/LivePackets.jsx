import React, { useEffect, useMemo, useState, useRef } from 'react';
import { trafficAPI } from '../services/api';
import { Radio, Search, Clock3, Activity, Wifi, WifiOff } from 'lucide-react';

export default function LivePackets() {
  const [packets, setPackets] = useState([]);
  const [period, setPeriod] = useState('1h');
  const [query, setQuery] = useState('');
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [wsStatus, setWsStatus] = useState('connecting'); // connecting, connected, disconnected
  const wsRef = useRef(null);

  useEffect(() => {
    fetchPackets();
    
    // Setup WebSocket
    const connectWs = () => {
      try {
        const wsUrl = `ws://${window.location.hostname}:6001`;
        const ws = new WebSocket(wsUrl);
        
        ws.onopen = () => {
          setWsStatus('connected');
        };
        
        ws.onmessage = (event) => {
          try {
            const data = JSON.parse(event.data);
            if (data.type === 'packet' || data.event === 'packet_captured' || data.source_ip) {
              const packet = data.data || data;
              setPackets((prev) => [packet, ...prev].slice(0, 500)); // keep last 500
            }
          } catch (e) {
            // ignore non-json or unhandled messages
          }
        };
        
        ws.onclose = () => {
          setWsStatus('disconnected');
          setTimeout(connectWs, 5000); // retry
        };
        
        ws.onerror = () => {
          setWsStatus('disconnected');
        };
        
        wsRef.current = ws;
      } catch (e) {
        setWsStatus('disconnected');
      }
    };
    
    connectWs();
    
    // Fallback polling if WS disconnected
    const interval = setInterval(() => {
      if (wsRef.current?.readyState !== WebSocket.OPEN) {
        fetchPackets();
      }
    }, 5000);

    return () => {
      clearInterval(interval);
      if (wsRef.current) wsRef.current.close();
    };
  }, [period]);

  const fetchPackets = async () => {
    try {
      if (packets.length === 0) setLoading(true);
      const response = await trafficAPI.getLivePackets({ period, limit: 200 });
      setPackets(response.data.data || []);
      setError(null);
    } catch (err) {
      console.error('Error fetching live packets:', err);
      setError('Failed to load live packets');
    } finally {
      setLoading(false);
    }
  };

  const filteredPackets = useMemo(() => {
    const q = query.trim().toLowerCase();
    if (!q) return packets;

    return packets.filter((packet) => {
      const haystack = [
        packet.device?.display_name,
        packet.device?.ip_address,
        packet.source_ip,
        packet.destination_ip,
        packet.protocol,
        packet.domain,
        packet.url,
      ]
        .filter(Boolean)
        .join(' ')
        .toLowerCase();

      return haystack.includes(q);
    });
  }, [packets, query]);

  return (
    <div className="min-h-screen bg-gradient-to-br from-slate-950 via-slate-900 to-slate-800 p-8 page-shell">
      <div className="max-w-7xl mx-auto">
        <div className="mb-8 flex items-end justify-between gap-4 flex-wrap">
          <div>
            <div className="flex items-center gap-4 mb-2">
              <h1 className="text-4xl font-bold text-white">Live Packets</h1>
              <div className={`flex items-center gap-1.5 px-3 py-1 rounded-full text-sm font-medium ${
                wsStatus === 'connected' ? 'bg-green-500/20 text-green-400 border border-green-500/30' :
                wsStatus === 'connecting' ? 'bg-yellow-500/20 text-yellow-400 border border-yellow-500/30' :
                'bg-red-500/20 text-red-400 border border-red-500/30'
              }`}>
                {wsStatus === 'connected' ? <Wifi className="w-4 h-4" /> : <WifiOff className="w-4 h-4" />}
                {wsStatus === 'connected' ? 'Live Stream Active' : wsStatus === 'connecting' ? 'Connecting...' : 'Polling Fallback'}
              </div>
            </div>
            <p className="text-slate-400">Stream the latest captured packets and filter by protocol, IP, or domain</p>
          </div>

          <div className="flex gap-3 flex-wrap">
            <select
              value={period}
              onChange={(e) => setPeriod(e.target.value)}
              className="bg-slate-800 text-white px-4 py-2 rounded-lg border border-slate-700 focus:outline-none focus:border-blue-500"
            >
              <option value="5m">Last 5 Minutes</option>
              <option value="1h">Last Hour</option>
              <option value="24h">Last 24 Hours</option>
              <option value="7d">Last 7 Days</option>
            </select>
            <div className="relative">
              <Search className="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
              <input
                value={query}
                onChange={(e) => setQuery(e.target.value)}
                placeholder="Search packets"
                className="bg-slate-800 text-white pl-9 pr-4 py-2 rounded-lg border border-slate-700 w-64 focus:outline-none focus:border-blue-500"
              />
            </div>
          </div>
        </div>

        {error && (
          <div className="bg-red-900/50 border border-red-500/50 text-red-200 px-4 py-3 rounded-lg mb-6">
            {error}
          </div>
        )}

        <div className="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
          <StatCard icon={Activity} label="Captured Packets" value={filteredPackets.length} color="blue" />
          <StatCard icon={Radio} label="Unique Protocols" value={new Set(filteredPackets.map((p) => p.protocol)).size} color="purple" />
          <StatCard icon={Clock3} label="Latest Capture" value={filteredPackets[0] ? new Date(filteredPackets[0].timestamp || filteredPackets[0].created_at).toLocaleTimeString() : '--'} color="green" />
        </div>

        <div className="bg-slate-800/80 rounded-2xl border border-slate-700 overflow-hidden shadow-xl backdrop-blur-sm">
          {loading && packets.length === 0 ? (
            <div className="p-12 text-center flex flex-col items-center">
              <Activity className="w-8 h-8 text-blue-500 animate-pulse mb-4" />
              <p className="text-slate-400">Loading packet stream...</p>
            </div>
          ) : filteredPackets.length > 0 ? (
            <div className="overflow-x-auto max-h-[800px] overflow-y-auto custom-scrollbar">
              <table className="w-full text-left">
                <thead className="bg-slate-900/80 text-slate-300 text-xs uppercase tracking-wider sticky top-0 backdrop-blur-md z-10">
                  <tr>
                    <th className="px-6 py-4 font-medium">Timestamp</th>
                    <th className="px-6 py-4 font-medium">Device</th>
                    <th className="px-6 py-4 font-medium">Source</th>
                    <th className="px-6 py-4 font-medium">Destination</th>
                    <th className="px-6 py-4 font-medium">Protocol</th>
                    <th className="px-6 py-4 font-medium">Size</th>
                    <th className="px-6 py-4 font-medium">Domain / URL</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-700/50">
                  {filteredPackets.map((packet, idx) => (
                    <tr key={packet.id || idx} className="hover:bg-slate-700/30 transition-colors">
                      <td className="px-6 py-3 text-slate-400 text-sm whitespace-nowrap">
                        {packet.timestamp || packet.created_at ? new Date(packet.timestamp || packet.created_at).toLocaleTimeString() : '--'}
                      </td>
                      <td className="px-6 py-3">
                        <div className="text-slate-200 font-medium text-sm">
                          {packet.device?.display_name || packet.device?.ip_address || 'Unknown'}
                        </div>
                      </td>
                      <td className="px-6 py-3 text-slate-300 text-sm font-mono">
                        {packet.source_ip}{packet.source_port ? <span className="text-slate-500">:{packet.source_port}</span> : ''}
                      </td>
                      <td className="px-6 py-3 text-slate-300 text-sm font-mono">
                        {packet.destination_ip}{packet.destination_port ? <span className="text-slate-500">:{packet.destination_port}</span> : ''}
                      </td>
                      <td className="px-6 py-3">
                        <span className="px-2 py-1 rounded bg-blue-500/10 text-blue-400 text-xs font-semibold uppercase tracking-wider">
                          {packet.protocol || 'Unknown'}
                        </span>
                      </td>
                      <td className="px-6 py-3 text-slate-400 text-sm">
                        {formatBytes(packet.size_bytes || packet.packet_size)}
                      </td>
                      <td className="px-6 py-3 text-slate-300 text-sm max-w-xs truncate">
                        {packet.domain || packet.url || packet.content_type || <span className="text-slate-600">N/A</span>}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          ) : (
            <div className="p-12 text-center flex flex-col items-center">
              <Radio className="w-12 h-12 text-slate-600 mb-4" />
              <p className="text-slate-400 text-lg">No packets found matching your criteria</p>
              <p className="text-slate-500 text-sm mt-2">Adjust your filters or ensure the router-agent is capturing traffic.</p>
            </div>
          )}
        </div>
      </div>
    </div>
  );
}

function StatCard({ icon: Icon, label, value, color }) {
  const colorMap = {
    blue: 'text-blue-400',
    purple: 'text-purple-400',
    green: 'text-green-400',
  };
  const bgMap = {
    blue: 'bg-blue-500/10',
    purple: 'bg-purple-500/10',
    green: 'bg-green-500/10',
  };

  return (
    <div className="bg-slate-800/80 rounded-2xl border border-slate-700 p-6 shadow-lg backdrop-blur-sm relative overflow-hidden group">
      <div className={`absolute top-0 right-0 w-24 h-24 ${bgMap[color]} rounded-full -translate-y-12 translate-x-12 transition-transform group-hover:scale-150`}></div>
      <div className="flex items-center justify-between gap-4 relative z-10">
        <div>
          <p className="text-slate-400 font-medium text-sm mb-1">{label}</p>
          <p className="text-3xl font-bold text-white">{value}</p>
        </div>
        <div className={`p-3 rounded-xl ${bgMap[color]}`}>
          <Icon className={`w-6 h-6 ${colorMap[color]}`} />
        </div>
      </div>
    </div>
  );
}

function formatBytes(bytes) {
  if (!bytes && bytes !== 0) return '0 B';
  const units = ['B', 'KB', 'MB', 'GB'];
  let value = bytes;
  let index = 0;
  while (value >= 1024 && index < units.length - 1) {
    value /= 1024;
    index += 1;
  }
  return `${value.toFixed(index === 0 ? 0 : 1)} ${units[index]}`;
}