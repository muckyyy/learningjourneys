<?php
/**
 * Quick PHP Configuration Test
 * Check if streaming optimizations are applied
 */

header('Content-Type: text/plain');

echo "=== PHP Streaming Configuration Test ===\n\n";

// Check critical settings
$settings = [
    'output_buffering' => ini_get('output_buffering'),
    'implicit_flush' => ini_get('implicit_flush'),
    'zlib.output_compression' => ini_get('zlib.output_compression'),
    'output_handler' => ini_get('output_handler'),
    'max_execution_time' => ini_get('max_execution_time'),
    'memory_limit' => ini_get('memory_limit'),
];

foreach ($settings as $setting => $value) {
    $status = '';
    if ($setting === 'output_buffering') {
        $status = ($value == '0' || $value === false) ? '✓ GOOD' : '✗ BAD (should be 0)';
    } elseif ($setting === 'implicit_flush') {
        $status = ($value == '1' || $value === true) ? '✓ GOOD' : '✗ BAD (should be 1)';
    } elseif ($setting === 'zlib.output_compression') {
        $status = ($value == '0' || $value === false || $value === '') ? '✓ GOOD' : '✗ BAD (should be off)';
    }
    
    echo sprintf("%-25s: %-10s %s\n", $setting, $value === false ? 'false' : ($value === true ? 'true' : $value), $status);
}

echo "\n=== PHP Configuration Files Loaded ===\n";
$configFiles = php_ini_scanned_files();
if ($configFiles) {
    $files = explode(',', $configFiles);
    foreach ($files as $file) {
        $file = trim($file);
        if (strpos($file, 'streaming') !== false) {
            echo "✓ $file\n";
        }
    }
} else {
    echo "No additional configuration files loaded\n";
}

echo "\n=== Main PHP Configuration ===\n";
echo "Main php.ini: " . php_ini_loaded_file() . "\n";
echo "Additional configs: " . (php_ini_scanned_files() ? 'Yes' : 'No') . "\n";

echo "\n=== Current Buffer Level ===\n";
echo "ob_get_level(): " . ob_get_level() . "\n";

echo "\n=== Recommendations ===\n";
if (ini_get('output_buffering') != '0' && ini_get('output_buffering') !== false) {
    echo "✗ CRITICAL: Disable output_buffering in PHP configuration\n";
} else {
    echo "✓ Output buffering is properly disabled\n";
}

if (!ini_get('implicit_flush')) {
    echo "✗ WARNING: Enable implicit_flush in PHP configuration\n";
} else {
    echo "✓ Implicit flush is properly enabled\n";
}

echo "\n=== Test Complete ===\n";
?>