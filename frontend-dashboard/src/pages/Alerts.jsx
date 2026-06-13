import React, { useEffect, useState } from 'react';
import { Bell, CheckCheck, RefreshCw, Trash2, ShieldAlert } from 'lucide-react';
import { alertAPI } from '../services/api';

export default function Alerts() {
  const [alerts, setAlerts] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [refreshing, setRefreshing] = useState(false);

  useEffect(() => {
    fetchAlerts();
    const interval = setInterval(fetchAlerts, 15000);
    return () => clearInterval(interval);
  }, []);

  const fetchAlerts = async () => {
    try {
      setRefreshing(true);
      const response = await alertAPI.getAlerts({ limit: 100 });
      setAlerts(response.data?.data || []);
      setError(null);
    } catch (err) {
      console.error('Error fetching alerts:', err);
      setError('Failed to load alerts');
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  };

  const markAsRead = async (id) => {
    try {
      await alertAPI.markAsRead(id);
      fetchAlerts();
    } catch (err) {
      console.error('Error marking alert read:', err);
    }
  };

  const markAllAsRead = async () => {
    try {
      await alertAPI.markAllAsRead();
      fetchAlerts();
    } catch (err) {
      console.error('Error marking all alerts read:', err);
    }
  };

  const clearAlerts = async () => {
    try {
      await alertAPI.deleteAlert();
      fetchAlerts();
    } catch (err) {
      console.error('Error clearing alerts:', err);
    }
  };

  const unreadCount = alerts.filter((alert) => !alert.is_read).length;

  return (
    <div className="min-h-screen bg-gradient-to-br from-slate-950 via-slate-900 to-slate-800 p-8 page-shell">
      <div className="max-w-7xl mx-auto">
        <div className="mb-8 flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
          <div>
            <div className="flex items-center gap-3 mb-2">
              <Bell className="w-7 h-7 text-red-300" />
              <h1 className="text-4xl font-bold text-white">Alerts</h1>
            </div>
            <p className="text-slate-400">All security, device, and traffic alerts in one place.</p>
          </div>

          <div className="flex flex-wrap gap-3">
            <button
              onClick={fetchAlerts}
              className="inline-flex items-center gap-2 rounded-xl border border-slate-700 bg-slate-800 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700"
            >
              <RefreshCw className={`w-4 h-4 ${refreshing ? 'animate-spin' : ''}`} />
              Refresh
            </button>
            <button
              onClick={markAllAsRead}
              className="inline-flex items-center gap-2 rounded-xl border border-blue-700 bg-blue-600/20 px-4 py-2 text-sm font-medium text-blue-200 hover:bg-blue-600/30"
            >
              <CheckCheck className="w-4 h-4" />
              Mark all read
            </button>
            <button
              onClick={clearAlerts}
              className="inline-flex items-center gap-2 rounded-xl border border-red-700 bg-red-600/20 px-4 py-2 text-sm font-medium text-red-200 hover:bg-red-600/30"
            >
              <Trash2 className="w-4 h-4" />
              Clear all
            </button>
          </div>
        </div>

        {error && (
          <div className="mb-6 rounded-xl border border-red-700 bg-red-900 px-4 py-3 text-red-100">
            {error}
          </div>
        )}

        <div className="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
          <StatCard icon={Bell} label="Total Alerts" value={alerts.length} />
          <StatCard icon={ShieldAlert} label="Unread Alerts" value={unreadCount} />
          <StatCard icon={CheckCheck} label="Read Alerts" value={alerts.length - unreadCount} />
        </div>

        <div className="rounded-2xl border border-slate-700 bg-slate-800/80 shadow-2xl overflow-hidden">
          {loading ? (
            <div className="p-12 text-center text-slate-400">Loading alerts...</div>
          ) : alerts.length > 0 ? (
            <div className="divide-y divide-slate-700/60">
              {alerts.map((alert) => (
                <div key={alert.id} className={`p-5 ${alert.is_read ? 'bg-slate-900/20' : 'bg-slate-900/50'}`}>
                  <div className="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                    <div className="flex-1">
                      <div className="flex flex-wrap items-center gap-2 mb-2">
                        <span className="rounded-full bg-slate-700 px-2.5 py-1 text-[11px] font-semibold uppercase tracking-wide text-slate-300">
                          {alert.type}
                        </span>
                        {!alert.is_read && (
                          <span className="rounded-full bg-red-500/15 px-2.5 py-1 text-[11px] font-semibold text-red-300">
                            Unread
                          </span>
                        )}
                      </div>
                      <h3 className="text-lg font-semibold text-white">{alert.title}</h3>
                      <p className="mt-2 text-sm text-slate-300">{alert.message}</p>
                      <div className="mt-3 text-xs text-slate-500">
                        {alert.device?.display_name || alert.device?.ip_address || 'Unknown device'}
                        {' · '}
                        {alert.created_at ? new Date(alert.created_at).toLocaleString() : 'Unknown time'}
                      </div>
                    </div>

                    <div className="flex flex-wrap gap-2">
                      {!alert.is_read && (
                        <button
                          onClick={() => markAsRead(alert.id)}
                          className="rounded-xl border border-slate-700 bg-slate-800 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700"
                        >
                          Mark read
                        </button>
                      )}
                    </div>
                  </div>
                </div>
              ))}
            </div>
          ) : (
            <div className="p-12 text-center text-slate-400">No alerts yet</div>
          )}
        </div>
      </div>
    </div>
  );
}

function StatCard({ icon: Icon, label, value }) {
  return (
    <div className="rounded-2xl border border-slate-700 bg-slate-800/80 p-5 shadow-lg surface-card">
      <div className="flex items-center justify-between gap-4">
        <div>
          <p className="text-slate-400 text-sm">{label}</p>
          <p className="text-3xl font-bold text-white mt-1">{value}</p>
        </div>
        <Icon className="w-8 h-8 text-sky-400" />
      </div>
    </div>
  );
}