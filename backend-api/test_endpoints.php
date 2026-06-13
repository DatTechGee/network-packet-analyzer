<?php
$endpoints = [
    'health' => 'http://localhost:8000/api/health',
    'devices' => 'http://localhost:8000/api/devices',
    'traffic_stats' => 'http://localhost:8000/api/traffic/stats',
    'traffic_timeline' => 'http://localhost:8000/api/traffic/timeline',
    'threats' => 'http://localhost:8000/api/threats'
];

foreach ($endpoints as $name => $url) {
    echo "Querying $name ($url)... ";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    if ($err) {
        echo "CURL ERROR: $err\n";
    } else {
        echo "HTTP CODE: $httpCode\n";
        echo "RESPONSE Snippet: " . substr($response, 0, 200) . "\n";
    }
    echo "--------------------------------------------------\n";
}
