@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">Laravel WebSocket (Reverb) Test</div>
                <div class="card-body">
                    <div id="status">
                        <p><strong>Connection Status:</strong> <span id="connection-status">Connecting...</span></p>
                        <p><strong>Messages:</strong></p>
                        <div id="messages" style="border: 1px solid #ccc; padding: 10px; height: 200px; overflow-y: scroll; background: #f8f9fa;">
                            <p>Initializing...</p>
                        </div>
                    </div>

                    <div id="audio-test" style="margin-top: 20px;">
                        <h5>Audio Recording Test</h5>
                        <button id="start-recording" class="btn btn-success" onclick="startRecording()">Start Recording</button>
                        <button id="stop-recording" class="btn btn-danger" onclick="stopRecording()" disabled>Stop Recording</button>
                        <button id="test-broadcast" class="btn btn-primary" onclick="testBroadcast()">Send Test Broadcast</button>
                        <p class="mt-2"><strong>Recording Status:</strong> <span id="recording-status">Ready</span></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://js.pusher.com/7.2/pusher.min.js"></script>
<script>
    // Initialize Pusher client for Laravel Reverb
    const pusher = new Pusher('{{ env('REVERB_APP_KEY') }}', {
        cluster: '', // No cluster for Reverb
        wsHost: '{{ env('REVERB_HOST', 'localhost') }}',
        wsPort: {{ env('REVERB_PORT', 8080) }},
        forceTLS: false,
        enabledTransports: ['ws']
    });

    // Get DOM elements
    const statusEl = document.getElementById('connection-status');
    const messagesEl = document.getElementById('messages');
    const recordingStatusEl = document.getElementById('recording-status');

    // Connection event handlers
    pusher.connection.bind('connected', function() {
        statusEl.textContent = 'Connected';
        statusEl.style.color = 'green';
        addMessage('✅ WebSocket connected successfully!');
    });

    pusher.connection.bind('disconnected', function() {
        statusEl.textContent = 'Disconnected';
        statusEl.style.color = 'red';
        addMessage('❌ WebSocket disconnected');
    });

    pusher.connection.bind('error', function(err) {
        statusEl.textContent = 'Error';
        statusEl.style.color = 'red';
        addMessage('❌ WebSocket error: ' + JSON.stringify(err));
    });

    // Subscribe to audio session channel (example)
    const sessionId = 'test-session-123';
    const audioChannel = pusher.subscribe('private-audio-session.' + sessionId);
    audioChannel.bind('App\\Events\\AudioChunkReceived', function(data) {
        addMessage('🎵 Audio chunk received: ' + JSON.stringify(data));
        recordingStatusEl.textContent = 'Chunk received #' + data.chunk_number;
    });

    // Helper function to add messages
    function addMessage(message) {
        const time = new Date().toLocaleTimeString();
        messagesEl.innerHTML += `<p class="mb-1"><small class="text-muted">[${time}]</small> ${message}</p>`;
        messagesEl.scrollTop = messagesEl.scrollHeight;
    }

    // Audio recording simulation
    let isRecording = false;
    let chunkCount = 0;

    function startRecording() {
        if (isRecording) return;
        
        isRecording = true;
        chunkCount = 0;
        document.getElementById('start-recording').disabled = true;
        document.getElementById('stop-recording').disabled = false;
        recordingStatusEl.textContent = 'Recording...';
        
        addMessage('🎤 Started recording simulation');
        
        // Simulate sending audio chunks every 2 seconds
        const chunkInterval = setInterval(() => {
            if (!isRecording) {
                clearInterval(chunkInterval);
                return;
            }
            
            chunkCount++;
            addMessage(`📊 Simulating audio chunk ${chunkCount} sent`);
            recordingStatusEl.textContent = `Recording... (chunk ${chunkCount})`;
        }, 2000);
    }

    function stopRecording() {
        if (!isRecording) return;
        
        isRecording = false;
        document.getElementById('start-recording').disabled = false;
        document.getElementById('stop-recording').disabled = true;
        recordingStatusEl.textContent = 'Stopped';
        addMessage('🛑 Recording stopped');
    }

    function testBroadcast() {
        fetch('{{ route('test.broadcast') }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            }
        })
        .then(response => response.json())
        .then(data => {
            addMessage('📡 Test broadcast sent: ' + data.message);
        })
        .catch(error => {
            addMessage('❌ Test broadcast failed: ' + error.message);
        });
    }

    // Test connection on load
    addMessage('🔄 Connecting to Laravel WebSocket server...');
</script>
@endsection