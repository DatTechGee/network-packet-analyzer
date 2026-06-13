<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ServeWebSockets extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'websockets:serve {--port=6001} {--udp-port=6002}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Starts a lightweight, Windows-compatible WebSocket server to stream packet logs';

    private array $clients = [];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $port = (int) $this->option('port');
        $udpPort = (int) $this->option('udp-port');

        $this->info("Starting TCP WebSocket Server on 0.0.0.0:{$port}...");
        $this->info("Starting UDP Broadcast Listener on 127.0.0.1:{$udpPort}...");

        // Create TCP socket
        $tcpSocket = @socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if (!$tcpSocket) {
            $this->error("Failed to create TCP socket: " . socket_strerror(socket_last_error()));
            return 1;
        }

        @socket_set_option($tcpSocket, SOL_SOCKET, SO_REUSEADDR, 1);
        if (!@socket_bind($tcpSocket, '0.0.0.0', $port)) {
            $this->error("Failed to bind TCP socket to port {$port}: " . socket_strerror(socket_last_error($tcpSocket)));
            return 1;
        }

        if (!@socket_listen($tcpSocket, 10)) {
            $this->error("Failed to listen on TCP socket: " . socket_strerror(socket_last_error($tcpSocket)));
            return 1;
        }

        // Create UDP socket
        $udpSocket = @socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
        if (!$udpSocket) {
            $this->error("Failed to create UDP socket: " . socket_strerror(socket_last_error()));
            return 1;
        }

        @socket_set_option($udpSocket, SOL_SOCKET, SO_REUSEADDR, 1);
        if (!@socket_bind($udpSocket, '127.0.0.1', $udpPort)) {
            $this->error("Failed to bind UDP socket to port {$udpPort}: " . socket_strerror(socket_last_error($udpSocket)));
            return 1;
        }

        $this->info("WebSocket Server is running. Press Ctrl+C to stop.");

        $this->clients = [];

        while (true) {
            $read = array_merge([$tcpSocket, $udpSocket], $this->clients);
            $write = null;
            $except = null;
            $tv_sec = 1;
            $tv_usec = 0;

            $changed = @socket_select($read, $write, $except, $tv_sec, $tv_usec);

            if ($changed === false || $changed === 0) {
                continue;
            }

            // Check for new TCP connection
            if (in_array($tcpSocket, $read)) {
                $newSocket = @socket_accept($tcpSocket);
                if ($newSocket) {
                    $this->performHandshake($newSocket);
                }
                // Remove from read array so we don't process it below
                $key = array_search($tcpSocket, $read);
                unset($read[$key]);
            }

            // Check for UDP packet
            if (in_array($udpSocket, $read)) {
                $buf = '';
                $fromIp = '';
                $fromPort = 0;
                @socket_recvfrom($udpSocket, $buf, 65536, 0, $fromIp, $fromPort);
                
                if (!empty($buf)) {
                    $this->broadcast($buf);
                }
                
                $key = array_search($udpSocket, $read);
                unset($read[$key]);
            }

            // Read from connected clients (disconnect check)
            foreach ($read as $clientSocket) {
                $data = @socket_read($clientSocket, 2048, PHP_BINARY_READ);
                if ($data === false || $data === '') {
                    $this->disconnect($clientSocket);
                }
            }
        }

        @socket_close($tcpSocket);
        @socket_close($udpSocket);

        return 0;
    }

    private function performHandshake($socket)
    {
        $request = @socket_read($socket, 4096);
        if (empty($request)) {
            @socket_close($socket);
            return;
        }

        $headers = [];
        $lines = explode("\r\n", $request);
        foreach ($lines as $line) {
            if (str_contains($line, ':')) {
                [$key, $value] = explode(':', $line, 2);
                $headers[strtolower(trim($key))] = trim($value);
            }
        }

        if (!isset($headers['sec-websocket-key'])) {
            @socket_close($socket);
            return;
        }

        $secKey = $headers['sec-websocket-key'];
        $magic = '258EAFA5-E914-47DA-95CA-C5AB0DC85B11';
        $accept = base64_encode(sha1($secKey . $magic, true));

        $response = "HTTP/1.1 101 Switching Protocols\r\n" .
                    "Upgrade: websocket\r\n" .
                    "Connection: Upgrade\r\n" .
                    "Sec-WebSocket-Accept: $accept\r\n\r\n";

        @socket_write($socket, $response, strlen($response));
        $this->clients[] = $socket;
        $this->info("Client connected. Total clients: " . count($this->clients));
    }

    private function disconnect($socket)
    {
        $key = array_search($socket, $this->clients);
        if ($key !== false) {
            unset($this->clients[$key]);
            $this->clients = array_values($this->clients);
        }
        @socket_close($socket);
        $this->info("Client disconnected. Total clients: " . count($this->clients));
    }

    private function broadcast(string $message)
    {
        if (empty($this->clients)) {
            return;
        }

        $frame = $this->encodeFrame($message);
        foreach ($this->clients as $client) {
            @socket_write($client, $frame, strlen($frame));
        }
    }

    private function encodeFrame(string $payload): string
    {
        $length = strlen($payload);
        
        // Byte 1: FIN (1) + Opcode (1 for Text) = 0x81
        $frame = chr(0x81);

        if ($length <= 125) {
            $frame .= chr($length);
        } elseif ($length <= 65535) {
            $frame .= chr(126) . pack('n', $length);
        } else {
            $frame .= chr(127) . pack('J', $length);
        }

        $frame .= $payload;
        return $frame;
    }
}
