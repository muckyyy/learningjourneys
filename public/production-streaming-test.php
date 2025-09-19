<?php
/**
 * Production Streaming Diagnostic Tool
 * Use this to identify production-specific streaming issues
 * 
 * Usage: https://your-domain.com/production-streaming-test.php
 */

// Set aggressive anti-buffering headers
while (ob_get_level()) {
    ob_end_clean();
}

// Production streaming headers
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache, no-store, must-revalidate, no-transform');
header('Connection: keep-alive');
header('X-Accel-Buffering: no');
header('X-Output-Buffering: off');
header('Transfer-Encoding: chunked');
header('Pragma: no-cache');
header('Expires: 0');
header('Vary: Accept-Encoding');

// AWS ALB specific
header('X-ALB-Classification-Response: no-cache');

// Force Apache environment variables
if (function_exists('apache_setenv')) {
    apache_setenv('no-gzip', '1');
    apache_setenv('no-brotli', '1');
}

echo "data: " . json_encode([
    'type' => 'diagnostic_start',
    'timestamp' => microtime(true),
    'message' => 'Starting production streaming diagnostic...'
]) . "\n\n";
flush();

// Test 1: Environment detection
echo "data: " . json_encode([
    'type' => 'environment',
    'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
    'php_version' => PHP_VERSION,
    'output_buffering' => ini_get('output_buffering'),
    'zlib_compression' => ini_get('zlib.output_compression'),
    'implicit_flush' => ini_get('implicit_flush'),
    'ob_level' => ob_get_level(),
    'headers_sent' => headers_sent(),
    'connection_status' => connection_status()
]) . "\n\n";
flush();
usleep(100000);

// Test 2: Load balancer detection
$isALB = false;
$isCDN = false;

if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
    $isALB = true;
    echo "data: " . json_encode([
        'type' => 'load_balancer',
        'detected' => true,
        'x_forwarded_for' => $_SERVER['HTTP_X_FORWARDED_FOR'],
        'x_forwarded_proto' => $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
    ]) . "\n\n";
} else {
    echo "data: " . json_encode([
        'type' => 'load_balancer',
        'detected' => false
    ]) . "\n\n";
}
flush();
usleep(100000);

// Test 3: CDN detection
if (isset($_SERVER['HTTP_CLOUDFRONT_VIEWER_COUNTRY']) || 
    isset($_SERVER['HTTP_X_EDGE_LOCATION']) ||
    strpos($_SERVER['HTTP_USER_AGENT'] ?? '', 'CloudFront') !== false) {
    $isCDN = true;
    echo "data: " . json_encode([
        'type' => 'cdn',
        'detected' => true,
        'headers' => array_filter($_SERVER, function($key) {
            return strpos($key, 'HTTP_CLOUDFRONT') === 0 || 
                   strpos($key, 'HTTP_X_EDGE') === 0;
        }, ARRAY_FILTER_USE_KEY)
    ]) . "\n\n";
} else {
    echo "data: " . json_encode([
        'type' => 'cdn',
        'detected' => false
    ]) . "\n\n";
}
flush();
usleep(100000);

// Test 4: Streaming test with different chunk sizes
$testMessage = "This is a streaming test message that will be sent in different chunk sizes to test buffering behavior in production environment.";

echo "data: " . json_encode([
    'type' => 'streaming_test_start',
    'message' => 'Testing different chunk sizes...'
]) . "\n\n";
flush();
usleep(100000);

// Small chunks (should stream immediately)
$chunks = str_split($testMessage, 5);
foreach ($chunks as $index => $chunk) {
    echo "data: " . json_encode([
        'type' => 'small_chunk',
        'chunk' => $chunk,
        'index' => $index,
        'timestamp' => microtime(true)
    ]) . "\n\n";
    flush();
    usleep(50000); // 0.05 seconds
}

usleep(200000);

// Medium chunks
$chunks = str_split($testMessage, 15);
foreach ($chunks as $index => $chunk) {
    echo "data: " . json_encode([
        'type' => 'medium_chunk',
        'chunk' => $chunk,
        'index' => $index,
        'timestamp' => microtime(true)
    ]) . "\n\n";
    flush();
    usleep(100000); // 0.1 seconds
}

usleep(200000);

// Test 5: Buffer size detection
echo "data: " . json_encode([
    'type' => 'buffer_test',
    'message' => 'Testing minimum buffer size that triggers flush...'
]) . "\n\n";
flush();

$testSizes = [100, 500, 1024, 2048, 4096, 8192];
foreach ($testSizes as $size) {
    $testData = str_repeat('A', $size);
    $startTime = microtime(true);
    
    echo "data: " . json_encode([
        'type' => 'buffer_size_test',
        'size' => $size,
        'data' => substr($testData, 0, 50) . '...',
        'timestamp' => $startTime
    ]) . "\n\n";
    flush();
    
    usleep(100000);
}

// Test 6: Connection persistence
echo "data: " . json_encode([
    'type' => 'connection_test',
    'message' => 'Testing connection persistence...',
    'connection_status' => connection_status(),
    'headers_sent' => headers_sent()
]) . "\n\n";
flush();

// Final recommendations
$recommendations = [];

if ($isALB) {
    $recommendations[] = "Application Load Balancer detected - ensure ALB timeout > 300s";
}

if ($isCDN) {
    $recommendations[] = "CDN detected - disable caching for streaming endpoints";
}

if (ini_get('output_buffering') && ini_get('output_buffering') != 'Off') {
    $recommendations[] = "PHP output buffering is ON - disable for streaming";
}

if (ini_get('zlib.output_compression')) {
    $recommendations[] = "PHP zlib compression is ON - disable for streaming";
}

echo "data: " . json_encode([
    'type' => 'final_report',
    'environment_issues' => [
        'alb_detected' => $isALB,
        'cdn_detected' => $isCDN,
        'php_buffering' => ini_get('output_buffering'),
        'zlib_compression' => ini_get('zlib.output_compression')
    ],
    'recommendations' => $recommendations,
    'timestamp' => microtime(true)
]) . "\n\n";
flush();

echo "data: " . json_encode(['type' => 'done']) . "\n\n";
flush();
?>