<?php
$file = 'storage/logs/laravel.log';
if (file_exists($file)) {
    $content = file_get_contents($file);
    $pattern = '/\[2026-\d{2}-\d{2}/';
    preg_match_all($pattern, $content, $matches, PREG_OFFSET_CAPTURE);
    
    if (!empty($matches[0])) {
        $total = count($matches[0]);
        $startIdx = max(0, $total - 5);
        echo "Total logs: $total. Showing last " . ($total - $startIdx) . " logs (first 500 chars):\n\n";
        for ($i = $startIdx; $i < $total; $i++) {
            $offset = $matches[0][$i][1];
            $length = ($i + 1 < $total) ? ($matches[0][$i+1][1] - $offset) : (strlen($content) - $offset);
            $snippet = substr($content, $offset, min($length, 500));
            echo $snippet . "\n... [TRUNCATED] ...\n----------------------------------------\n";
        }
    } else {
        echo "No recent log patterns found.\n";
    }
} else {
    echo "Log file not found.\n";
}
