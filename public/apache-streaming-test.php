<?php
/**
 * Apache Streaming Test for Production Environment
 * Tests if Apache is properly configured for streaming responses
 */

// Prevent any PHP output buffering
while (ob_get_level()) {
    ob_end_flush();
}

// Set headers for streaming
header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
header('X-Accel-Buffering: no'); // Disable Nginx buffering (if behind proxy)
header('X-Output-Buffering: off'); // Custom header for debugging

// Disable compression if somehow still enabled
if (function_exists('apache_setenv')) {
    apache_setenv('no-gzip', '1');
    apache_setenv('no-brotli', '1');
}

// Force immediate output
if (function_exists('fastcgi_finish_request')) {
    // We'll use this later for testing
}

echo "=== Apache Streaming Configuration Test ===\n";
echo "Timestamp: " . date('Y-m-d H:i:s T') . "\n\n";

// Test 1: Basic streaming capability
echo "=== Test 1: Basic Streaming Test ===\n";
echo "This should appear immediately, followed by chunks with 1-second delays:\n\n";

flush();

for ($i = 1; $i <= 5; $i++) {
    $start_time = microtime(true);
    echo "Chunk $i/5 - " . date('H:i:s') . " - ";
    
    // Add some content to make the chunk more substantial
    echo str_repeat("data-$i ", 10) . "\n";
    
    flush();
    
    if ($i < 5) {
        sleep(1);
    }
    
    $end_time = microtime(true);
    $processing_time = round(($end_time - $start_time) * 1000, 2);
    echo "  (Processing time: {$processing_time}ms)\n";
    flush();
}

echo "\n=== Test 2: Apache Environment Analysis ===\n";

// Check Apache modules and configuration
echo "Server Software: " . ($_SERVER['SERVER_SOFTWARE'] ?? 'Unknown') . "\n";
echo "Request Method: " . ($_SERVER['REQUEST_METHOD'] ?? 'Unknown') . "\n";
echo "HTTP Protocol: " . ($_SERVER['SERVER_PROTOCOL'] ?? 'Unknown') . "\n";

// Check for compression-related headers
echo "\nHTTP Headers Analysis:\n";
$headers_to_check = [
    'HTTP_ACCEPT_ENCODING',
    'HTTP_CONTENT_ENCODING', 
    'HTTP_TRANSFER_ENCODING',
    'HTTP_CONNECTION',
    'HTTP_CACHE_CONTROL'
];

foreach ($headers_to_check as $header) {
    $value = $_SERVER[$header] ?? 'Not Set';
    echo "  $header: $value\n";
}

// Check Apache-specific environment variables
echo "\nApache Environment Variables:\n";
$apache_vars = [
    'REDIRECT_STATUS',
    'REDIRECT_URL',
    'SERVER_ADDR',
    'SERVER_PORT',
    'HTTPS',
    'REQUEST_SCHEME'
];

foreach ($apache_vars as $var) {
    $value = $_SERVER[$var] ?? 'Not Set';
    echo "  $var: $value\n";
}

flush();

echo "\n=== Test 3: PHP-FPM Integration Test ===\n";

// Test if we're running under PHP-FPM
echo "SAPI: " . php_sapi_name() . "\n";
echo "PHP Interface: " . (php_sapi_name() === 'fpm-fcgi' ? 'PHP-FPM' : 'Other') . "\n";

// Check FastCGI specific functions
echo "FastCGI Functions Available:\n";
echo "  fastcgi_finish_request(): " . (function_exists('fastcgi_finish_request') ? 'Yes' : 'No') . "\n";

flush();

echo "\n=== Test 4: Output Buffer Deep Analysis ===\n";

echo "Current output buffer level: " . ob_get_level() . "\n";

if (ob_get_level() > 0) {
    echo "Active output handlers:\n";
    $handlers = ob_list_handlers();
    foreach ($handlers as $handler) {
        echo "  - $handler\n";
    }
    
    echo "Buffer contents length: " . strlen(ob_get_contents()) . " bytes\n";
    echo "Buffer status:\n";
    $status = ob_get_status(true);
    foreach ($status as $i => $buffer) {
        echo "  Level $i:\n";
        if (is_array($buffer)) {
            foreach ($buffer as $key => $value) {
                echo "    $key: $value\n";
            }
        } else {
            echo "    Buffer info: " . print_r($buffer, true) . "\n";
        }
    }
}

flush();

echo "\n=== Test 5: Real-time Streaming with Timestamps ===\n";
echo "Each chunk should appear immediately (watch your browser/console for real-time updates):\n\n";

for ($i = 1; $i <= 10; $i++) {
    $timestamp = date('H:i:s.') . substr(microtime(), 2, 3);
    $message = "Real-time chunk #$i at $timestamp";
    
    echo str_pad($message, 60, '.') . " SENT\n";
    
    // Force immediate flush
    if (function_exists('fastcgi_finish_request') && $i === 5) {
        echo "Testing fastcgi_finish_request() at chunk 5...\n";
        fastcgi_finish_request();
        echo "After fastcgi_finish_request() - this should be immediate\n";
    }
    
    flush();
    
    if ($i < 10) {
        usleep(500000); // 0.5 second delay
    }
}

echo "\n=== Test 6: Apache Module Detection ===\n";

// Check if mod_deflate is active
echo "Checking for compression modules:\n";

if (function_exists('apache_get_modules')) {
    $modules = apache_get_modules();
    $compression_modules = ['mod_deflate', 'mod_gzip', 'mod_brotli'];
    
    foreach ($compression_modules as $module) {
        $status = in_array($module, $modules) ? 'LOADED' : 'Not Loaded';
        echo "  $module: $status\n";
        
        if ($status === 'LOADED') {
            echo "    ⚠ WARNING: This module can cause streaming issues\n";
        }
    }
    
    // Check for other relevant modules
    $streaming_modules = ['mod_proxy', 'mod_proxy_fcgi', 'mod_http2'];
    echo "\nStreaming-related modules:\n";
    foreach ($streaming_modules as $module) {
        $status = in_array($module, $modules) ? 'LOADED' : 'Not Loaded';
        echo "  $module: $status\n";
    }
} else {
    echo "apache_get_modules() not available - cannot check loaded modules\n";
}

flush();

echo "\n=== Test 7: HTTP/2 and Connection Analysis ===\n";

echo "Connection Details:\n";
echo "  Protocol: " . ($_SERVER['SERVER_PROTOCOL'] ?? 'Unknown') . "\n";
echo "  Connection: " . ($_SERVER['HTTP_CONNECTION'] ?? 'Unknown') . "\n";
echo "  Upgrade: " . ($_SERVER['HTTP_UPGRADE'] ?? 'Not Set') . "\n";

// Check if HTTP/2 is being used
if (isset($_SERVER['SERVER_PROTOCOL']) && strpos($_SERVER['SERVER_PROTOCOL'], '2') !== false) {
    echo "  ⚠ HTTP/2 detected - may cause streaming buffering\n";
} else {
    echo "  ✓ HTTP/1.1 - good for streaming\n";
}

flush();

echo "\n=== Test 8: Content-Length vs Transfer-Encoding ===\n";

// We should NOT be sending Content-Length for streaming responses
echo "Response Headers Analysis:\n";

// Check if Content-Length is being set (bad for streaming)
$headers = headers_list();
foreach ($headers as $header) {
    echo "  $header\n";
    
    if (stripos($header, 'content-length') !== false) {
        echo "    ⚠ WARNING: Content-Length header detected - will prevent streaming\n";
    }
    
    if (stripos($header, 'transfer-encoding') !== false) {
        echo "    ✓ Transfer-Encoding header detected - good for streaming\n";
    }
    
    if (stripos($header, 'content-encoding') !== false) {
        echo "    ⚠ WARNING: Content-Encoding header detected - compression is active\n";
    }
}

flush();

echo "\n=== Test 9: Final Streaming Verification ===\n";
echo "Final test - 5 rapid chunks (should appear one by one):\n\n";

for ($i = 1; $i <= 5; $i++) {
    echo "FINAL-CHUNK-$i ";
    flush();
    usleep(200000); // 0.2 seconds
}

echo "\n\n=== Test Results Summary ===\n";

// Provide recommendations based on findings
echo "Streaming Configuration Assessment:\n";

$issues = [];
$recommendations = [];

// Check for common streaming problems
if (ob_get_level() > 0) {
    $issues[] = "Output buffering is still active at PHP level";
    $recommendations[] = "Check php.ini and PHP-FPM pool configuration";
}

if (function_exists('apache_get_modules') && in_array('mod_deflate', apache_get_modules())) {
    $issues[] = "mod_deflate is loaded and may be compressing output";
    $recommendations[] = "Ensure Apache virtual host disables compression for streaming endpoints";
}

if (isset($_SERVER['SERVER_PROTOCOL']) && strpos($_SERVER['SERVER_PROTOCOL'], '2') !== false) {
    $issues[] = "HTTP/2 may be causing streaming delays";
    $recommendations[] = "Consider disabling HTTP/2 for streaming endpoints or configure H2Push off";
}

// Check headers for streaming issues
$headers_sent = headers_list();
foreach ($headers_sent as $header) {
    if (stripos($header, 'content-length') !== false) {
        $issues[] = "Content-Length header is being sent";
        $recommendations[] = "Remove Content-Length header for streaming responses";
    }
}

if (empty($issues)) {
    echo "✓ No obvious streaming issues detected at Apache level\n";
    echo "✓ Configuration appears optimized for streaming\n";
} else {
    echo "Issues detected:\n";
    foreach ($issues as $issue) {
        echo "  ✗ $issue\n";
    }
    
    echo "\nRecommendations:\n";
    foreach ($recommendations as $rec) {
        echo "  → $rec\n";
    }
}

echo "\n=== Test Complete ===\n";
echo "End timestamp: " . date('Y-m-d H:i:s T') . "\n";
echo "Total execution time: " . round(microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'], 2) . " seconds\n";

// Final flush
flush();
?>