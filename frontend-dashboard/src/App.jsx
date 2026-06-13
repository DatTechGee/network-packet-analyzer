import React, { useState } from 'react';
import { BrowserRouter as Router, Routes, Route, useLocation } from 'react-router-dom';
import Navbar from './components/Navbar';
import Welcome from './pages/Welcome';
import Dashboard from './pages/Dashboard';
import Analytics from './pages/Analytics';
import SecurityCenter from './pages/SecurityCenter';
import Alerts from './pages/Alerts';
import Settings from './pages/Settings';
import DeviceDetail from './pages/DeviceDetail';
import Devices from './pages/Devices';
import LivePackets from './pages/LivePackets';
import Topology from './pages/Topology';
import Reports from './pages/Reports';
import SpeedTest from './pages/SpeedTest';
import './App.css';

function AppContent() {
  const [sidebarCollapsed, setSidebarCollapsed] = useState(false);
  const location = useLocation();
  const isWelcome = location.pathname === '/';

  if (isWelcome) {
    return (
      <Routes>
        <Route path="/" element={<Welcome />} />
      </Routes>
    );
  }

  return (
    <div className="min-h-screen bg-slate-900 md:flex">
      <Navbar collapsed={sidebarCollapsed} onToggle={() => setSidebarCollapsed((v) => !v)} />
      <main className={`min-h-screen flex-1 min-w-0 transition-all duration-300 ${sidebarCollapsed ? 'md:pl-20' : 'md:pl-72'}`}>
        <Routes>
          <Route path="/" element={<Welcome />} />
          <Route path="/dashboard" element={<Dashboard />} />
          <Route path="/alerts" element={<Alerts />} />
          <Route path="/devices" element={<Devices />} />
          <Route path="/live-packets" element={<LivePackets />} />
          <Route path="/topology" element={<Topology />} />
          <Route path="/device/:deviceId" element={<DeviceDetail />} />
          <Route path="/analytics" element={<Analytics />} />
          <Route path="/reports" element={<Reports />} />
          <Route path="/security" element={<SecurityCenter />} />
          <Route path="/speed-test" element={<SpeedTest />} />
          <Route path="/settings" element={<Settings />} />
        </Routes>
      </main>
    </div>
  );
}

function App() {
  return (
    <Router future={{ v7_startTransition: true, v7_relativeSplatPath: true }}>
      <AppContent />
    </Router>
  );
}

export default App;
