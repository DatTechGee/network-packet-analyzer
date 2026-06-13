import React, { useEffect, useMemo, useState } from 'react';
import { BarChart, Bar, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer, Cell } from 'recharts';
import { reportAPI } from '../services/api';
import { FileText, Activity, ShieldAlert, HardDrive } from 'lucide-react';

const COLORS = ['#38bdf8', '#10b981', '#f59e0b', '#ef4444'];

export default function Reports() {
  const [report, setReport] = useState({ summary: {}, top_devices: [], protocols: [] });
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    fetchReport();
  }, []);

  const fetchReport = async () => {
    try {
      setLoading(true);
      const response = await reportAPI.getSummary();
      setReport(response.data.data || { summary: {}, top_devices: [], protocols: [] });
    } catch (error) {
      console.error('Error fetching reports:', error);
    } finally {
      setLoading(false);
    }
  };

  const chartData = useMemo(() => {
    return [
      { label: 'Daily', ...(report.summary?.daily || {}), value: report.summary?.daily?.traffic_mb || 0 },
      { label: 'Weekly', ...(report.summary?.weekly || {}), value: report.summary?.weekly?.traffic_mb || 0 },
      { label: 'Monthly', ...(report.summary?.monthly || {}), value: report.summary?.monthly?.traffic_mb || 0 },
    ];
  }, [report.summary]);

  return (
    <div className="min-h-screen bg-gradient-to-br from-slate-950 via-slate-900 to-slate-800 p-8 page-shell">
      <div className="max-w-7xl mx-auto">
        <div className="mb-8">
          <h1 className="text-4xl font-bold text-white mb-2">Reports</h1>
          <p className="text-slate-400">Daily, weekly, and monthly usage snapshots with device and protocol breakdowns</p>
        </div>

        {loading ? (
          <div className="text-center text-slate-400 py-12">Loading reports...</div>
        ) : (
          <>
            <div className="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
              <StatCard icon={FileText} label="Daily Traffic" value={`${report.summary?.daily?.traffic_mb || 0} MB`} />
              <StatCard icon={Activity} label="Weekly Packets" value={report.summary?.weekly?.packets || 0} />
              <StatCard icon={ShieldAlert} label="Monthly Threats" value={report.summary?.monthly?.threats || 0} />
              <StatCard icon={HardDrive} label="Top Devices" value={report.top_devices?.length || 0} />
            </div>

            <div className="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
              <div className="bg-slate-800 rounded-2xl border border-slate-700 p-6 surface-card">
                <h2 className="text-xl font-semibold text-white mb-4">Traffic Summary</h2>
                <ResponsiveContainer width="100%" height={300}>
                  <BarChart data={chartData}>
                    <CartesianGrid strokeDasharray="3 3" stroke="#334155" />
                    <XAxis dataKey="label" stroke="#94a3b8" />
                    <YAxis stroke="#94a3b8" />
                    <Tooltip contentStyle={{ backgroundColor: '#0f172a', border: '1px solid #334155' }} />
                    <Bar dataKey="value" radius={[8, 8, 0, 0]}>
                      {chartData.map((entry, index) => (
                        <Cell key={entry.label} fill={COLORS[index % COLORS.length]} />
                      ))}
                    </Bar>
                  </BarChart>
                </ResponsiveContainer>
              </div>

              <div className="bg-slate-800 rounded-2xl border border-slate-700 p-6 surface-card">
                <h2 className="text-xl font-semibold text-white mb-4">Protocol Usage</h2>
                <div className="space-y-3">
                  {(report.protocols || []).map((protocol) => (
                    <div key={protocol.protocol}>
                      <div className="flex items-center justify-between text-sm mb-1">
                        <span className="text-white font-medium">{protocol.protocol}</span>
                        <span className="text-slate-400">{protocol.packets} packets</span>
                      </div>
                      <div className="w-full h-2 rounded-full bg-slate-700 overflow-hidden">
                        <div
                          className="h-2 rounded-full bg-sky-500"
                          style={{ width: `${Math.max(8, Math.min(100, protocol.packets * 5))}%` }}
                        />
                      </div>
                    </div>
                  ))}
                  {report.protocols?.length === 0 && <p className="text-slate-400">No protocol data available</p>}
                </div>
              </div>
            </div>

            <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
              <div className="bg-slate-800 rounded-2xl border border-slate-700 p-6 surface-card overflow-hidden">
                <h2 className="text-xl font-semibold text-white mb-4">Top Devices</h2>
                <div className="overflow-x-auto">
                  <table className="w-full text-left text-sm">
                    <thead className="text-slate-400 uppercase tracking-wide">
                      <tr>
                        <th className="py-3">Device</th>
                        <th className="py-3">IP</th>
                        <th className="py-3">Traffic</th>
                        <th className="py-3">Packets</th>
                      </tr>
                    </thead>
                    <tbody>
                      {(report.top_devices || []).map((device) => (
                        <tr key={device.device_id} className="border-t border-slate-700">
                          <td className="py-3 text-white font-medium">{device.device_name}</td>
                          <td className="py-3 text-slate-300">{device.ip_address || 'N/A'}</td>
                          <td className="py-3 text-slate-300">{device.total_mb} MB</td>
                          <td className="py-3 text-slate-300">{device.packets}</td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              </div>

              <div className="bg-slate-800 rounded-2xl border border-slate-700 p-6 surface-card">
                <h2 className="text-xl font-semibold text-white mb-4">Policy Snapshot</h2>
                <div className="space-y-4 text-slate-300">
                  <p>Daily traffic: {report.summary?.daily?.traffic_mb || 0} MB across {report.summary?.daily?.devices || 0} devices.</p>
                  <p>Weekly traffic: {report.summary?.weekly?.traffic_mb || 0} MB with {report.summary?.weekly?.threats || 0} threats.</p>
                  <p>Monthly traffic: {report.summary?.monthly?.traffic_mb || 0} MB with {report.summary?.monthly?.packets || 0} packets.</p>
                </div>
              </div>
            </div>
          </>
        )}
      </div>
    </div>
  );
}

function StatCard({ icon: Icon, label, value }) {
  return (
    <div className="bg-slate-800 rounded-2xl border border-slate-700 p-5 surface-card">
      <div className="flex items-center justify-between gap-4">
        <div>
          <p className="text-slate-400 text-sm">{label}</p>
          <p className="text-2xl font-bold text-white mt-1">{value}</p>
        </div>
        <Icon className="w-8 h-8 text-sky-400" />
      </div>
    </div>
  );
}