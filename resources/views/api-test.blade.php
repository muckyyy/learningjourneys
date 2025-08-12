<!DOCTYPE html>
<html>
<head>
    <title>API Test</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .test-result { margin: 10px 0; padding: 10px; border-radius: 5px; }
        .success { background-color: #d4edda; color: #155724; }
        .error { background-color: #f8d7da; color: #721c24; }
        .info { background-color: #d1ecf1; color: #0c5460; }
        input, button { margin: 5px; padding: 8px; }
        #response-area { border: 1px solid #ccc; padding: 10px; margin: 10px 0; height: 300px; overflow-y: scroll; background-color: #f8f9fa; font-family: monospace; white-space: pre-wrap; }
    </style>
</head>
<body>
    <h1>API Endpoint Test</h1>
    
    <div class="test-result info">
        <h3>Step 1: Test Authentication Middleware</h3>
        <div id="auth-test">Testing...</div>
    </div>
    
    <div class="test-result info">
        <h3>Step 2: Test with API Token</h3>
        <p>Get your API token from: <a href="/user/api-tokens" target="_blank">API Tokens Page</a></p>
        <input type="text" id="api-token" placeholder="Paste your API token here" style="width: 300px;">
        <br>
        <button onclick="testStartChat()">Test start_chat</button>
        <button onclick="testChatSubmit()">Test chat_submit</button>
        <button onclick="testChatSubmitStream()">Test chat_submit (Stream)</button>
    </div>
    
    <div class="test-result">
        <h3>API Response:</h3>
        <div id="response-area">Responses will appear here...</div>
    </div>

    <script>
    let responseArea = document.getElementById('response-area');
    
    function log(message) {
        responseArea.textContent += new Date().toLocaleTimeString() + ': ' + message + '\n';
        responseArea.scrollTop = responseArea.scrollHeight;
    }
    
    function clearLog() {
        responseArea.textContent = '';
    }

    // Test authentication middleware
    fetch('/api/start_chat', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        },
        body: JSON.stringify({
            journey_id: 1
        })
    })
    .then(response => {
        const authTestDiv = document.getElementById('auth-test');
        if (response.status === 401) {
            authTestDiv.innerHTML = '<span style="color: green;">✅ PASS: Authentication required (401 Unauthorized)</span>';
            log('✅ Authentication middleware working correctly - got 401 Unauthorized');
        } else {
            authTestDiv.innerHTML = '<span style="color: red;">❌ FAIL: Expected 401, got ' + response.status + '</span>';
            log('❌ Authentication test failed - expected 401, got ' + response.status);
        }
        return response.text();
    })
    .then(text => {
        log('Unauthenticated response: ' + text);
    })
    .catch(error => {
        document.getElementById('auth-test').innerHTML = '<span style="color: red;">❌ ERROR: ' + error.message + '</span>';
        log('❌ Error testing authentication: ' + error.message);
    });

    function getToken() {
        const token = document.getElementById('api-token').value.trim();
        if (!token) {
            alert('Please enter an API token first');
            return null;
        }
        return token;
    }

    function testStartChat() {
        const token = getToken();
        if (!token) return;
        
        clearLog();
        log('🚀 Testing start_chat endpoint...');
        
        fetch('/api/start_chat', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'Authorization': 'Bearer ' + token
            },
            body: JSON.stringify({
                journey_id: 1
            })
        })
        .then(response => {
            log('📡 Response status: ' + response.status);
            log('📡 Response headers: ' + JSON.stringify([...response.headers.entries()]));
            
            if (response.ok) {
                log('✅ start_chat endpoint is working!');
                
                // Check if it's a streaming response
                const contentType = response.headers.get('content-type');
                if (contentType && contentType.includes('text/plain')) {
                    log('📺 Streaming response detected');
                    return response.text();
                } else {
                    log('📄 JSON response detected');
                    return response.json();
                }
            } else {
                return response.text().then(text => {
                    throw new Error('HTTP ' + response.status + ': ' + text);
                });
            }
        })
        .then(data => {
            log('📝 Response data: ' + (typeof data === 'string' ? data : JSON.stringify(data, null, 2)));
        })
        .catch(error => {
            log('❌ Error: ' + error.message);
        });
    }

    function testChatSubmit() {
        const token = getToken();
        if (!token) return;
        
        clearLog();
        log('🚀 Testing chat_submit endpoint...');
        
        fetch('/api/chat_submit', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'Authorization': 'Bearer ' + token
            },
            body: JSON.stringify({
                journey_id: 1,
                user_input: 'Hello, I would like to start learning about data structures!'
            })
        })
        .then(response => {
            log('📡 Response status: ' + response.status);
            log('📡 Response headers: ' + JSON.stringify([...response.headers.entries()]));
            
            if (response.ok) {
                log('✅ chat_submit endpoint is working!');
                
                // Check if it's a streaming response
                const contentType = response.headers.get('content-type');
                if (contentType && contentType.includes('text/plain')) {
                    log('📺 Streaming response detected');
                    return response.text();
                } else {
                    log('📄 JSON response detected');
                    return response.json();
                }
            } else {
                return response.text().then(text => {
                    throw new Error('HTTP ' + response.status + ': ' + text);
                });
            }
        })
        .then(data => {
            log('📝 Response data: ' + (typeof data === 'string' ? data : JSON.stringify(data, null, 2)));
        })
        .catch(error => {
            log('❌ Error: ' + error.message);
        });
    }

    function testChatSubmitStream() {
        const token = getToken();
        if (!token) return;
        
        clearLog();
        log('🚀 Testing chat_submit endpoint with streaming...');
        
        const eventSource = new EventSource('/api/chat_submit?' + new URLSearchParams({
            journey_id: 1,
            user_input: 'Can you explain what algorithms are?',
            _token: token
        }));
        
        eventSource.onopen = function() {
            log('📺 SSE connection opened');
        };
        
        eventSource.onmessage = function(event) {
            log('📨 SSE message: ' + event.data);
        };
        
        eventSource.onerror = function(event) {
            log('❌ SSE error occurred');
            eventSource.close();
        };
        
        // Close after 30 seconds
        setTimeout(() => {
            eventSource.close();
            log('🔌 SSE connection closed after 30 seconds');
        }, 30000);
    }
    </script>
</body>
</html>
