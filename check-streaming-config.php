<?php
/**
 * Streaming Configuration Check for Production Environment
 * Run this script to verify your server is configured for real-time streaming
 */

echo "<h2>Streaming Configuration Check</h2>\n";

// Check PHP configuration
echo "<h3>PHP Configuration</h3>\n";
echo "PHP Version: " . PHP_VERSION . "\n";
echo "Output Buffering: " . (ini_get('output_buffering') ? 'ON (⚠️ May interfere with streaming)' : 'OFF (✅ Good for streaming)') . "\n";
echo "Max Execution Time: " . ini_get('max_execution_time') . " seconds\n";
echo "Memory Limit: " . ini_get('memory_limit') . "\n";
echo "Zlib Output Compression: " . (ini_get('zlib.output_compression') ? 'ON (⚠️ May interfere)' : 'OFF (✅ Good)') . "\n";
echo "FastCGI available: " . (function_exists('fastcgi_finish_request') ? 'YES (✅)' : 'NO') . "\n";

// Check server headers
echo "\n<h3>Server Environment</h3>\n";
echo "Server Software: " . ($_SERVER['SERVER_SOFTWARE'] ?? 'Unknown') . "\n";
echo "Request Method: " . ($_SERVER['REQUEST_METHOD'] ?? 'Unknown') . "\n";
echo "HTTP Version: " . ($_SERVER['SERVER_PROTOCOL'] ?? 'Unknown') . "\n";

// Test streaming capability
echo "\n<h3>Streaming Test</h3>\n";

// Disable output buffering
while (ob_get_level()) {
    ob_end_clean();
}

// Set streaming headers
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Connection: keep-alive');
header('X-Accel-Buffering: no'); // Nginx
header('X-Output-Buffering: off'); // Apache

echo "data: " . json_encode(['message' => 'Stream test started', 'timestamp' => time()]) . "\n\n";
flush();

// Test with multiple chunks
for ($i = 1; $i <= 5; $i++) {
    echo "data: " . json_encode([
        'message' => "Test chunk {$i}/5",
        'timestamp' => time(),
        'chunk' => $i
    ]) . "\n\n";
    
    if (function_exists('ob_flush')) { @ob_flush(); }
    flush();
    
    usleep(500000); // 0.5 second delay
}

echo "data: " . json_encode(['message' => 'Stream test completed', 'timestamp' => time()]) . "\n\n";
flush();

// Check if we can detect buffering issues
echo "\n<h3>Buffering Detection</h3>\n";
$start_time = microtime(true);
echo "Starting buffering test...\n";
flush();
usleep(1000000); // 1 second
$end_time = microtime(true);
$duration = $end_time - $start_time;

if ($duration < 1.1) {
    echo "✅ Low latency detected - streaming should work well\n";
} else {
    echo "⚠️ High latency detected - may indicate buffering issues\n";
}

echo "\n<h3>Recommendations for Production</h3>\n";
echo "1. Ensure output_buffering is OFF in php.ini\n";
echo "2. Set appropriate max_execution_time (30-60 seconds)\n";
echo "3. Configure web server to disable buffering for streaming endpoints\n";
echo "4. For Nginx: add 'proxy_buffering off;' and 'X-Accel-Buffering: no'\n";
echo "5. For Apache: add 'SetEnv no-gzip' and mod_deflate exclusions\n";
echo "6. Test with: curl -N http://yoursite.com/api/chat/start-web\n";
?>