#!/usr/bin/env python3
"""
WebSocket Server for Real-time Network Data Streaming
Broadcasts packets, threats, and device updates to connected clients
"""

import asyncio
import json
import socket
import logging
from typing import Set, Dict, Any
from datetime import datetime, timezone
from aiohttp import web
import threading

logger = logging.getLogger(__name__)


class WebSocketManager:
    """Manages WebSocket connections and message broadcasting"""
    
    def __init__(self):
        self.clients: Set[web.WebSocketResponse] = set()
        self.udp_socket = None
        self.running = False
        
    async def register(self, ws: web.WebSocketResponse):
        """Register a new WebSocket client"""
        self.clients.add(ws)
        logger.info(f"Client connected. Total clients: {len(self.clients)}")
        
    async def unregister(self, ws: web.WebSocketResponse):
        """Unregister a WebSocket client"""
        self.clients.discard(ws)
        logger.info(f"Client disconnected. Total clients: {len(self.clients)}")
        
    async def broadcast(self, message: Dict[str, Any]):
        """Broadcast message to all connected clients"""
        if not self.clients:
            return
            
        data = json.dumps(message)
        disconnected = set()
        
        for ws in self.clients:
            try:
                await ws.send_str(data)
            except Exception as e:
                logger.debug(f"Failed to send to client: {e}")
                disconnected.add(ws)
                
        for ws in disconnected:
            self.clients.discard(ws)
            
    def start_udp_listener(self, port: int = 6002):
        """Start UDP listener for backend broadcasts"""
        self.running = True
        self.udp_socket = socket.socket(socket.AF_INET, socket.SOCK_DGRAM)
        self.udp_socket.bind(('0.0.0.0', port))
        self.udp_socket.settimeout(1.0)
        logger.info(f"UDP listener started on port {port}")
        
        def listen():
            while self.running:
                try:
                    data, addr = self.udp_socket.recvfrom(65536)
                    message = json.loads(data.decode('utf-8'))
                    asyncio.run_coroutine_threadsafe(
                        self.broadcast(message),
                        self.loop
                    )
                except socket.timeout:
                    continue
                except json.JSONDecodeError:
                    continue
                except Exception as e:
                    if self.running:
                        logger.error(f"UDP listener error: {e}")
                        
        self.loop = asyncio.get_event_loop()
        self.udp_thread = threading.Thread(target=listen, daemon=True)
        self.udp_thread.start()
        
    def stop(self):
        """Stop the UDP listener"""
        self.running = False
        if self.udp_socket:
            self.udp_socket.close()


ws_manager = WebSocketManager()


async def websocket_handler(request: web.Request) -> web.WebSocketResponse:
    """Handle WebSocket connections"""
    ws = web.WebSocketResponse()
    await ws.prepare(request)
    
    await ws_manager.register(ws)
    
    try:
        async for msg in ws:
            if msg.type == web.WSMsgType.TEXT:
                try:
                    data = json.loads(msg.data)
                    if data.get('type') == 'ping':
                        await ws.send_str(json.dumps({'type': 'pong', 'timestamp': datetime.now(timezone.utc).isoformat()}))
                except json.JSONDecodeError:
                    pass
            elif msg.type == web.WSMsgType.ERROR:
                logger.error(f"WebSocket error: {ws.exception()}")
    finally:
        await ws_manager.unregister(ws)
        
    return ws


async def health_check(request: web.Request) -> web.Response:
    """Health check endpoint"""
    return web.json_response({
        'status': 'ok',
        'clients': len(ws_manager.clients),
        'timestamp': datetime.now(timezone.utc).isoformat()
    })


async def init_app() -> web.Application:
    """Initialize the web application"""
    app = web.Application()
    app.router.add_get('/ws', websocket_handler)
    app.router.add_get('/health', health_check)
    return app


def run_server(host: str = '0.0.0.0', port: int = 6001):
    """Run the WebSocket server"""
    logging.basicConfig(
        level=logging.INFO,
        format='%(asctime)s - %(name)s - %(levelname)s - %(message)s'
    )
    
    app = asyncio.run(init_app())
    
    # Start UDP listener in background
    async def on_startup(app):
        ws_manager.start_udp_listener(6002)
        
    async def on_shutdown(app):
        ws_manager.stop()
        
    app.on_startup.append(on_startup)
    app.on_shutdown.append(on_shutdown)
    
    logger.info(f"Starting WebSocket server on {host}:{port}")
    web.run_app(app, host=host, port=port)


if __name__ == '__main__':
    run_server()