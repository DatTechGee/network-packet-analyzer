import React, { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { Activity, Wifi, Shield, BarChart3, Globe2, Radio, ArrowRight, ChevronRight, Zap, Lock, Eye, BookOpen, AlertTriangle, EyeOff, Server } from 'lucide-react';

export default function Welcome() {
  const navigate = useNavigate();
  const [showContent, setShowContent] = useState(false);

  useEffect(() => {
    const timer = setTimeout(() => setShowContent(true), 300);
    return () => clearTimeout(timer);
  }, []);

  const features = [
    {
      icon: <Radio className="w-6 h-6" />,
      title: 'Real-Time Packet Capture',
      description: 'Captures and analyzes network packets in real-time using Wireshark/tshark integration. Monitors all TCP, UDP, DNS, HTTP, and TLS traffic flowing through your network.',
    },
    {
      icon: <Globe2 className="w-6 h-6" />,
      title: 'Website Visit Tracking',
      description: 'Tracks every website visited by devices on your network. Identifies HTTP-only (insecure) sites, blacklisted domains, and suspicious web activity with full domain resolution.',
    },
    {
      icon: <Shield className="w-6 h-6" />,
      title: 'Threat Detection Engine',
      description: 'Automatically detects port scanning, DDoS attempts, ARP spoofing, VPN usage, suspicious DNS queries, and brute-force attacks. Threats are classified by severity level.',
    },
    {
      icon: <Wifi className="w-6 h-6" />,
      title: 'Device Discovery & Tracking',
      description: 'Automatically discovers and tracks all devices connected to your network via ARP, DHCP, and packet analysis. Shows device type, vendor, OS fingerprint, and connection status.',
    },
    {
      icon: <BarChart3 className="w-6 h-6" />,
      title: 'Traffic Analytics & Bandwidth',
      description: 'Provides detailed traffic analytics including bandwidth usage per device, top domains, protocol distribution, and historical traffic trends over time.',
    },
    {
      icon: <Lock className="w-6 h-6" />,
      title: 'Network Topology Map',
      description: 'Visualizes your network topology showing how devices connect to each other and the gateway. Displays real-time online/offline status for every discovered device.',
    },
  ];

  const techStack = [
    { name: 'Python', role: 'Router Agent & Packet Capture' },
    { name: 'PyShark', role: 'Wireshark/Tshark Integration' },
    { name: 'Laravel', role: 'REST API Backend' },
    { name: 'React', role: 'Real-Time Dashboard' },
    { name: 'SQLite', role: 'Traffic & Threat Storage' },
    { name: 'WebSocket', role: 'Live Data Streaming' },
  ];

  const blogPosts = [
    {
      icon: <AlertTriangle className="w-6 h-6" />,
      title: 'Why HTTP Sites Are Dangerous',
      category: 'Network Security',
      date: '2026',
      summary: 'When you visit a website over HTTP (port 80), all data travels in plain text. Anyone on the same network — using tools like Wireshark — can read your passwords, messages, and personal information. HTTPS encrypts this data, making it unreadable to eavesdroppers. This is why our system flags every HTTP connection as a medium-severity threat.',
      details: 'HTTP traffic can be intercepted through Man-in-the-Middle (MITM) attacks. On public Wi-Fi or hotspot networks, attackers can capture unencrypted packets and extract sensitive data. Always look for the padlock icon in your browser — it means HTTPS is active.',
    },
    {
      icon: <EyeOff className="w-6 h-6" />,
      title: 'ARP Spoofing: The Silent Network Attack',
      category: 'Threat Detection',
      date: '2026',
      summary: 'ARP spoofing lets an attacker redirect network traffic through their machine without anyone noticing. By sending fake ARP messages, the attacker impersonates the gateway router. All your internet traffic then flows through their device first, enabling them to intercept, modify, or block your communications.',
      details: 'Our system detects ARP spoofing by monitoring MAC address changes on the gateway. When a device\'s MAC address suddenly changes for the same IP, it triggers a high-severity alert. This is one of the most common attacks on local networks and hotspot environments.',
    },
    {
      icon: <Server className="w-6 h-6" />,
      title: 'Understanding DNS and Domain Blocking',
      category: 'Access Control',
      date: '2026',
      summary: 'DNS (Domain Name System) translates human-readable domain names like google.com into IP addresses that computers use. When our system blocks a domain, it modifies the Windows hosts file to redirect that domain to 127.0.0.1 (localhost), making it unreachable. This is a powerful parental control and security enforcement mechanism.',
      details: 'The hosts file takes priority over DNS servers. By mapping blocked domains to localhost, all requests to those sites fail immediately. Our router agent fetches the blocked list from the backend and enforces it in real-time, creating a site_blocked threat whenever a blocked domain is accessed.',
    },
    {
      icon: <Shield className="w-6 h-6" />,
      title: 'Network Monitoring Best Practices',
      category: 'Best Practices',
      date: '2026',
      summary: 'Effective network monitoring requires a layered approach. Capture packets at the gateway level, analyze traffic patterns for anomalies, maintain an updated blacklist of known malicious domains, and keep device inventories current. Real-time alerting ensures you respond to threats within seconds, not hours.',
      details: 'Key practices: 1) Monitor all port 80 traffic for insecure connections. 2) Track device MAC addresses to detect unauthorized devices. 3) Set up bandwidth thresholds to detect DDoS attacks. 4) Maintain DNS logs to audit website visits. 5) Use VLANs to segment sensitive devices from general traffic.',
    },
  ];

  return (
    <div className="min-h-screen bg-gradient-to-br from-slate-950 via-slate-900 to-slate-800">
      {/* Hero Section */}
      <div className="relative overflow-hidden">
        <div className="absolute inset-0 bg-gradient-to-r from-blue-600/10 to-cyan-500/10" />
        <div className="relative max-w-6xl mx-auto px-8 pt-16 pb-20">
          <div className={`text-center transition-all duration-700 ${showContent ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-8'}`}>
            <div className="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-blue-500/10 border border-blue-500/20 text-blue-400 text-sm font-medium mb-6">
              <Activity className="w-4 h-4" />
              G2 Project — Group 2
            </div>
            <h1 className="text-5xl md:text-7xl font-bold text-white mb-6 tracking-tight">
              Network <span className="text-transparent bg-clip-text bg-gradient-to-r from-blue-400 to-cyan-400">Packet Tracer</span>
            </h1>
            <p className="text-xl text-slate-300 max-w-3xl mx-auto mb-4 leading-relaxed">
              A comprehensive real-time network monitoring and threat detection system built for analyzing traffic on Windows-based hotspot networks.
            </p>
            <p className="text-slate-400 max-w-2xl mx-auto mb-10">
              Captures packets via Wireshark, detects threats instantly, tracks every device and website visit on your network — all displayed through a live dashboard with WebSocket streaming.
            </p>
            <button
              onClick={() => navigate('/dashboard')}
              className="group inline-flex items-center gap-3 px-8 py-4 bg-gradient-to-r from-blue-600 to-cyan-600 hover:from-blue-500 hover:to-cyan-500 text-white font-semibold rounded-xl shadow-lg shadow-blue-500/25 transition-all duration-300 hover:shadow-blue-500/40 hover:scale-105"
            >
              Proceed to Dashboard
              <ArrowRight className="w-5 h-5 group-hover:translate-x-1 transition-transform" />
            </button>
          </div>
        </div>
      </div>

      {/* What It Does */}
      <div className={`max-w-6xl mx-auto px-8 py-16 transition-all duration-700 delay-200 ${showContent ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-8'}`}>
        <div className="text-center mb-12">
          <h2 className="text-3xl font-bold text-white mb-3">What This System Does</h2>
          <p className="text-slate-400 max-w-2xl mx-auto">
            Designed as a group project to demonstrate real-world network security monitoring, packet analysis, and threat detection capabilities.
          </p>
        </div>
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
          {features.map((feature, index) => (
            <div
              key={index}
              className="bg-slate-800/50 rounded-xl p-6 border border-slate-700/50 hover:border-blue-500/30 hover:bg-slate-800/80 transition-all duration-300 group"
            >
              <div className="text-blue-400 mb-4 group-hover:text-cyan-400 transition-colors">
                {feature.icon}
              </div>
              <h3 className="text-lg font-semibold text-white mb-2">{feature.title}</h3>
              <p className="text-slate-400 text-sm leading-relaxed">{feature.description}</p>
            </div>
          ))}
        </div>
      </div>

      {/* How It Works */}
      <div className="max-w-6xl mx-auto px-8 py-16">
        <div className="text-center mb-12">
          <h2 className="text-3xl font-bold text-white mb-3">How It Works</h2>
        </div>
        <div className="grid grid-cols-1 md:grid-cols-4 gap-6">
          {[
            { step: '1', title: 'Connect', desc: 'Connect devices to your phone hotspot' },
            { step: '2', title: 'Capture', desc: 'Router agent captures packets via Wireshark' },
            { step: '3', title: 'Analyze', desc: 'Backend analyzes traffic and detects threats' },
            { step: '4', title: 'Monitor', desc: 'Dashboard shows everything in real-time' },
          ].map((item, index) => (
            <div key={index} className="text-center relative">
              <div className="w-12 h-12 rounded-full bg-blue-600 text-white font-bold text-lg flex items-center justify-center mx-auto mb-4">
                {item.step}
              </div>
              <h3 className="text-white font-semibold mb-2">{item.title}</h3>
              <p className="text-slate-400 text-sm">{item.desc}</p>
              {index < 3 && (
                <ChevronRight className="hidden md:block w-6 h-6 text-slate-600 absolute top-4 -right-3" />
              )}
            </div>
          ))}
        </div>
      </div>

      {/* Tech Stack */}
      <div className="max-w-6xl mx-auto px-8 py-16">
        <div className="text-center mb-12">
          <h2 className="text-3xl font-bold text-white mb-3">Technology Stack</h2>
          <p className="text-slate-400">Built with modern tools for real-time network analysis</p>
        </div>
        <div className="grid grid-cols-2 md:grid-cols-3 gap-4">
          {techStack.map((tech, index) => (
            <div key={index} className="bg-slate-800/50 rounded-lg p-4 border border-slate-700/50 hover:border-blue-500/20 transition-colors">
              <p className="text-white font-semibold">{tech.name}</p>
              <p className="text-slate-400 text-sm mt-1">{tech.role}</p>
            </div>
          ))}
        </div>
      </div>

      {/* Security Blog */}
      <div className="max-w-6xl mx-auto px-8 py-16">
        <div className="text-center mb-12">
          <div className="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-cyan-500/10 border border-cyan-500/20 text-cyan-400 text-sm font-medium mb-4">
            <BookOpen className="w-4 h-4" />
            Security Knowledge Base
          </div>
          <h2 className="text-3xl font-bold text-white mb-3">Network Security Insights</h2>
          <p className="text-slate-400 max-w-2xl mx-auto">
            Learn about common network threats, how they work, and why monitoring matters.
          </p>
        </div>
        <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
          {blogPosts.map((post, index) => (
            <div
              key={index}
              className="bg-slate-800/50 rounded-xl p-6 border border-slate-700/50 hover:border-cyan-500/30 hover:bg-slate-800/80 transition-all duration-300 group"
            >
              <div className="flex items-start gap-4 mb-4">
                <div className="text-cyan-400 mt-1 group-hover:text-blue-400 transition-colors">
                  {post.icon}
                </div>
                <div className="flex-1">
                  <div className="flex items-center gap-2 mb-1">
                    <span className="px-2 py-0.5 bg-cyan-500/10 text-cyan-400 text-xs rounded-full font-medium">{post.category}</span>
                    <span className="text-slate-500 text-xs">{post.date}</span>
                  </div>
                  <h3 className="text-lg font-semibold text-white">{post.title}</h3>
                </div>
              </div>
              <p className="text-slate-300 text-sm leading-relaxed mb-3">{post.summary}</p>
              <p className="text-slate-400 text-xs leading-relaxed border-t border-slate-700/50 pt-3">{post.details}</p>
            </div>
          ))}
        </div>
      </div>

      {/* CTA */}
      <div className="max-w-6xl mx-auto px-8 py-16 text-center">
        <div className="bg-gradient-to-r from-blue-600/10 to-cyan-600/10 rounded-2xl p-12 border border-blue-500/20">
          <Zap className="w-10 h-10 text-blue-400 mx-auto mb-4" />
          <h2 className="text-2xl font-bold text-white mb-3">Ready to Monitor Your Network?</h2>
          <p className="text-slate-400 mb-6 max-w-lg mx-auto">
            Start the router agent on your Windows machine and watch your network traffic come alive on the dashboard.
          </p>
          <button
            onClick={() => navigate('/dashboard')}
            className="group inline-flex items-center gap-2 px-8 py-3 bg-blue-600 hover:bg-blue-500 text-white font-semibold rounded-xl transition-all duration-300"
          >
            <Eye className="w-5 h-5" />
            Open Dashboard
            <ArrowRight className="w-4 h-4 group-hover:translate-x-1 transition-transform" />
          </button>
        </div>
      </div>

      {/* Footer */}
      <div className="max-w-6xl mx-auto px-8 py-8 border-t border-slate-800">
        <div className="flex flex-col md:flex-row items-center justify-between gap-4">
          <div className="flex items-center gap-3">
            <div className="w-8 h-8 bg-gradient-to-br from-blue-500 to-cyan-400 rounded-lg flex items-center justify-center">
              <Activity className="w-4 h-4 text-white" />
            </div>
            <span className="text-white font-semibold">G2 Project</span>
          </div>
          <p className="text-slate-500 text-sm">Group 2 — Network Packet Tracer</p>
        </div>
      </div>
    </div>
  );
}
