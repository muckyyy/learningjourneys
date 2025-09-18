@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h4>{{ $attempt->journey->title }}</h4>
                            <small class="text-muted">
                                Learning Journey - {{ ucfirst($attempt->mode) }} Mode
                                @if($attempt->status === 'completed')
                                    <span class="badge bg-success ms-2">Completed</span>
                                @endif
                            </small>
                        </div>
                        <div>
                            <a href="{{ route('journeys.index') }}" class="btn btn-outline-secondary">
                                <i class="bi bi-arrow-left"></i> Back to Journeys
                            </a>
                        </div>
                    </div>
                </div>
                
                <div class="card-body">
                    <!-- Hidden data for JavaScript -->
                    <div id="journey-data" class="d-none"
                        data-attempt-id="{{ $attempt->id }}"
                        data-journey-id="{{ $attempt->journey_id }}"
                        data-current-step="{{ $attempt->current_step }}"
                        data-mode="{{ $attempt->mode }}"
                        data-status="{{ $attempt->status }}">
                    </div>

                    <!-- Journey Progress -->
                    <div class="row mb-3">
                        <div class="col-12">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="text-muted">Progress</span>
                                <span class="text-muted">Step {{ $attempt->current_step }} of {{ $attempt->journey->steps->count() }}</span>
                            </div>
                            <div class="progress">
                                <div class="progress-bar" role="progressbar" 
                                     style="width: {{ ($attempt->current_step / $attempt->journey->steps->count()) * 100 }}%">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Chat Container -->
                    <div id="chatContainer" class="border p-3 mb-3" style="height: calc(100vh - 250px); min-height: 400px; overflow-y: auto; background-color: #f8f9fa;">
                        <!-- Pre-load existing messages -->
                        @if(isset($existingMessages) && count($existingMessages) > 0)
                            @foreach($existingMessages as $message)
                                <div class="message {{ $message['type'] }}-message">
                                    {!! $message['content'] !!}
                                </div>
                            @endforeach
                        @endif
                    </div>

                    <!-- Message Input -->
                    <div class="row">
                        <div class="col-12">
                            <!-- WebSocket and Audio Status -->
                            <div class="status-indicators mb-2">
                                <small class="text-muted">
                                    <span id="websocket-status">🔌 WebSocket: <span class="status-text">Connecting...</span></span>
                                    <span class="mx-2">|</span>
                                    <span id="audio-status">🎤 Audio: <span class="status-text">Ready</span></span>
                                </small>
                            </div>

                            <div class="input-group">
                                <input type="text" id="messageInput" class="form-control" 
                                       placeholder="Type your response..." 
                                       onkeypress="handleKeyPress(event)"
                                       {{ $attempt->status === 'completed' ? 'disabled' : '' }}>
                                <button class="btn btn-outline-secondary" id="micButton" type="button" title="Voice Input" 
                                        {{ $attempt->status === 'completed' ? 'style=display:none' : '' }}>
                                    🎤
                                </button>
                                <button class="btn btn-primary" onclick="sendMessage()" 
                                        id="sendButton" {{ $attempt->status === 'completed' ? 'disabled' : '' }}>
                                    <span id="sendButtonText">Send</span>
                                    <span class="spinner-border spinner-border-sm d-none" id="sendSpinner"></span>
                                </button>
                            </div>
                            
                            <!-- Recording Indicator - Discreet -->
                            <div id="recordingIndicatorDiscreet" class="recording-indicator-discreet mt-2">
                                <div class="d-flex align-items-center">
                                    <span class="recording-icon-small me-2"></span>
                                    <span class="recording-text me-3">Recording...</span>
                                    <span class="recording-time-small" id="recordingTimeDiscreet">00:00</span>
                                    <span class="mx-2 text-muted">•</span>
                                    <span class="recording-time-remaining-small text-muted" id="recordingTimeRemainingDiscreet">30s left</span>
                                </div>
                            </div>
                            
                            <!-- Processing Indicator -->
                            <div id="processingIndicator" class="processing-indicator mt-2">
                                <div class="d-flex align-items-center">
                                    <div class="processing-spinner me-2"></div>
                                    <span class="processing-text">Processing audio...</span>
                                </div>
                            </div>
                            
                            @if($attempt->status === 'completed')
                                <small class="text-muted">This journey has been completed.</small>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Pusher JS is loaded via compiled app.js in layout -->

<script>
// Verify Pusher is available from compiled assets
if (typeof Pusher === 'undefined') {
    console.error('Pusher is not available. Make sure app.js is loaded in the layout.');
}

let isProcessing = false;
let apiToken = null;

// Initialize the journey chat
document.addEventListener('DOMContentLoaded', function() {
    // First check if user is authenticated
    checkAuthentication();
    
    // Check if messages were pre-loaded from PHP
    const container = document.getElementById('chatContainer');
    const hasPreloadedMessages = container.children.length > 0;
    
    // Scroll to bottom on page load (with small delay to ensure content is rendered)
    if (container) {
        setTimeout(() => {
            container.scrollTop = container.scrollHeight;
        }, 100);
    }
    
    if (!hasPreloadedMessages) {
        // Only load from API if no messages were pre-loaded
        loadExistingMessages();
    }
    
    // Auto-start chat if no messages exist
    setTimeout(() => {
        if (container.children.length === 0) {
            startJourneyChat();
        }
    }, 100);
});

async function checkAuthentication() {
    try {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        if (!csrfToken) {
            console.error('No CSRF token found');
            addMessage('❌ Authentication error: No CSRF token found. Please refresh the page.', 'error');
            return false;
        }
        
        // Test if we can reach the user endpoint with session auth
        const response = await fetch('/api/user', {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin'
        });
        
        if (response.ok) {
            const user = await response.json();
            console.log('User authenticated:', user);
            return true;
        } else {
            console.error('User not authenticated:', response.status);
            addMessage('❌ User not authenticated. Please login and try again.', 'error');
            return false;
        }
    } catch (error) {
        console.error('Authentication check failed:', error);
        return false;
    }
}

async function loadExistingMessages() {
    // Load existing messages from this attempt's step responses
    const attemptId = document.getElementById('journey-data').dataset.attemptId;
    
    console.log('Loading existing messages for attempt:', attemptId);
    
    try {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        const response = await fetch(`/api/journey-attempts/${attemptId}/messages`, {
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin'
        });
        
        console.log('Messages API response status:', response.status);
        
        if (response.ok) {
            const data = await response.json();
            console.log('Messages API response data:', data);
            
            if (data.success && data.messages && data.messages.length > 0) {
                console.log('Loading', data.messages.length, 'existing messages');
                data.messages.forEach((message, index) => {
                    console.log(`Message ${index + 1}:`, message.type, message.content.substring(0, 50));
                    addMessage(message.content, message.type);
                });
            } else {
                console.log('No existing messages found');
            }
        } else {
            const errorText = await response.text();
            console.error('Failed to load messages:', response.status, errorText);
        }
    } catch (error) {
        console.error('Error loading existing messages:', error);
    }
}

async function startJourneyChat() {
    if (isProcessing) return;
    
    const journeyData = document.getElementById('journey-data').dataset;
    
    if (journeyData.status === 'completed') {
        addMessage('✅ This journey has been completed!', 'system');
        return;
    }
    
    isProcessing = true;
    
    try {
        // Try session-based authentication first
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        
        console.log('Starting chat with journey_id:', journeyData.journeyId, 'attempt_id:', journeyData.attemptId);
        
        const response = await fetch('/api/chat/start', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'text/event-stream',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                journey_id: parseInt(journeyData.journeyId),
                attempt_id: parseInt(journeyData.attemptId)
            })
        });

        if (!response.ok) {
            const errorText = await response.text();
            console.error('Chat start failed:', response.status, errorText);
            throw new Error(`Failed to start journey chat: ${response.status} - ${errorText}`);
        }

        const reader = response.body.getReader();
        const decoder = new TextDecoder();
        let currentMessageDiv = null;
        let accumulatedText = '';

        while (true) {
            const { done, value } = await reader.read();
            if (done) break;

            const chunk = decoder.decode(value);
            const lines = chunk.split('\n');

            for (const line of lines) {
                if (line.startsWith('data: ')) {
                    const data = line.substring(6);
                    if (data === '[DONE]') continue;
                    
                    try {
                        const parsed = JSON.parse(data);
                        if (parsed.text) {
                            // Accumulate text and update the same message div
                            accumulatedText += parsed.text;
                            updateStreamingMessage(accumulatedText, 'ai');
                        }
                    } catch (e) {
                        // Ignore parse errors for streaming
                    }
                }
            }
        }
    } catch (error) {
        console.error('Error starting journey chat:', error);
        addMessage('❌ Failed to start journey chat: ' + error.message, 'error');
        addMessage('Please check the browser console for more details, or try refreshing the page.', 'system');
    } finally {
        isProcessing = false;
    }
}

async function sendMessage() {
    if (isProcessing) return;
    
    const input = document.getElementById('messageInput');
    const message = input.value.trim();
    
    if (!message) return;
    
    const journeyData = document.getElementById('journey-data').dataset;
    
    if (journeyData.status === 'completed') {
        addMessage('This journey has been completed.', 'system');
        return;
    }
    
    // Add user message
    addMessage(message, 'user');
    input.value = '';
    
    // Show processing state
    isProcessing = true;
    updateSendButton(true);
    
    try {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        
        const response = await fetch('/api/chat/submit', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'text/event-stream',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                attempt_id: parseInt(journeyData.attemptId),
                user_input: message
            })
        });

        if (!response.ok) {
            throw new Error('Failed to submit message');
        }

        const reader = response.body.getReader();
        const decoder = new TextDecoder();
        let accumulatedText = '';

        while (true) {
            const { done, value } = await reader.read();
            if (done) break;

            const chunk = decoder.decode(value);
            const lines = chunk.split('\n');

            for (const line of lines) {
                if (line.startsWith('data: ')) {
                    const data = line.substring(6);
                    if (data === '[DONE]') continue;
                    
                    try {
                        const parsed = JSON.parse(data);
                        if (parsed.text) {
                            accumulatedText += parsed.text;
                            updateStreamingMessage(accumulatedText, 'ai');
                        }
                        if (parsed.type === 'done' && parsed.is_complete) {
                            addMessage('🎉 Congratulations! You have completed this journey!', 'system');
                            document.getElementById('messageInput').disabled = true;
                            document.getElementById('sendButton').disabled = true;
                            // Update progress bar
                            setTimeout(() => window.location.reload(), 2000);
                        }
                    } catch (e) {
                        // Ignore parse errors for streaming
                    }
                }
            }
        }
    } catch (error) {
        console.error('Error sending message:', error);
        addMessage('❌ Failed to send message. Please try again.', 'error');
    } finally {
        isProcessing = false;
        updateSendButton(false);
    }
}

function addMessage(content, type, newLine = true) {
    const container = document.getElementById('chatContainer');
    const messageDiv = document.createElement('div');
    
    messageDiv.className = `message ${type}-message`;
    messageDiv.innerHTML = content;
    
    container.appendChild(messageDiv);
    container.scrollTop = container.scrollHeight;
}

function updateStreamingMessage(content, type) {
    const container = document.getElementById('chatContainer');
    let lastMessage = container.querySelector(`.message.${type}-message:last-child`);
    
    // If there's no AI message or the last message is not an AI message, create a new one
    if (!lastMessage || !lastMessage.classList.contains(`${type}-message`)) {
        lastMessage = document.createElement('div');
        lastMessage.className = `message ${type}-message`;
        container.appendChild(lastMessage);
    }
    
    lastMessage.innerHTML = content;
    container.scrollTop = container.scrollHeight;
}

function updateSendButton(processing) {
    const button = document.getElementById('sendButton');
    const text = document.getElementById('sendButtonText');
    const spinner = document.getElementById('sendSpinner');
    
    if (processing) {
        text.textContent = 'Sending...';
        spinner.classList.remove('d-none');
        button.disabled = true;
    } else {
        text.textContent = 'Send';
        spinner.classList.add('d-none');
        button.disabled = false;
    }
}

function handleKeyPress(event) {
    if (event.key === 'Enter' && !isProcessing) {
        sendMessage();
    }
}

// Token management functions (same as in journeys.index)
async function getOrGenerateApiToken() {
    try {
        let token = localStorage.getItem('journey_api_token');
        
        if (token) {
            const isValid = await validateToken(token);
            if (isValid) {
                return token;
            } else {
                localStorage.removeItem('journey_api_token');
            }
        }
        
        token = await generateNewApiToken();
        if (token) {
            localStorage.setItem('journey_api_token', token);
            return token;
        }
        
        return null;
    } catch (error) {
        console.error('Error managing API token:', error);
        return null;
    }
}

async function validateToken(token) {
    try {
        const response = await fetch('/api/user', {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
                'Authorization': 'Bearer ' + token
            }
        });
        return response.status === 200;
    } catch (error) {
        return false;
    }
}

async function generateNewApiToken() {
    try {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        
        if (!csrfToken) {
            console.error('CSRF token not found in meta tag');
            throw new Error('CSRF token not found. Please refresh the page.');
        }
        
        console.log('Generating new API token...');
        
        const response = await fetch('/user/api-tokens', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                name: 'Journey Chat Token - ' + new Date().toISOString().slice(0, 19).replace('T', ' ')
            })
        });
        
        if (response.ok) {
            const data = await response.json();
            console.log('Token generated successfully');
            return data.token;
        } else {
            const errorText = await response.text();
            console.error('Token generation failed:', response.status, errorText);
            throw new Error(`Failed to generate token: ${response.status}`);
        }
    } catch (error) {
        console.error('Error generating API token:', error);
        return null;
    }
}

// Audio recording variables
let mediaRecorder = null;
let audioChunks = [];
let recordingSessionId = null;
let isRecording = false;
let recordingTimeout = null;
let recordingStartTime = null;
let recordingTimer = null;
const MAX_RECORDING_TIME = 30; // 30 seconds

// Audio Recording Functions
async function initAudioRecording() {
    try {
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            throw new Error('Audio recording not supported in this browser');
        }

        const stream = await navigator.mediaDevices.getUserMedia({ 
            audio: {
                sampleRate: 16000,
                channelCount: 1,
                echoCancellation: true,
                noiseSuppression: true,
                autoGainControl: true
            } 
        });

        mediaRecorder = new MediaRecorder(stream, {
            mimeType: 'audio/webm;codecs=opus'
        });

        audioChunks = [];

        mediaRecorder.ondataavailable = async (event) => {
            if (event.data.size > 0) {
                audioChunks.push(event.data);
            }
        };

        mediaRecorder.onstop = async () => {
            // Combine all audio chunks into a single blob
            const audioBlob = new Blob(audioChunks, { type: 'audio/webm;codecs=opus' });
            
            // Send the complete audio file for transcription
            await sendCompleteAudioForTranscription(audioBlob);
        };

        return true;
    } catch (error) {
        console.error('Error initializing audio recording:', error);
        return false;
    }
}

async function startAudioRecording() {
    if (isRecording) {
        stopAudioRecording();
        return;
    }

    // Get journey data
    const journeyData = document.getElementById('journey-data').dataset;
    const currentAttemptId = journeyData.attemptId;
    const currentStepId = journeyData.currentStep;

    if (!currentAttemptId) {
        return;
    }

    try {
        // Initialize recording if not already done
        if (!mediaRecorder) {
            const success = await initAudioRecording();
            if (!success) return;
        }

        // Generate session ID for this recording
        recordingSessionId = 'audio_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);

        // Start recording - capture everything until stop
        mediaRecorder.start();
        isRecording = true;
        recordingStartTime = Date.now();

        // Update UI
        const micButton = document.getElementById('micButton');
        micButton.innerHTML = '🔴';
        micButton.title = 'Stop Recording (Max 30s)';
        micButton.classList.add('btn-recording');
        micButton.classList.remove('btn-outline-secondary');
        
        // Update audio status
        document.querySelector('#audio-status .status-text').textContent = 'Recording...';
        document.querySelector('#audio-status .status-text').style.color = 'red';
        
        // Subscribe to WebSocket audio channel for this session
        if (window.subscribeToAudioChannel) {
            window.subscribeToAudioChannel(recordingSessionId);
        }

        // Make input read-only during recording
        const messageInput = document.getElementById('messageInput');
        messageInput.readOnly = true;
        messageInput.placeholder = 'Recording voice input...';

        // Show recording indicator
        showRecordingIndicator();

        // Start recording timer
        startRecordingTimer();

        // Set 30-second timeout
        recordingTimeout = setTimeout(() => {
            stopAudioRecording();
        }, MAX_RECORDING_TIME * 1000);

    } catch (error) {
        console.error('Error starting recording:', error);
        
        // Reset state
        isRecording = false;
        recordingSessionId = null;
        recordingStartTime = null;
        
        // Clear timers
        if (recordingTimeout) {
            clearTimeout(recordingTimeout);
            recordingTimeout = null;
        }
        if (recordingTimer) {
            clearInterval(recordingTimer);
            recordingTimer = null;
        }
        
        // Reset UI
        const micButton = document.getElementById('micButton');
        micButton.innerHTML = '🎤';
        micButton.title = 'Voice Input';
        micButton.classList.remove('btn-recording');
        micButton.classList.add('btn-outline-secondary');
        
        // Restore input functionality
        const messageInput = document.getElementById('messageInput');
        messageInput.readOnly = false;
        messageInput.placeholder = 'Type your response...';
        
        // Hide all indicators
        hideRecordingIndicator();
        hideProcessingIndicator();
    }
}

async function stopAudioRecording() {
    if (!isRecording || !mediaRecorder) return;

    try {
        isRecording = false;
        
        // Clear timeout and timer
        if (recordingTimeout) {
            clearTimeout(recordingTimeout);
            recordingTimeout = null;
        }
        if (recordingTimer) {
            clearInterval(recordingTimer);
            recordingTimer = null;
        }

        // Stop recording
        mediaRecorder.stop();

        // Update UI
        const micButton = document.getElementById('micButton');
        micButton.innerHTML = '🎤';
        micButton.title = 'Voice Input';
        micButton.classList.remove('btn-recording');
        micButton.classList.add('btn-outline-secondary');
        
        // Update audio status
        document.querySelector('#audio-status .status-text').textContent = 'Processing...';
        document.querySelector('#audio-status .status-text').style.color = 'orange';

        // Keep input read-only until processing is complete
        const messageInput = document.getElementById('messageInput');
        messageInput.placeholder = 'Processing voice input...';

        // Hide recording indicator and show processing indicator
        hideRecordingIndicator();
        showProcessingIndicator();

    } catch (error) {
        console.error('Error stopping recording:', error);
        
        // Reset state anyway
        isRecording = false;
        recordingStartTime = null;
        
        // Clear timers
        if (recordingTimeout) {
            clearTimeout(recordingTimeout);
            recordingTimeout = null;
        }
        if (recordingTimer) {
            clearInterval(recordingTimer);
            recordingTimer = null;
        }
        
        const micButton = document.getElementById('micButton');
        micButton.innerHTML = '🎤';
        micButton.title = 'Voice Input';
        micButton.classList.remove('btn-recording');
        micButton.classList.add('btn-outline-secondary');
        
        // Restore input functionality on error
        const messageInput = document.getElementById('messageInput');
        messageInput.readOnly = false;
        messageInput.placeholder = 'Type your response...';
        
        // Hide both indicators
        hideRecordingIndicator();
        hideProcessingIndicator();
    }
}

async function sendCompleteAudioForTranscription(audioBlob) {
    if (!recordingSessionId) return;

    try {
        // Get journey data
        const journeyData = document.getElementById('journey-data').dataset;
        const currentAttemptId = journeyData.attemptId;
        const currentStepId = journeyData.currentStep;

        // Create FormData to send the audio file
        const formData = new FormData();
        formData.append('audio', audioBlob, 'recording.webm');
        formData.append('session_id', recordingSessionId);
        formData.append('journey_attempt_id', currentAttemptId);
        formData.append('journey_step_id', currentStepId);

        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        
        const response = await fetch('/audio/transcribe', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            },
            credentials: 'same-origin',
            body: formData
        });

        if (!response.ok) {
            const errorData = await response.json().catch(() => ({}));
            throw new Error(errorData.error || `HTTP ${response.status}: ${response.statusText}`);
        }

        const data = await response.json();
        
        if (data.success && data.transcription) {
            // Hide processing indicator and restore input
            hideProcessingIndicator();
            const messageInput = document.getElementById('messageInput');
            messageInput.readOnly = false;
            messageInput.placeholder = 'Type your response...';
            
            // Update audio status
            document.querySelector('#audio-status .status-text').textContent = 'Ready';
            document.querySelector('#audio-status .status-text').style.color = 'green';
            
            // Insert transcription into input field
            const currentValue = messageInput.value.trim();
            const newValue = currentValue ? currentValue + ' ' + data.transcription : data.transcription;
            messageInput.value = newValue;
            
            // Clean up recording session
            recordingSessionId = null;
            
            // Automatically submit the transcribed message
            if (newValue.length > 0) {
                // Small delay to let user see the transcription before submitting
                setTimeout(() => {
                    sendMessage();
                }, 500);
            }
        } else {
            throw new Error('Transcription failed: ' + (data.error || 'Unknown error'));
        }

    } catch (error) {
        console.error('Error sending audio for transcription:', error);
        
        // Hide processing indicator and restore input on error
        hideProcessingIndicator();
        const messageInput = document.getElementById('messageInput');
        messageInput.readOnly = false;
        messageInput.placeholder = 'Type your response...';
        
        // Show error message to user
        addMessage('❌ Failed to transcribe audio. Please try again or type your message.', 'error');
        
        recordingSessionId = null;
    }
}

// Recording indicator functions
function showRecordingIndicator() {
    const indicator = document.getElementById('recordingIndicatorDiscreet');
    if (indicator) {
        indicator.classList.add('show');
    }
}

function hideRecordingIndicator() {
    const indicator = document.getElementById('recordingIndicatorDiscreet');
    if (indicator) {
        indicator.classList.remove('show');
    }
}

// Processing indicator functions
function showProcessingIndicator() {
    const indicator = document.getElementById('processingIndicator');
    if (indicator) {
        indicator.classList.add('show');
    }
}

function hideProcessingIndicator() {
    const indicator = document.getElementById('processingIndicator');
    if (indicator) {
        indicator.classList.remove('show');
    }
}

function startRecordingTimer() {
    if (recordingTimer) {
        clearInterval(recordingTimer);
    }
    
    recordingTimer = setInterval(() => {
        if (!recordingStartTime || !isRecording) {
            return;
        }
        
        const elapsed = Math.floor((Date.now() - recordingStartTime) / 1000);
        const remaining = Math.max(0, MAX_RECORDING_TIME - elapsed);
        
        // Update time display
        const timeElement = document.getElementById('recordingTimeDiscreet');
        const remainingElement = document.getElementById('recordingTimeRemainingDiscreet');
        
        if (timeElement) {
            const minutes = Math.floor(elapsed / 60);
            const seconds = elapsed % 60;
            timeElement.textContent = `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
        }
        
        if (remainingElement) {
            if (remaining > 0) {
                remainingElement.textContent = `${remaining}s left`;
            } else {
                remainingElement.textContent = 'Time up';
            }
        }
        
        // Auto-stop when time is up
        if (remaining <= 0) {
            stopAudioRecording();
        }
    }, 100); // Update every 100ms for smooth timer
}

function formatTime(seconds) {
    const minutes = Math.floor(seconds / 60);
    const secs = seconds % 60;
    return `${minutes.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
}

// Add mic button event listener when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    const micButton = document.getElementById('micButton');
    if (micButton) {
        micButton.addEventListener('click', function() {
            const journeyData = document.getElementById('journey-data').dataset;
            if (journeyData.status === 'completed') {
                return;
            }

            if (isRecording) {
                stopAudioRecording();
            } else {
                startAudioRecording();
            }
        });
    }

    // WebSocket Integration using Laravel Reverb
    const pusher = new Pusher('{{ env('REVERB_APP_KEY') }}', {
        cluster: '', // No cluster for Reverb
        wsHost: '{{ env('REVERB_HOST', 'localhost') }}',
        wsPort: {{ env('REVERB_PORT', 8080) }},
        forceTLS: false,
        enabledTransports: ['ws'],
        auth: {
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'Authorization': 'Bearer ' + (apiToken || '')
            }
        }
    });

    // WebSocket connection status handlers
    pusher.connection.bind('connected', function() {
        document.querySelector('#websocket-status .status-text').textContent = 'Connected';
        document.querySelector('#websocket-status .status-text').style.color = 'green';
    });

    pusher.connection.bind('disconnected', function() {
        document.querySelector('#websocket-status .status-text').textContent = 'Disconnected';
        document.querySelector('#websocket-status .status-text').style.color = 'red';
    });

    pusher.connection.bind('error', function(err) {
        document.querySelector('#websocket-status .status-text').textContent = 'Error';
        document.querySelector('#websocket-status .status-text').style.color = 'red';
        console.error('WebSocket error:', err);
    });

    // Subscribe to audio session channel if we have a recording session
    window.subscribeToAudioChannel = function(sessionId) {
        if (!sessionId) return;
        
        const audioChannel = pusher.subscribe('private-audio-session.' + sessionId);
        audioChannel.bind('App\\Events\\AudioChunkReceived', function(data) {
            console.log('Audio chunk received via WebSocket:', data);
            document.querySelector('#audio-status .status-text').textContent = 
                `Chunk #${data.chunk_number} received`;
        });
    };
});
</script>
@endsection
