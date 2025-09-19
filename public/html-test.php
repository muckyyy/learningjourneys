<?php
/**
 * Test with HTML Content-Type
 */
header('Content-Type: text/html; charset=utf-8');
echo "<html><head><title>Test</title></head><body>";
echo "<h1>HTML Test Page</h1>";
echo "<p>Current Time: " . date('Y-m-d H:i:s') . "</p>";
echo "<p>PHP Version: " . phpversion() . "</p>";
echo "<p>Server: " . ($_SERVER['SERVER_SOFTWARE'] ?? 'Unknown') . "</p>";
echo "<pre>";
echo "=== PHP Streaming Configuration Test ===\n";
echo "This should be visible in browser...\n";
echo "</pre>";
echo "</body></html>";
?>