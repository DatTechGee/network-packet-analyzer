import React, { useState, useEffect, useRef } from 'react';
import { Gauge, Play, Activity, Clock, Zap, AlertTriangle, ArrowDown, ArrowUp } from 'lucide-react';
import { speedTestAPI } from '../services/api';
import { AreaChart, Area, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer } from 'recharts';

export default function SpeedTest() {
  const [isRunning, setIsRunning] = useState(false);
  const [results, setResults] = useState({
    download: 0,
    upload: 0,
    ping: 0,
    jitter: 0,
  });
  const [history, setHistory] = useState([]);
  const [error, setError] = useState(null);

  // Gauge animation progress
  const [progress, setProgress] = useState(0);
  const [currentPhase, setCurrentPhase] = useState('idle'); // idle, ping, download, upload

  useEffect(() => {
    fetchHistory();
  }, []);

  const fetchHistory = async () => {
    try {
      const res = await speedTestAPI.getHistory({ limit: 10 });
      if (res.data && res.data.data) {
        setHistory(res.data.data.map(item => ({
          time: new Date(item.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }),
          download: item.download_mbps,
          upload: item.upload_mbps,
          ping: item.ping_ms
        })).reverse());
      }
    } catch (err) {
      console.error('Failed to fetch speed test history:', err);
    }
  };

  const runTest = async () => {
    setIsRunning(true);
    setError(null);
    setResults({ download: 0, upload: 0, ping: 0, jitter: 0 });
    setProgress(0);
    setCurrentPhase('ping');

    // Simulate progress while waiting for real results
    const interval = setInterval(() => {
      setProgress(p => {
        if (p < 30) setCurrentPhase('ping');
        else if (p < 70) setCurrentPhase('download');
        else setCurrentPhase('upload');
        return Math.min(p + 2, 95);
      });
    }, 200);

    try {
      const res = await speedTestAPI.runTest();
      clearInterval(interval);
      setProgress(100);
      setCurrentPhase('idle');
      
      if (res.data && res.data.data) {
        setResults({
          download: res.data.data.download_mbps,
          upload: res.data.data.upload_mbps,
          ping: res.data.data.ping_ms,
          jitter: res.data.data.jitter_ms || 0
        });
      }
      fetchHistory();
    } catch (err) {
      clearInterval(interval);
      const isTimeout = err?.code === 'ECONNABORTED' || err?.message?.includes('timeout');
      setError(isTimeout
        ? 'Speed test timed out. The test may still be running on the server — try again in a moment.'
        : `Speed test failed: ${err?.response?.data?.message || err?.message || 'Server error. Is the backend running?'}`
      );
      setCurrentPhase('idle');
      setProgress(0);
    } finally {
      setIsRunning(false);
    }
  };

  return (
    <div className="min-h-screen bg-gradient-to-br from-slate-950 via-slate-900 to-slate-800 p-8 page-shell">
      <div className="max-w-6xl mx-auto">
        <div className="mb-8 flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
          <h1 className="text-4xl font-bold text-white mb-2 flex items-center gap-3">
            <Gauge className="w-10 h-10 text-blue-500" />
            Network Speed Test
          </h1>
          <p className="text-slate-400">Measure local internet performance and latency</p>
        </div>

        {error && (
          <div className="bg-red-900/50 border border-red-500/50 text-red-200 px-4 py-3 rounded-lg mb-8 flex items-center gap-3">
            <AlertTriangle className="w-5 h-5 text-red-400" />
            {error}
          </div>
        )}

        <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
          {/* Main Gauge / Action Area */}
          <div className="lg:col-span-2 bg-slate-800/80 rounded-2xl p-8 border border-slate-700 shadow-xl flex flex-col items-center justify-center relative overflow-hidden backdrop-blur-sm">
            {/* Background animated circles when running */}
            {isRunning && (
              <div className="absolute inset-0 flex items-center justify-center opacity-10 pointer-events-none">
                <div className="w-96 h-96 bg-blue-500 rounded-full animate-ping"></div>
              </div>
            )}
            
            <div className="text-center mb-12 relative z-10">
              <h2 className="text-xl font-medium text-slate-300 mb-2">
                {currentPhase === 'idle' ? 'Ready to Test' : `Testing ${currentPhase}...`}
              </h2>
              {isRunning && (
                <div className="w-64 h-2 bg-slate-700 rounded-full mt-4 overflow-hidden">
                  <div 
                    className="h-full bg-gradient-to-r from-blue-500 to-purple-500 transition-all duration-200" 
                    style={{ width: `${progress}%` }}
                  ></div>
                </div>
              )}
            </div>

            <button
              onClick={runTest}
              disabled={isRunning}
              className={`relative group flex items-center justify-center w-48 h-48 rounded-full border-4 transition-all duration-300 z-10 ${
                isRunning 
                  ? 'border-blue-500/30 bg-blue-900/20 text-blue-400' 
                  : 'border-blue-500 bg-gradient-to-b from-blue-600 to-blue-800 text-white hover:shadow-[0_0_40px_rgba(59,130,246,0.5)] hover:scale-105'
              }`}
            >
              <div className="text-center">
                {isRunning ? (
                  <Activity className="w-12 h-12 mx-auto mb-2 animate-pulse" />
                ) : (
                  <Play className="w-12 h-12 mx-auto mb-2 ml-2" />
                )}
                <span className="text-xl font-bold uppercase tracking-wider">
                  {isRunning ? 'Testing' : 'Start'}
                </span>
              </div>
            </button>
          </div>

          {/* Results Cards */}
          <div className="grid grid-cols-1 gap-4">
            <div className="bg-slate-800/80 rounded-2xl p-6 border border-slate-700 shadow-lg relative overflow-hidden group">
              <div className="absolute top-0 right-0 w-32 h-32 bg-blue-500/5 rounded-full -translate-y-16 translate-x-16 group-hover:bg-blue-500/10 transition-colors"></div>
              <p className="text-slate-400 text-sm font-medium flex items-center gap-2">
                <ArrowDown className="w-4 h-4 text-blue-400" /> Download
              </p>
              <div className="mt-2 flex items-baseline gap-2">
                <span className="text-4xl font-bold text-white">
                  {results.download > 0 ? results.download.toFixed(1) : '--'}
                </span>
                <span className="text-blue-400 font-medium">Mbps</span>
              </div>
            </div>

            <div className="bg-slate-800/80 rounded-2xl p-6 border border-slate-700 shadow-lg relative overflow-hidden group">
              <div className="absolute top-0 right-0 w-32 h-32 bg-purple-500/5 rounded-full -translate-y-16 translate-x-16 group-hover:bg-purple-500/10 transition-colors"></div>
              <p className="text-slate-400 text-sm font-medium flex items-center gap-2">
                <ArrowUp className="w-4 h-4 text-purple-400" /> Upload
              </p>
              <div className="mt-2 flex items-baseline gap-2">
                <span className="text-4xl font-bold text-white">
                  {results.upload > 0 ? results.upload.toFixed(1) : '--'}
                </span>
                <span className="text-purple-400 font-medium">Mbps</span>
              </div>
            </div>

            <div className="bg-slate-800/80 rounded-2xl p-6 border border-slate-700 shadow-lg grid grid-cols-2 gap-4">
              <div>
                <p className="text-slate-400 text-sm font-medium flex items-center gap-2">
                  <Clock className="w-4 h-4 text-green-400" /> Ping
                </p>
                <div className="mt-1 flex items-baseline gap-1">
                  <span className="text-2xl font-bold text-white">
                    {results.ping > 0 ? results.ping.toFixed(0) : '--'}
                  </span>
                  <span className="text-green-400 text-sm">ms</span>
                </div>
              </div>
              <div>
                <p className="text-slate-400 text-sm font-medium flex items-center gap-2">
                  <Zap className="w-4 h-4 text-yellow-400" /> Jitter
                </p>
                <div className="mt-1 flex items-baseline gap-1">
                  <span className="text-2xl font-bold text-white">
                    {results.jitter > 0 ? results.jitter.toFixed(1) : '--'}
                  </span>
                  <span className="text-yellow-400 text-sm">ms</span>
                </div>
              </div>
            </div>
          </div>
        </div>

        {/* History Chart */}
        {history.length > 0 && (
          <div className="mt-8 bg-slate-800/80 rounded-2xl p-8 border border-slate-700 shadow-xl backdrop-blur-sm">
            <h2 className="text-xl font-semibold text-white mb-6">Recent Test History</h2>
            <div className="h-64">
              <ResponsiveContainer width="100%" height="100%">
                <AreaChart data={history} margin={{ top: 10, right: 10, left: -20, bottom: 0 }}>
                  <defs>
                    <linearGradient id="colorDown" x1="0" y1="0" x2="0" y2="1">
                      <stop offset="5%" stopColor="#3b82f6" stopOpacity={0.3}/>
                      <stop offset="95%" stopColor="#3b82f6" stopOpacity={0}/>
                    </linearGradient>
                    <linearGradient id="colorUp" x1="0" y1="0" x2="0" y2="1">
                      <stop offset="5%" stopColor="#a855f7" stopOpacity={0.3}/>
                      <stop offset="95%" stopColor="#a855f7" stopOpacity={0}/>
                    </linearGradient>
                  </defs>
                  <CartesianGrid strokeDasharray="3 3" stroke="#334155" vertical={false} />
                  <XAxis dataKey="time" stroke="#94a3b8" axisLine={false} tickLine={false} />
                  <YAxis stroke="#94a3b8" axisLine={false} tickLine={false} />
                  <Tooltip 
                    contentStyle={{ backgroundColor: '#1e293b', border: '1px solid #475569', borderRadius: '0.5rem' }} 
                    itemStyle={{ color: '#e2e8f0' }}
                  />
                  <Area type="monotone" dataKey="download" name="Download (Mbps)" stroke="#3b82f6" strokeWidth={3} fillOpacity={1} fill="url(#colorDown)" />
                  <Area type="monotone" dataKey="upload" name="Upload (Mbps)" stroke="#a855f7" strokeWidth={3} fillOpacity={1} fill="url(#colorUp)" />
                </AreaChart>
              </ResponsiveContainer>
            </div>
          </div>
        )}
      </div>
    </div>
  );
}
