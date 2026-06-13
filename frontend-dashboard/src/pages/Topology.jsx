import React, { useEffect, useMemo, useRef, useState } from 'react';
import ReactFlow, { Background, Controls, MiniMap, Handle, Position, MarkerType } from 'reactflow';
import 'reactflow/dist/style.css';
import { topologyAPI } from '../services/api';
import { GitBranch, Wifi, Radio, Smartphone, Monitor, Tv, Cpu } from 'lucide-react';

const deviceIcons = {
  smartphone: Smartphone,
  mobile: Smartphone,
  phone: Smartphone,
  tablet: Smartphone,
  laptop: Monitor,
  computer: Monitor,
  desktop: Monitor,
  tv: Tv,
  media: Tv,
  iot: Cpu,
  default: Wifi,
};

const NetworkNode = React.memo(function NetworkNode({ data }) {
  const online = data.is_online === true;
  const borderColor = online ? 'border-emerald-500/50' : 'border-slate-600';
  const statusDot = online ? 'bg-emerald-400' : 'bg-red-400';
  const Icon = deviceIcons[data.device_type] || deviceIcons.default;
  return (
    <div className={'rounded-2xl border bg-slate-900 px-4 py-3 shadow-xl min-w-[200px] ' + borderColor}>
      <Handle type="target" position={Position.Top} className="!bg-sky-400" />
      <div className="flex items-center gap-3">
        <Icon className="w-5 h-5 text-sky-400 shrink-0" />
        <div className="min-w-0">
          <div className="text-white font-semibold truncate">{data.label}</div>
          {data.subLabel && <div className="text-slate-400 text-xs">{data.subLabel}</div>}
        </div>
        <span className={'w-2.5 h-2.5 rounded-full shrink-0 ' + statusDot} />
      </div>
      {data.vendor && <div className="text-slate-500 text-xs mt-2 truncate">{data.vendor}</div>}
      <div className="flex gap-2 mt-1.5 text-[10px] text-slate-500">
        {data.device_type && <span className="capitalize">{data.device_type}</span>}
        {data.mac_address && <span className="truncate">{data.mac_address}</span>}
      </div>
      <Handle type="source" position={Position.Bottom} className="!bg-sky-400" />
    </div>
  );
});

const defaultEdgeOptions = {
  style: { stroke: '#64748b', strokeWidth: 2 },
  markerEnd: { type: MarkerType.ArrowClosed, color: '#64748b' },
};

const nodeTypes = { networkNode: NetworkNode };

export default function Topology() {
  const [topology, setTopology] = useState({ nodes: [], edges: [], summary: {} });
  const [loading, setLoading] = useState(true);
  const initialLoadDone = useRef(false);

  useEffect(() => {
    fetchTopology();
    const interval = setInterval(fetchTopology, 5000);
    return () => clearInterval(interval);
  }, []);

  const fetchTopology = async () => {
    try {
      if (!initialLoadDone.current) setLoading(true);
      const response = await topologyAPI.getTopology({ period: '5m' });
      setTopology(response.data.data || { nodes: [], edges: [], summary: {} });
    } catch (error) {
      console.error('Error fetching topology:', error);
    } finally {
      initialLoadDone.current = true;
      setLoading(false);
    }
  };

  const nodes = useMemo(() => topology.nodes.map((node) => ({ ...node, type: 'networkNode' })), [topology.nodes]);

  return (
    <div className="min-h-screen bg-gradient-to-br from-slate-950 via-slate-900 to-slate-800 p-8 page-shell">
      <div className="max-w-7xl mx-auto">
        <div className="mb-8 flex items-end justify-between gap-4 flex-wrap">
          <div>
            <h1 className="text-4xl font-bold text-white mb-2">Network Topology</h1>
            <p className="text-slate-400">Visualize the router, hotspot gateway, and connected devices</p>
          </div>
          <div className="flex gap-3 flex-wrap text-sm text-slate-300">
            <Badge icon={Wifi} label={`${topology.summary?.devices || 0} devices`} />
            <Badge icon={Radio} label={`${topology.summary?.online || 0} online`} />
            <Badge icon={GitBranch} label={topology.summary?.gateway_present ? 'Gateway detected' : 'No gateway'} />
            <Badge icon={Radio} label={topology.summary?.window ? `${topology.summary.window} window` : '5m window'} />
          </div>
        </div>

        <div className="bg-slate-800 rounded-2xl border border-slate-700 overflow-hidden surface-card" style={{ height: '700px' }}>
          {loading && nodes.length === 0 ? (
            <div className="p-8 text-center text-slate-400">Loading topology...</div>
          ) : (
            <ReactFlow nodes={nodes} edges={topology.edges || []} nodeTypes={nodeTypes} defaultEdgeOptions={defaultEdgeOptions} fitView fitViewOptions={{ padding: 0.3 }}>
              <MiniMap pannable zoomable />
              <Controls />
              <Background color="#475569" gap={24} />
            </ReactFlow>
          )}
        </div>
      </div>
    </div>
  );
}

function Badge({ icon: Icon, label }) {
  return (
    <div className="inline-flex items-center gap-2 px-3 py-2 rounded-full bg-slate-800 border border-slate-700">
      <Icon className="w-4 h-4 text-sky-400" />
      <span>{label}</span>
    </div>
  );
}