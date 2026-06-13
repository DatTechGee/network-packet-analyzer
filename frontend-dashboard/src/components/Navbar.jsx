import React, { useState, useEffect } from 'react';
import { Menu, X, BarChart3, Shield, Settings, Home, Server, Radio, GitBranch, FileText, Bell, Activity, Gauge, ChevronRight, ChevronLeft, PanelLeft } from 'lucide-react';
import { Link, useLocation } from 'react-router-dom';
import { alertAPI } from '../services/api';

export default function Navbar({ collapsed, onToggle }) {
  const [mobileOpen, setMobileOpen] = useState(false);
  const [unreadCount, setUnreadCount] = useState(0);
  const location = useLocation();

  const links = [
    { name: 'Alerts', href: '/alerts', icon: Bell, badge: unreadCount },
    { name: 'Dashboard', href: '/dashboard', icon: Home },
    { name: 'Devices', href: '/devices', icon: Server },
    { name: 'Live Packets', href: '/live-packets', icon: Radio },
    { name: 'Topology', href: '/topology', icon: GitBranch },
    { name: 'Analytics', href: '/analytics', icon: BarChart3 },
    { name: 'Reports', href: '/reports', icon: FileText },
    { name: 'Security', href: '/security', icon: Shield },
    { name: 'Speed Test', href: '/speed-test', icon: Gauge },
    { name: 'Settings', href: '/settings', icon: Settings },
  ];

  const isActiveLink = (href) => href === '/' ? location.pathname === '/' : location.pathname.startsWith(href);

  useEffect(() => {
    fetchAlerts();
    const interval = setInterval(fetchAlerts, 10000);
    return () => clearInterval(interval);
  }, []);

  useEffect(() => {
    setMobileOpen(false);
  }, [location.pathname]);

  const fetchAlerts = async () => {
    try {
      const res = await alertAPI.getAlerts({ limit: 10 });
      setUnreadCount(Number(res.data?.unread_count || 0));
    } catch (err) {
      console.error('Failed to fetch alerts', err);
    }
  };

  return (
    <>
      <aside className={`hidden md:flex fixed inset-y-0 left-0 z-40 flex-col border-r border-slate-800 bg-slate-950/95 backdrop-blur-xl transition-all duration-300 ${collapsed ? 'w-20' : 'w-72'}`}>
        <div className={`flex items-center border-b border-slate-800 ${collapsed ? 'justify-center px-2 py-5' : 'gap-3 px-6 py-6'}`}>
          <div className="w-11 h-11 bg-gradient-to-br from-blue-500 to-cyan-400 rounded-xl flex items-center justify-center shadow-lg shadow-blue-500/20 shrink-0">
            <Activity className="w-6 h-6 text-white" />
          </div>
          {!collapsed && (
            <div className="overflow-hidden">
              <h1 className="text-lg font-bold text-white tracking-tight truncate">G2 Project</h1>
              <p className="text-xs text-slate-400 truncate">Packet Tracer</p>
            </div>
          )}
        </div>

        <nav className="flex-1 px-2 py-5 space-y-1 overflow-y-auto">
          {links.map((link) => {
            const Icon = link.icon;
            const active = isActiveLink(link.href);

            return (
              <Link
                key={link.name}
                to={link.href}
                className={`flex items-center rounded-xl px-3 py-3 text-sm font-medium transition-colors ${
                  collapsed ? 'justify-center' : 'gap-3 px-4'
                } ${
                  active
                    ? 'bg-blue-500/15 text-white ring-1 ring-blue-500/25'
                    : 'text-slate-400 hover:bg-slate-800/80 hover:text-white'
                }`}
                title={link.name}
              >
                <Icon className="w-5 h-5 shrink-0" />
                {!collapsed && <span className="flex-1 truncate">{link.name}</span>}
                {!collapsed && typeof link.badge === 'number' && link.badge > 0 && (
                  <span className="rounded-full bg-red-500/20 px-2 py-0.5 text-[11px] font-semibold text-red-300">
                    {link.badge}
                  </span>
                )}
                {!collapsed && active && <ChevronRight className="w-4 h-4 text-blue-300 shrink-0" />}
              </Link>
            );
          })}
        </nav>

        <div className="border-t border-slate-800 p-3 flex justify-center">
          <button
            onClick={onToggle}
            className="rounded-lg p-2 text-slate-400 hover:bg-slate-800 hover:text-white transition-colors"
            title={collapsed ? 'Expand sidebar' : 'Collapse sidebar'}
          >
            {collapsed ? <PanelLeft className="w-5 h-5" /> : <ChevronLeft className="w-5 h-5" />}
          </button>
        </div>
      </aside>

      <header className="sticky top-0 z-50 md:hidden border-b border-slate-800 bg-slate-950/95 backdrop-blur-xl">
        <div className="flex items-center justify-between px-4 py-3">
          <div className="flex items-center gap-3">
            <div className="w-10 h-10 bg-gradient-to-br from-blue-500 to-cyan-400 rounded-xl flex items-center justify-center shadow-lg shadow-blue-500/20">
              <Activity className="w-5 h-5 text-white" />
            </div>
            <div>
              <h1 className="text-base font-bold text-white">G2 Project</h1>
              <p className="text-[11px] text-slate-400">Packet Tracer</p>
            </div>
          </div>

          <div className="flex items-center gap-2">
            <Link
              to="/alerts"
              className="relative rounded-lg p-2 text-slate-400 hover:bg-slate-800 hover:text-white"
              title="Alerts"
            >
              <Bell className="w-5 h-5" />
              {unreadCount > 0 && <span className="absolute right-1 top-1 h-2 w-2 rounded-full bg-red-500" />}
            </Link>
            <button
              onClick={() => setMobileOpen(!mobileOpen)}
              className="rounded-lg p-2 text-slate-400 hover:bg-slate-800 hover:text-white"
            >
              {mobileOpen ? <X className="w-6 h-6" /> : <Menu className="w-6 h-6" />}
            </button>
          </div>
        </div>

        {mobileOpen && (
          <div className="border-t border-slate-800 bg-slate-950 px-4 py-3">
            <nav className="space-y-1">
              {links.map((link) => {
                const Icon = link.icon;
                const active = isActiveLink(link.href);

                return (
                  <Link
                    key={link.name}
                    to={link.href}
                    className={`flex items-center gap-3 rounded-xl px-4 py-3 text-sm font-medium transition-colors ${
                      active
                        ? 'bg-blue-500/15 text-white ring-1 ring-blue-500/25'
                        : 'text-slate-400 hover:bg-slate-800/80 hover:text-white'
                    }`}
                  >
                    <Icon className="w-4 h-4" />
                    <span className="flex-1">{link.name}</span>
                    {typeof link.badge === 'number' && link.badge > 0 && (
                      <span className="rounded-full bg-red-500/20 px-2 py-0.5 text-[11px] font-semibold text-red-300">
                        {link.badge}
                      </span>
                    )}
                    {active && <ChevronRight className="w-4 h-4 text-blue-300" />}
                  </Link>
                );
              })}
            </nav>
          </div>
        )}
      </header>
    </>
  );
}
