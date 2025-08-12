<?php

// Simple API test script
function testAPI($endpoint, $data = null, $token = null) {
    $url = "http://127.0.0.1:8001/api/" . $endpoint;
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    
    if ($token) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
            'Accept: application/json'
        ]);
    }
    
    if ($data) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "=== Testing $endpoint ===\n";
    echo "HTTP Code: $httpCode\n";
    echo "Response:\n$response\n\n";
    
    return $httpCode;
}

// Test without token (should fail)
echo "Testing API endpoints without authentication:\n";
testAPI('start_chat', ['journey_id' => 1]);

echo "\n";
echo "Please generate an API token from http://127.0.0.1:8001/api-tokens\n";
echo "Then run this script with the token as an argument:\n";
echo "php test_api.php YOUR_TOKEN_HERE\n";

// If token is provided as argument
if (isset($argv[1])) {
    $token = $argv[1];
    echo "\nTesting with token: " . substr($token, 0, 10) . "...\n\n";
    
    // Test start_chat endpoint
    testAPI('start_chat', ['journey_id' => 1], $token);
    
    // Test chat_submit endpoint
    testAPI('chat_submit', [
        'journey_id' => 1,
        'user_input' => 'Hello, I would like to start learning!'
    ], $token);
}
