@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="row justify-content-center">
        <div class="col-md-10">
            <div class="card">
                <div class="card-header">
                    <h4>🚀 Laravel Reverb WebSocket Test (Integrated)</h4>
                    <small class="text-muted">Using compiled Laravel Echo with Reverb broadcaster</small>
                </div>
                <div class="card-body">
                    
                    <!-- Configuration Info -->
                    <div class="alert alert-info">
                        <h5>📋 Configuration</h5>
                        <p><strong>Broadcasting Driver:</strong> {{ config('broadcasting.default') }}</p>
                        <p><strong>App Environment:</strong> {{ app()->environment() }}</p>
                        <p><strong>WebSocket URL:</strong> <span id="ws-url">Detecting...</span></p>
                        <p class="mb-0"><strong>Assets:</strong> Using compiled app.js (no external CDN)</p>
                    </div>

                    <!-- Connection Status -->
                    <div id="connection-status" class="alert alert-warning">
                        <h5>🔌 Connection Status: <span id="status-text">Initializing...</span></h5>
                        <p id="status-details" class="mb-0">Preparing WebSocket connection...</p>
                    </div>

                    <!-- Connection Controls -->
                    <div class="card mb-4">
                        <div class="card-body">
                            <h5>Connection Controls</h5>
                            <button id="connect-btn" class="btn btn-primary" onclick="connectWebSocket()">Connect</button>
                            <button id="disconnect-btn" class="btn btn-danger" onclick="disconnectWebSocket()" disabled>Disconnect</button>
                            <button id="test-broadcast-btn" class="btn btn-success" onclick="testBroadcast()" disabled>Test Broadcast</button>
                            <button id="clear-log-btn" class="btn btn-secondary" onclick="clearMessages()">Clear Log</button>
                        </div>
                    </div>

                    <!-- Messages -->
                    <div class="card mb-4">
                        <div class="card-body">
                            <h5>📨 Messages & Events</h5>
                            <div id="messages" style="border: 1px solid #dee2e6; border-radius: 5px; height: 300px; overflow-y: auto; padding: 15px; background-color: #f8f9fa; font-family: monospace; font-size: 14px;">
                                <div>Waiting for WebSocket connection...</div>
                            </div>
                        </div>
                    </div>

                    <!-- Audio Test -->
                    <div class="card">
                        <div class="card-body">
                            <h5>🎤 Audio Recording Test</h5>
                            <button id="start-recording" class="btn btn-primary" onclick="startAudioRecording()" disabled>Start Recording</button>
                            <button id="stop-recording" class="btn btn-danger" onclick="stopAudioRecording()" disabled>Stop Recording</button>
                            <div class="mt-3">
                                <strong>Recording Status:</strong> <span id="recording-text">Ready</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    let isConnected = false;
    let isRecording = false;
    let mediaRecorder = null;
    let audioChunks = [];

    // DOM elements
    const statusPanel = document.getElementById('connection-status');
    const statusText = document.getElementById('status-text');
    const statusDetails = document.getElementById('status-details');
    const messagesContainer = document.getElementById('messages');
    const connectBtn = document.getElementById('connect-btn');
    const disconnectBtn = document.getElementById('disconnect-btn');
    const testBroadcastBtn = document.getElementById('test-broadcast-btn');
    const startRecordingBtn = document.getElementById('start-recording');
    const stopRecordingBtn = document.getElementById('stop-recording');
    const recordingText = document.getElementById('recording-text');
    const wsUrlSpan = document.getElementById('ws-url');

    // Utility functions
    function addMessage(message, type = 'info') {
        const timestamp = new Date().toLocaleTimeString();
        const messageDiv = document.createElement('div');
        messageDiv.innerHTML = `[${timestamp}] ${message}`;
        messageDiv.style.color = type === 'error' ? '#dc3545' : type === 'success' ? '#28a745' : '#333';
        messagesContainer.appendChild(messageDiv);
        messagesContainer.scrollTop = messagesContainer.scrollHeight;
    }

    function updateConnectionStatus(status, details) {
        statusText.textContent = status;
        statusDetails.textContent = details;
        
        statusPanel.className = 'alert';
        if (status.includes('Connected')) {
            statusPanel.classList.add('alert-success');
        } else if (status.includes('Connecting')) {
            statusPanel.classList.add('alert-warning');
        } else {
            statusPanel.classList.add('alert-danger');
        }
    }

    function updateButtonStates() {
        connectBtn.disabled = isConnected;
        disconnectBtn.disabled = !isConnected;
        testBroadcastBtn.disabled = !isConnected;
        startRecordingBtn.disabled = !isConnected;
        stopRecordingBtn.disabled = !isRecording;
    }

    // WebSocket functions
    function connectWebSocket() {
        if (!window.Echo) {
            addMessage('❌ Laravel Echo is not available. Please check your app.js compilation.', 'error');
            return;
        }

        addMessage('🔌 Attempting to connect to Laravel Reverb...', 'info');
        updateConnectionStatus('Connecting...', 'Establishing WebSocket connection');
        
        try {
            // Display the WebSocket URL being used
            if (window.Echo.connector && window.Echo.connector.pusher && window.Echo.connector.pusher.config) {
                const echoConfig = window.Echo.connector.pusher.config;
                const wsUrl = `${echoConfig.encrypted ? 'wss' : 'ws'}://${echoConfig.wsHost}:${echoConfig.wsPort}`;
                wsUrlSpan.textContent = wsUrl;
            } else {
                wsUrlSpan.textContent = 'Configuration not available';
            }
            
            // Listen for connection events
            window.Echo.connector.pusher.connection.bind('connected', function() {
                isConnected = true;
                updateConnectionStatus('Connected ✅', 'WebSocket connection established successfully');
                addMessage('✅ WebSocket connected successfully!', 'success');
                updateButtonStates();
                
                // Subscribe to test channels
                subscribeToTestChannels();
            });

            window.Echo.connector.pusher.connection.bind('disconnected', function() {
                isConnected = false;
                updateConnectionStatus('Disconnected ❌', 'WebSocket connection lost');
                addMessage('❌ WebSocket disconnected', 'error');
                updateButtonStates();
            });

            window.Echo.connector.pusher.connection.bind('error', function(error) {
                addMessage('❌ WebSocket error: ' + JSON.stringify(error), 'error');
                updateConnectionStatus('Error ❌', 'Connection error occurred');
            });

            window.Echo.connector.pusher.connection.bind('state_change', function(states) {
                addMessage(`📡 Connection state change: ${states.previous} → ${states.current}`, 'info');
            });

            // Force connection attempt
            window.Echo.connector.pusher.connect();
            
        } catch (error) {
            addMessage('❌ Failed to initialize WebSocket: ' + error.message, 'error');
            updateConnectionStatus('Failed ❌', 'WebSocket initialization failed');
        }
    }

    function disconnectWebSocket() {
        if (window.Echo && window.Echo.connector.pusher) {
            window.Echo.connector.pusher.disconnect();
            addMessage('🔌 WebSocket disconnected by user', 'info');
        }
    }

    function subscribeToTestChannels() {
        try {
            // Subscribe to audio chunk events
            window.Echo.channel('audio-session-test')
                .listen('AudioChunkReceived', (e) => {
                    addMessage(`🎤 Audio chunk received: ${JSON.stringify(e)}`, 'success');
                });

            // Subscribe to general test channel
            window.Echo.channel('test-channel')
                .listen('.test-event', (e) => {
                    addMessage(`📨 Test event received: ${JSON.stringify(e)}`, 'success');
                });

            addMessage('📡 Subscribed to test channels: audio-session-test, test-channel', 'info');
        } catch (error) {
            addMessage('❌ Failed to subscribe to channels: ' + error.message, 'error');
        }
    }

    function testBroadcast() {
        fetch('{{ route("test.broadcast") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({ 
                test: true,
                timestamp: new Date().toISOString(),
                session: 'websocket-test'
            })
        })
        .then(response => response.json())
        .then(data => {
            addMessage('📤 Test broadcast sent: ' + JSON.stringify(data), 'info');
        })
        .catch(error => {
            addMessage('❌ Test broadcast failed: ' + error.message, 'error');
        });
    }

    // Audio recording functions
    function startAudioRecording() {
        navigator.mediaDevices.getUserMedia({ audio: true })
            .then(stream => {
                mediaRecorder = new MediaRecorder(stream);
                audioChunks = [];
                isRecording = true;

                mediaRecorder.ondataavailable = event => {
                    audioChunks.push(event.data);
                    addMessage(`🎤 Audio chunk recorded: ${event.data.size} bytes`, 'info');
                };

                mediaRecorder.onstop = () => {
                    const audioBlob = new Blob(audioChunks, { type: 'audio/wav' });
                    addMessage(`🎤 Recording completed: ${audioBlob.size} bytes total`, 'success');
                    
                    // Stop all tracks
                    stream.getTracks().forEach(track => track.stop());
                };

                mediaRecorder.start(1000); // Collect data every second
                recordingText.textContent = 'Recording... 🔴';
                updateButtonStates();
                addMessage('🎤 Audio recording started', 'success');
            })
            .catch(error => {
                addMessage('❌ Failed to access microphone: ' + error.message, 'error');
            });
    }

    function stopAudioRecording() {
        if (mediaRecorder && isRecording) {
            mediaRecorder.stop();
            isRecording = false;
            recordingText.textContent = 'Ready';
            updateButtonStates();
            addMessage('🎤 Audio recording stopped', 'info');
        }
    }

    function clearMessages() {
        messagesContainer.innerHTML = '<div>Log cleared...</div>';
    }

    // Initialize on page load
    document.addEventListener('DOMContentLoaded', function() {
        addMessage('🚀 Laravel Reverb WebSocket test initialized', 'info');
        
        if (window.Echo) {
            addMessage('✅ Laravel Echo loaded successfully', 'success');
            addMessage('📋 Echo configuration ready', 'info');
        } else {
            addMessage('❌ Laravel Echo not found! Check app.js compilation.', 'error');
        }
        
        updateButtonStates();
        
        // Auto-connect after a short delay
        setTimeout(() => {
            if (window.Echo) {
                connectWebSocket();
            }
        }, 1000);
    });
</script>
@endpush