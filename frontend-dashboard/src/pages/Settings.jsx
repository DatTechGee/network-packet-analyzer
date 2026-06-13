import React, { useEffect, useState } from 'react';

const STORAGE_KEYS = {
  activeWindowMinutes: 'networkAnalyzer.activeWindowMinutes',
  refreshInterval: 'networkAnalyzer.refreshInterval',
  threatStrictMode: 'networkAnalyzer.threatStrictMode',
};

export default function Settings() {
  const [activeWindowMinutes, setActiveWindowMinutes] = useState('15');
  const [refreshInterval, setRefreshInterval] = useState('5');
  const [threatStrictMode, setThreatStrictMode] = useState(false);
  const [saved, setSaved] = useState(false);

  useEffect(() => {
    const storedWindow = Number(localStorage.getItem(STORAGE_KEYS.activeWindowMinutes)) || 2;
    setActiveWindowMinutes(String(Math.max(storedWindow, 0.25)));
    setRefreshInterval(localStorage.getItem(STORAGE_KEYS.refreshInterval) || '5');
    setThreatStrictMode(localStorage.getItem(STORAGE_KEYS.threatStrictMode) === 'true');
  }, []);

  const saveSettings = () => {
    localStorage.setItem(STORAGE_KEYS.activeWindowMinutes, activeWindowMinutes);
    localStorage.setItem(STORAGE_KEYS.refreshInterval, refreshInterval);
    localStorage.setItem(STORAGE_KEYS.threatStrictMode, String(threatStrictMode));
    setSaved(true);
    setTimeout(() => setSaved(false), 2000);
  };

  return (
    <div className="min-h-screen bg-gradient-to-br from-slate-900 to-slate-800 p-8">
      <div className="max-w-4xl mx-auto">
        <div className="mb-8">
          <h1 className="text-4xl font-bold text-white mb-2">Settings</h1>
          <p className="text-slate-400">Configure G2 Project packet tracer monitoring preferences.</p>
        </div>

        <div className="bg-slate-800 rounded-lg p-6 border border-slate-700 space-y-6">
          <SettingRow
            title="Active device window"
            description="How long a device stays visible after its last packet (in minutes). 0.25 = 15 seconds."
            control={
              <input
                type="number"
                min="0.25"
                max="1440"
                step="0.25"
                value={activeWindowMinutes}
                onChange={(e) => setActiveWindowMinutes(e.target.value)}
                className="bg-slate-700 text-white px-4 py-2 rounded border border-slate-600 w-32"
              />
            }
          />

          <SettingRow
            title="Refresh interval"
            description="Used by the UI refresh timing for dashboard polling."
            control={
              <input
                type="number"
                min="2"
                max="300"
                value={refreshInterval}
                onChange={(e) => setRefreshInterval(e.target.value)}
                className="bg-slate-700 text-white px-4 py-2 rounded border border-slate-600 w-32"
              />
            }
          />

          <SettingRow
            title="Strict threat mode"
            description="Keep detection conservative to reduce false positives on normal home traffic."
            control={
              <label className="inline-flex items-center gap-3 text-white">
                <input
                  type="checkbox"
                  checked={threatStrictMode}
                  onChange={(e) => setThreatStrictMode(e.target.checked)}
                  className="h-4 w-4"
                />
                Enabled
              </label>
            }
          />

          <div className="flex justify-end items-center gap-3">
            {saved && <span className="text-emerald-400 text-sm">Settings saved</span>}
            <button
              onClick={saveSettings}
              className="bg-blue-600 hover:bg-blue-700 text-white px-5 py-2 rounded font-medium transition"
            >
              Save Settings
            </button>
          </div>
        </div>
      </div>
    </div>
  );
}

function SettingRow({ title, description, control }) {
  return (
    <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-4 border-b border-slate-700 pb-6 last:border-b-0 last:pb-0">
      <div className="max-w-2xl">
        <h2 className="text-lg font-semibold text-white">{title}</h2>
        <p className="text-slate-400 text-sm mt-1">{description}</p>
      </div>
      <div>{control}</div>
    </div>
  );
}