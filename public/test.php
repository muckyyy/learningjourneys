<?php
/**
 * Advanced PHP Configuration Test for Production Environment
 * Check if streaming optimizations are applied for Amazon Linux 2023 + PHP-FPM
 */

header('Content-Type: text/plain');

echo "=== PHP Streaming Configuration Test (Production Environment) ===\n\n";

// Check critical streaming settings
$settings = [
    'output_buffering' => ini_get('output_buffering'),
    'implicit_flush' => ini_get('implicit_flush'),
    'zlib.output_compression' => ini_get('zlib.output_compression'),
    'output_handler' => ini_get('output_handler'),
    'max_execution_time' => ini_get('max_execution_time'),
    'memory_limit' => ini_get('memory_limit'),
    'fastcgi.logging' => ini_get('fastcgi.logging'),
];

echo "=== Core Streaming Settings ===\n";
foreach ($settings as $setting => $value) {
    $status = '';
    $displayValue = $value === false ? 'false' : ($value === true ? 'true' : $value);
    
    if ($setting === 'output_buffering') {
        $status = ($value == '0' || $value === false || $value === '' || strtolower($value) === 'off') ? '✓ GOOD' : '✗ BAD (should be 0/Off)';
    } elseif ($setting === 'implicit_flush') {
        $status = ($value == '1' || $value === true || strtolower($value) === 'on') ? '✓ GOOD' : '✗ BAD (should be 1/On)';
    } elseif ($setting === 'zlib.output_compression') {
        $status = ($value == '0' || $value === false || $value === '' || strtolower($value) === 'off') ? '✓ GOOD' : '✗ BAD (should be off)';
    } elseif ($setting === 'output_handler') {
        $status = ($value === '' || $value === false) ? '✓ GOOD' : '✗ BAD (should be empty)';
    }
    
    echo sprintf("%-25s: %-15s %s\n", $setting, $displayValue, $status);
}

echo "\n=== PHP-FPM Detection ===\n";
if (function_exists('php_sapi_name') && php_sapi_name() === 'fpm-fcgi') {
    echo "✓ Running under PHP-FPM\n";
} elseif (isset($_SERVER['SERVER_SOFTWARE']) && strpos($_SERVER['SERVER_SOFTWARE'], 'Apache') !== false) {
    echo "✓ Running under Apache (mod_php or PHP-FPM)\n";
} else {
    echo "⚠ SAPI: " . php_sapi_name() . "\n";
}

echo "\n=== PHP Configuration Files ===\n";
echo "Main php.ini: " . php_ini_loaded_file() . "\n";

$configFiles = php_ini_scanned_files();
if ($configFiles) {
    $files = explode(',', $configFiles);
    $streamingFound = false;
    foreach ($files as $file) {
        $file = trim($file);
        if (strpos($file, 'streaming') !== false) {
            echo "✓ Streaming config: $file\n";
            $streamingFound = true;
        }
    }
    if (!$streamingFound) {
        echo "⚠ No streaming-specific configuration files found\n";
        echo "Looking for 99-streaming-optimizations.ini...\n";
        foreach ($files as $file) {
            $file = trim($file);
            if (strpos($file, '99-streaming') !== false) {
                echo "✓ Found: $file\n";
                $streamingFound = true;
            }
        }
    }
    
    echo "\nAll loaded configuration files:\n";
    foreach (array_slice($files, 0, 5) as $file) { // Show first 5 files
        echo "  - " . trim($file) . "\n";
    }
    if (count($files) > 5) {
        echo "  ... and " . (count($files) - 5) . " more files\n";
    }
} else {
    echo "No additional configuration files loaded\n";
}

echo "\n=== Server Environment ===\n";
echo "Server Software: " . ($_SERVER['SERVER_SOFTWARE'] ?? 'Unknown') . "\n";
echo "Request Method: " . ($_SERVER['REQUEST_METHOD'] ?? 'Unknown') . "\n";
echo "HTTP Host: " . ($_SERVER['HTTP_HOST'] ?? 'Unknown') . "\n";

echo "\n=== Output Buffer Analysis ===\n";
echo "ob_get_level(): " . ob_get_level() . "\n";

if (ob_get_level() > 0) {
    echo "✗ WARNING: Output buffering is active at level " . ob_get_level() . "\n";
    $handlers = ob_list_handlers();
    if (!empty($handlers)) {
        echo "Active handlers: " . implode(', ', $handlers) . "\n";
    }
} else {
    echo "✓ No output buffering detected\n";
}

echo "\n=== OpCache Status ===\n";
if (function_exists('opcache_get_status')) {
    $opcache_status = opcache_get_status(false);
    if ($opcache_status !== false) {
        echo "✓ OpCache enabled: " . ($opcache_status['opcache_enabled'] ? 'Yes' : 'No') . "\n";
        echo "Memory usage: " . round($opcache_status['memory_usage']['used_memory'] / 1024 / 1024, 2) . " MB\n";
    } else {
        echo "⚠ OpCache status not available\n";
    }
} else {
    echo "⚠ OpCache not installed\n";
}

echo "\n=== Streaming Test ===\n";
echo "Testing real-time output...\n";
flush();

for ($i = 1; $i <= 3; $i++) {
    echo "Chunk $i of 3\n";
    flush();
    sleep(1);
}

echo "\n=== Configuration Recommendations ===\n";
$issues = [];

if (ini_get('output_buffering') && ini_get('output_buffering') != '0') {
    $issues[] = "CRITICAL: Disable output_buffering in PHP configuration";
}

if (!ini_get('implicit_flush')) {
    $issues[] = "WARNING: Enable implicit_flush in PHP configuration";
}

if (ini_get('zlib.output_compression')) {
    $issues[] = "WARNING: Disable zlib.output_compression for streaming";
}

if (ob_get_level() > 0) {
    $issues[] = "WARNING: Output buffering is active - check application code";
}

if (empty($issues)) {
    echo "✓ All streaming optimizations appear to be correctly configured!\n";
} else {
    foreach ($issues as $issue) {
        echo "✗ $issue\n";
    }
}

echo "\n=== Test Complete ===\n";
echo "Timestamp: " . date('Y-m-d H:i:s T') . "\n";
?>