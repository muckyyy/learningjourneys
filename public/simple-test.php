<?php
/**
 * Simple Apache Test - Basic Diagnostics
 * Minimal test to check if PHP is working and identify issues
 */

// Basic error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Basic output
echo "=== SIMPLE APACHE TEST ===\n";
echo "Current Time: " . date('Y-m-d H:i:s') . "\n";
echo "PHP Version: " . phpversion() . "\n";
echo "SAPI: " . php_sapi_name() . "\n";

// Test basic functionality
echo "\n=== Basic Functionality Test ===\n";

try {
    echo "✓ Basic echo works\n";
    
    // Test server variables
    echo "✓ Server: " . ($_SERVER['SERVER_SOFTWARE'] ?? 'Unknown') . "\n";
    echo "✓ Method: " . ($_SERVER['REQUEST_METHOD'] ?? 'Unknown') . "\n";
    echo "✓ Host: " . ($_SERVER['HTTP_HOST'] ?? 'Unknown') . "\n";
    
    // Test flush
    echo "✓ Testing flush...\n";
    flush();
    
    echo "✓ Flush completed\n";
    
    // Test simple loop
    echo "\n=== Simple Streaming Test ===\n";
    for ($i = 1; $i <= 3; $i++) {
        echo "Chunk $i ";
        flush();
        if ($i < 3) sleep(1);
    }
    echo "\n✓ Simple streaming test completed\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
} catch (Error $e) {
    echo "FATAL ERROR: " . $e->getMessage() . "\n";
}

echo "\n=== Test Complete ===\n";
?>