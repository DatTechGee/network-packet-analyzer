import React, { useEffect, useState, useRef } from 'react';
import { deviceAPI } from '../services/api';
import { Server, Wifi, Clock3, HardDrive, ShieldBan, ShieldCheck } from 'lucide-react';

const ACTIVE_WINDOW_MINUTES = 2;
const DEVICE_PAGE_SIZE = 1000;

function isInfrastructureDevice(device) {
  const deviceType = (device.device_type || '').toLowerCase();
  const label = `${device.display_name || ''} ${device.device_name || ''}`.toLowerCase();

  return (
    deviceType === 'router' ||
    deviceType === 'hotspot_gateway' ||
    deviceType === 'external' ||
    label.includes('gateway') ||
    label.includes('router') ||
    label.includes('external hosts')
  );
}

export default function Devices() {
  const [devices, setDevices] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const initialLoadDone = useRef(false);

  useEffect(() => {
    fetchDevices();
    const interval = setInterval(fetchDevices, 2000);
    return () => clearInterval(interval);
  }, []);

  const fetchDevices = async () => {
    try {
      if (!initialLoadDone.current) {
        setLoading(true);
      }
      const response = await deviceAPI.getAll({
        per_page: DEVICE_PAGE_SIZE,
        active_window_minutes: ACTIVE_WINDOW_MINUTES,
      });
      const visibleDevices = (response.data.data || []).filter((device) => !isInfrastructureDevice(device));
      setDevices(visibleDevices);
      setError(null);
    } catch (err) {
      console.error('Error fetching devices:', err);
      setError('Failed to load devices');
    } finally {
      initialLoadDone.current = true;
      setLoading(false);
    }
  };

  const handleBlockDevice = async (device) => {
    const name = device.display_name || device.device_name || device.ip_address;
    const confirmed = confirm(`Block ${name}?\n\nThis will add a Windows Firewall rule to block all traffic to/from this device.`);
    if (!confirmed) return;

    try {
      const res = await deviceAPI.blockDevice(device.id);
      alert(res.data.message || 'Device blocked');
      fetchDevices();
    } catch (err) {
      alert(`Error: ${err.response?.data?.message || err.message}`);
    }
  };

  const handleUnblockDevice = async (device) => {
    try {
      const res = await deviceAPI.unblockDevice(device.id);
      alert(res.data.message || 'Device unblocked');
      fetchDevices();
    } catch (err) {
      alert(`Error: ${err.message}`);
    }
  };

  const activeDevices = devices.filter((device) => device.is_active || device.is_online);
  const blockedDevices = devices.filter((device) => device.is_blocked);

  return (
    <div className="min-h-screen bg-gradient-to-br from-slate-950 via-slate-900 to-slate-800 p-8 page-shell">
      <div className="max-w-7xl mx-auto">
        <div className="mb-8">
          <h1 className="text-4xl font-bold text-white mb-2">Devices</h1>
          <p className="text-slate-400">Live device discovery with online status and identity details</p>
        </div>

        {error && (
          <div className="bg-red-900 border border-red-700 text-red-100 px-4 py-3 rounded mb-6">
            {error}
          </div>
        )}

        <div className="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
          <StatCard icon={Server} label="Total Devices" value={devices.length} />
          <StatCard icon={Wifi} label="Active Now" value={activeDevices.length} />
          <StatCard icon={ShieldBan} label="Blocked" value={blockedDevices.length} danger={blockedDevices.length > 0} />
          <StatCard icon={Clock3} label="Offline" value={Math.max(devices.length - activeDevices.length, 0)} />
        </div>

        <div className="bg-slate-800 rounded-2xl border border-slate-700 overflow-hidden surface-card">
          {loading ? (
            <div className="p-8 text-center text-slate-400">Loading devices...</div>
          ) : devices.length > 0 ? (
            <div className="overflow-x-auto">
              <table className="w-full text-left">
                <thead className="bg-slate-900/60 text-slate-300 text-sm uppercase tracking-wide">
                  <tr>
                    <th className="px-6 py-4">Device</th>
                    <th className="px-6 py-4">Type</th>
                    <th className="px-6 py-4">IP Address</th>
                    <th className="px-6 py-4">Vendor</th>
                    <th className="px-6 py-4">Last Seen</th>
                    <th className="px-6 py-4">Status</th>
                    <th className="px-6 py-4">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  {devices.map((device) => (
                    <tr key={device.id} className={`border-t border-slate-700 hover:bg-slate-700/40 ${device.is_blocked ? 'bg-red-900/10' : ''}`}>
                      <td className="px-6 py-4">
                        <div className="font-semibold text-white flex items-center gap-2">
                          {device.display_name || device.device_name || device.ip_address}
                          {device.is_blocked && (
                            <span className="px-2 py-0.5 bg-red-600/30 text-red-300 text-[10px] font-bold rounded-full border border-red-500 uppercase">
                              Blocked
                            </span>
                          )}
                        </div>
                        <div className="text-sm text-slate-400">{device.mac_address}</div>
                        {device.metadata?.fingerprint && (
                          <div className="text-[11px] text-slate-500 font-mono mt-1 truncate max-w-[20rem]">
                            FP: {device.metadata.fingerprint}
                          </div>
                        )}
                        {device.metadata?.os_guess && (
                          <div className="text-[11px] text-sky-300 mt-1">
                            OS: {device.metadata.os_guess}
                          </div>
                        )}
                      </td>
                      <td className="px-6 py-4 text-slate-300 capitalize">{device.device_type || 'unknown'}</td>
                      <td className="px-6 py-4 text-slate-300">{device.ip_address || 'N/A'}</td>
                      <td className="px-6 py-4 text-slate-300">{device.vendor || 'Unknown'}</td>
                      <td className="px-6 py-4 text-slate-300">{device.last_seen ? new Date(device.last_seen).toLocaleString() : 'Never'}</td>
                      <td className="px-6 py-4">
                        <span
                          className={`inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold ${
                            device.is_active || device.is_online
                              ? 'bg-emerald-500/20 text-emerald-300'
                              : 'bg-slate-700 text-slate-300'
                          }`}
                        >
                          {device.is_active || device.is_online ? 'Online' : 'Offline'}
                        </span>
                      </td>
                      <td className="px-6 py-4">
                        {device.is_blocked ? (
                          <button
                            onClick={() => handleUnblockDevice(device)}
                            className="flex items-center gap-1 px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold rounded-lg transition-colors"
                          >
                            <ShieldCheck className="w-3.5 h-3.5" />
                            Unblock
                          </button>
                        ) : (
                          <button
                            onClick={() => handleBlockDevice(device)}
                            className="flex items-center gap-1 px-3 py-1.5 bg-red-600/80 hover:bg-red-700 text-white text-xs font-semibold rounded-lg transition-colors"
                          >
                            <ShieldBan className="w-3.5 h-3.5" />
                            Block
                          </button>
                        )}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          ) : (
            <div className="p-8 text-center text-slate-400">No devices discovered yet</div>
          )}
        </div>
      </div>
    </div>
  );
}

function StatCard({ icon: Icon, label, value, danger }) {
  return (
    <div className={`bg-slate-800 rounded-2xl border p-5 surface-card ${danger ? 'border-red-500/50' : 'border-slate-700'}`}>
      <div className="flex items-center justify-between">
        <div>
          <p className="text-slate-400 text-sm">{label}</p>
          <p className={`text-3xl font-bold mt-1 ${danger ? 'text-red-400' : 'text-white'}`}>{value}</p>
        </div>
        <Icon className={`w-8 h-8 ${danger ? 'text-red-400' : 'text-sky-400'}`} />
      </div>
    </div>
  );
}