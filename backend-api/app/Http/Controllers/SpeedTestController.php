<?php

namespace App\Http\Controllers;

use App\Models\SpeedTestResult;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SpeedTestController extends Controller
{
    /**
     * Latency ping test
     */
    public function ping(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'timestamp' => microtime(true) * 1000,
        ]);
    }

    /**
     * Download speed test (streams ~5MB of zeroed data)
     */
    public function download(): StreamedResponse
    {
        return response()->stream(function () {
            $chunkSize = 65536; // 64KB chunks for better throughput
            $totalSize = 5 * 1024 * 1024; // 5MB
            $bytesSent = 0;
            $data = str_repeat("\0", $chunkSize);

            if (ob_get_level()) {
                ob_end_clean();
            }

            while ($bytesSent < $totalSize) {
                echo $data;
                $bytesSent += $chunkSize;
                flush();
            }
        }, 200, [
            'Content-Type' => 'application/octet-stream',
            'Content-Length' => 5 * 1024 * 1024,
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
            'Access-Control-Allow-Origin' => '*',
        ]);
    }

    /**
     * Upload speed test - receives data and measures throughput
     */
    public function upload(Request $request): JsonResponse
    {
        $startTime = $request->input('start_time', microtime(true) * 1000);
        $endTime = microtime(true) * 1000;

        return response()->json([
            'success' => true,
            'bytes_received' => strlen($request->getContent()),
            'elapsed_ms' => max(1.0, $endTime - $startTime),
        ]);
    }

    /**
     * Full speed test: measures ping, download, and upload without self-HTTP-calls.
     * Uses in-process measurements to avoid deadlocking the single-threaded dev server.
     */
    public function run(): JsonResponse
    {
        @set_time_limit(120);

        try {
            // --- 1. Ping: TCP connect to well-known hosts ---
            $pingHosts = ['8.8.8.8', '1.1.1.1', 'google.com'];
            $pingResults = [];
            foreach ($pingHosts as $host) {
                for ($i = 0; $i < 3; $i++) {
                    $t0 = microtime(true);
                    $fp = @fsockopen('tcp://' . $host, 443, $errno, $errstr, 3);
                    if ($fp) {
                        fclose($fp);
                        $pingResults[] = (microtime(true) - $t0) * 1000;
                    }
                }
                if (count($pingResults) >= 5) break;
            }

            // Fallback: HTTP ping if TCP is blocked
            if (empty($pingResults)) {
                for ($i = 0; $i < 5; $i++) {
                    $t0 = microtime(true);
                    $ctx = stream_context_create(['http' => [
                        'timeout'       => 5,
                        'method'        => 'HEAD',
                        'ignore_errors' => true,
                    ]]);
                    @file_get_contents('https://www.google.com/', false, $ctx);
                    $pingResults[] = (microtime(true) - $t0) * 1000;
                }
            }

            sort($pingResults);
            $trimmed  = array_slice($pingResults, 0, max(1, (int) ceil(count($pingResults) * 0.8)));
            $pingMs   = round(array_sum($trimmed) / max(count($trimmed), 1), 1);
            $jitterMs = count($pingResults) > 1
                ? round(max($pingResults) - min($pingResults), 1)
                : 0.0;

            // --- 2. Download speed: measure time to fetch a known payload from the internet ---
            //     We pull ~1MB from a CDN (Cloudflare speed test file) to get real bandwidth.
            $dlTargets = [
                'https://speed.cloudflare.com/__down?bytes=1048576',
                'https://httpbin.org/bytes/1048576',
            ];
            $dlBytes   = 0;
            $dlElapsed = 0.001;
            foreach ($dlTargets as $dlUrl) {
                $dlCtx = stream_context_create(['http' => [
                    'timeout'       => 30,
                    'method'        => 'GET',
                    'ignore_errors' => true,
                ]]);
                $dlStart = microtime(true);
                $dlData  = @file_get_contents($dlUrl, false, $dlCtx);
                $dlElapsed = max(0.001, microtime(true) - $dlStart);
                $dlBytes   = strlen($dlData ?: '');
                if ($dlBytes > 100_000) break; // got a good sample, stop
            }
            // Mbps = bytes / seconds / 125000
            $downloadMbps = $dlBytes > 0
                ? round(($dlBytes / $dlElapsed) / 125_000, 2)
                : 0.0;

            // --- 3. Upload speed: push 1MB to httpbin (or our own endpoint as fallback) ---
            $uploadPayload = str_repeat('X', 1 * 1024 * 1024); // 1MB
            $ulElapsed     = 0.001;
            $ulBytes       = strlen($uploadPayload);

            $ulTargets = [
                'https://httpbin.org/post',
            ];
            $ulSuccess = false;
            foreach ($ulTargets as $ulUrl) {
                $ulCtx = stream_context_create(['http' => [
                    'timeout'       => 30,
                    'method'        => 'POST',
                    'header'        => "Content-Type: application/octet-stream\r\nContent-Length: " . $ulBytes . "\r\n",
                    'content'       => $uploadPayload,
                    'ignore_errors' => true,
                ]]);
                $ulStart = microtime(true);
                $ulResp  = @file_get_contents($ulUrl, false, $ulCtx);
                $ulElapsed = max(0.001, microtime(true) - $ulStart);
                if ($ulResp !== false) {
                    $ulSuccess = true;
                    break;
                }
            }

            // If external upload is blocked, estimate from download speed (typical ratio)
            $uploadMbps = $ulSuccess
                ? round(($ulBytes / $ulElapsed) / 125_000, 2)
                : round($downloadMbps * 0.4, 2); // estimate ~40% of download

            // --- Save to DB ---
            $result = SpeedTestResult::create([
                'download_mbps' => $downloadMbps,
                'upload_mbps'   => $uploadMbps,
                'ping_ms'       => $pingMs,
                'jitter_ms'     => $jitterMs,
                'server'        => 'Cloudflare / httpbin.org',
                'isp'           => gethostname() ?: 'Unknown',
                'metadata'      => [
                    'dl_bytes'     => $dlBytes,
                    'dl_elapsed'   => round($dlElapsed, 3),
                    'ul_bytes'     => $ulBytes,
                    'ul_elapsed'   => round($ulElapsed, 3),
                    'ul_estimated' => !$ulSuccess,
                    'ping_samples' => $pingResults,
                ],
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'download_mbps' => $downloadMbps,
                    'upload_mbps'   => $uploadMbps,
                    'ping_ms'       => $pingMs,
                    'jitter_ms'     => $jitterMs,
                    'server'        => $result->server,
                    'isp'           => $result->isp,
                    'tested_at'     => $result->created_at->toIso8601String(),
                ],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Speed test failed: ' . $e->getMessage(),
            ], 500);
        }
    }


    /**
     * Get speed test history
     */
    public function history(Request $request): JsonResponse
    {
        $limit = max(1, min(50, (int) $request->query('limit', 10)));

        $results = SpeedTestResult::orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(fn ($r) => [
                'id'            => $r->id,
                'download_mbps' => $r->download_mbps,
                'upload_mbps'   => $r->upload_mbps,
                'ping_ms'       => $r->ping_ms,
                'jitter_ms'     => $r->jitter_ms,
                'server'        => $r->server,
                'isp'           => $r->isp,
                'created_at'    => $r->created_at?->toIso8601String(),
            ]);

        return response()->json([
            'success' => true,
            'data'    => $results,
        ]);
    }
}
