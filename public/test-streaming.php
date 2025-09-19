<?php
// Local streaming test script for AI responses
header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-cache');
header('X-Accel-Buffering: no'); // Disable Nginx buffering if behind Nginx
header('Connection: keep-alive');

// Disable all forms of output buffering
if (ob_get_level()) {
    ob_end_clean();
}
ini_set('output_buffering', 'off');
ini_set('zlib.output_compression', false);
ini_set('implicit_flush', true);
ob_implicit_flush(true);

echo "Local streaming test started...\n";
flush();

//echo "Environment: " . (isset($_ENV['APP_ENV']) ? $_ENV['APP_ENV'] : 'Production') . "\n";
echo "PHP Version: " . PHP_VERSION . "\n";
echo "Server: " . ($_SERVER['SERVER_SOFTWARE'] ?? 'Unknown') . "\n";
echo "Output Buffering: " . (ini_get('output_buffering') ? 'On (' . ini_get('output_buffering') . ')' : 'Off') . "\n";
echo "Implicit Flush: " . (ini_get('implicit_flush') ? 'On' : 'Off') . "\n";
echo "Zlib Compression: " . (ini_get('zlib.output_compression') ? 'On' : 'Off') . "\n";
echo "\n";
flush();

for ($i = 1; $i <= 10; $i++) {
    echo "Chunk $i: " . date('H:i:s') . " - ";
    echo str_repeat('data ', 10) . "\n";
    flush();
    usleep(500000); // 0.5 seconds
    
    // Check if client disconnected
    if (connection_aborted()) {
        echo "Client disconnected, stopping streaming...\n";
        break;
    }
}

echo "\nStreaming test completed!\n";
echo "Total time: " . number_format(microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'], 2) . " seconds\n";