require('./bootstrap');

// Load SortableJS globally
try {
    window.Sortable = require('sortablejs');
} catch (e) {}

// Import Echo and Pusher for WebSocket functionality
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

// Make Pusher class available globally for Blade templates
window.Pusher = Pusher;

// WebSocket Application Logic
// Environment-aware WebSocket configuration
const getWebSocketConfig = () => {
    // For local development - expanded local hostname detection
    const isLocal = window.location.hostname === 'localhost' || 
                   window.location.hostname === '127.0.0.1' ||
                   window.location.hostname.endsWith('.local') ||
                   window.location.port === '8000' ||
                   window.location.hostname === 'learningjourneys.test';
    
    if (isLocal) {
        return {
            app_key: process.env.MIX_VITE_REVERB_APP_KEY || 'ez8fmlurx5ekx7vdiocj',
            host: '127.0.0.1',
            port: 8080,
            scheme: 'http',
            forceTLS: false,
            encrypted: false,
            disableStats: true,
            enabledTransports: ['ws', 'wss']
        };
    }
    
    // For production - use compiled environment variables
    return {
        app_key: process.env.MIX_VITE_REVERB_APP_KEY || 'ez8fmlurx5ekx7vdiocj',
        host: process.env.MIX_VITE_REVERB_HOST || 'the-thinking-course.com',
        port: parseInt(process.env.MIX_VITE_REVERB_PORT) || 443,
        scheme: process.env.MIX_VITE_REVERB_SCHEME || 'https',
        forceTLS: (process.env.MIX_VITE_REVERB_SCHEME || 'https') === 'https',
        encrypted: true,
        disableStats: true,
        enabledTransports: ['ws', 'wss']
    };
};

// Create and configure WebSocket settings
const config = getWebSocketConfig();
window.webSocketConfig = config;

// Create Echo instance with environment-aware configuration
const isLocal = window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1';



window.Echo = new Echo({
    broadcaster: 'reverb',
    key: config.app_key,
    wsHost: config.host,
    wsPort: config.port,
    wssPort: config.port,
    forceTLS: config.forceTLS,
    encrypted: config.encrypted,
    enabledTransports: config.enabledTransports,
    disableStats: config.disableStats,
    authEndpoint: '/broadcasting/auth',
    auth: {
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
            'Accept': 'application/json',
            'Content-Type': 'application/json'
        }
    }
});



/**
 * Preview Chat Module
 * Handles chat functionality for the preview-chat page
 */
window.PreviewChat = (function() {
    // Private variables
    let currentAttemptId = null;
    let currentStepId = null;
    let isProcessing = false;
    let isChatStarted = false;
    let isSessionCompleted = false;
    let isContinueMode = false;
    
    // Audio recording variables
    let mediaRecorder = null;
    let audioChunks = [];
    let recordingSessionId = null;
    let isRecording = false;
    let recordingTimeout = null;
    
    // API Token management
    let apiToken = null;
    
    // Excluded variables that should never be sent from preview-chat
    const EXCLUDED_VARS = new Set([
        'journey_description', 'student_email', 'institution_name', 'journey_title',
        'current_step', 'previous_step', 'next_step'
    ]);

    // Private methods
    function initializeFromDataAttributes() {
        const previewDataEl = document.getElementById('preview-data');
        if (!previewDataEl) return;
        
        currentAttemptId = previewDataEl.dataset.attemptId || null;
        currentStepId = previewDataEl.dataset.stepId || null;
        isChatStarted = (previewDataEl.dataset.isStarted === '1');
        isSessionCompleted = (previewDataEl.dataset.isCompleted === '1');
        isContinueMode = isChatStarted;
    }

    function canSendMessages() {
        return !isSessionCompleted;
    }

    function updateChatControls() {
        const sendButton = document.getElementById('sendButton');
        const micButton = document.getElementById('micButton');
        const userInput = document.getElementById('userInput');
        
        if (!sendButton || !userInput) return;
        
        if (isSessionCompleted) {
            sendButton.disabled = true;
            if (micButton) micButton.disabled = true;
            userInput.disabled = true;
            userInput.readOnly = true;
            userInput.placeholder = 'This session is completed - no more messages allowed';
        } else if (isChatStarted) {
            sendButton.disabled = false;
            if (micButton) micButton.disabled = false;
            userInput.disabled = false;
            userInput.readOnly = false;
            userInput.placeholder = 'Type your message...';
        }
    }

    // Utility function to synchronize send and mic button states
    function setSendAndMicButtonStates(disabled) {
        const sendButton = document.getElementById('sendButton');
        const micButton = document.getElementById('micButton');
        
        if (sendButton) sendButton.disabled = disabled;
        if (micButton) micButton.disabled = disabled;
    }

    function collectVariables() {
        const variables = {};
        const inputs = document.querySelectorAll('.variable-input');
        
        inputs.forEach(input => {
            let varName = '';
            if (input.id.startsWith('profile_')) {
                varName = input.id.replace('profile_', '');
            } else if (input.id.startsWith('var_')) {
                varName = input.id.replace('var_', '');
            }
            
            if (varName && !EXCLUDED_VARS.has(varName) && input.value.trim()) {
                variables[varName] = input.value.trim();
            }
        });
        
        return variables;
    }

    function disableVariableInputs() {
        const inputs = document.querySelectorAll('.variable-input');
        inputs.forEach(input => {
            input.disabled = true;
        });
        
        const journeySelect = document.getElementById('journeyId');
        if (journeySelect) journeySelect.disabled = true;
    }

    function enableVariableInputs() {
        const inputs = document.querySelectorAll('.variable-input');
        inputs.forEach(input => {
            input.disabled = false;
        });
        
        const journeySelect = document.getElementById('journeyId');
        if (journeySelect) journeySelect.disabled = false;
    }

    function addMessage(content, type) {
        return window.StreamingUtils.addMessage(content, type, 'chatContainer');
    }

    function addStepInfo(stepData) {
        const chatContainer = document.getElementById('chatContainer');
        if (!chatContainer) return;
        
        const stepDiv = document.createElement('div');
        stepDiv.className = 'step-info';
        
        let stepContent = '';
        
        if (stepData.order && stepData.total_steps) {
            stepContent += `<span class="badge bg-primary">Step ${stepData.order}/${stepData.total_steps}</span>`;
        } else if (stepData.order) {
            stepContent += `<span class="badge bg-primary">Step ${stepData.order}</span>`;
        }
        
        if (stepData.attempt_count && stepData.total_attempts) {
            if (stepContent) stepContent += ' ';
            stepContent += `<span class="badge bg-info">Attempt ${stepData.attempt_count}/${stepData.total_attempts}</span>`;
        }
        
        if (stepData.rating) {
            if (stepContent) stepContent += ' ';
            const stars = '★'.repeat(Math.max(0, Math.min(5, stepData.rating))) + '☆'.repeat(Math.max(0, 5 - Math.max(0, stepData.rating)));
            stepContent += `<span class="badge bg-warning">${stars} ${stepData.rating}/5</span>`;
        }
        
        if (stepData.title) {
            if (stepContent) stepContent += ' ';
            stepContent += `<strong>${stepData.title}</strong>`;
        }
        
        if (!stepContent.trim()) {
            stepContent = '<span class="badge bg-secondary">Current Step</span>';
        }
        
        stepDiv.innerHTML = stepContent;
        chatContainer.appendChild(stepDiv);
        chatContainer.scrollTop = chatContainer.scrollHeight;
    }

    function addFeedbackInfo(rating, action, extraData = {}) {
        const chatContainer = document.getElementById('chatContainer');
        if (!chatContainer) return;
        
        const feedbackDiv = document.createElement('div');
        feedbackDiv.className = `feedback-info action-${action}`;
        
        let content = '';
        
        if (rating !== null && rating !== undefined) {
            const stars = '★'.repeat(Math.max(0, Math.min(5, rating))) + '☆'.repeat(Math.max(0, 5 - Math.max(0, rating)));
            content += `<strong>Rating:</strong> ${stars} (${rating}/5)<br>`;
        }
        
        if (extraData.step_attempt_count && extraData.step_max_attempts) {
            content += `<strong>Attempt:</strong> ${extraData.step_attempt_count}/${extraData.step_max_attempts}<br>`;
        }
        
        const actionLabels = {
            'finish_journey': '🎉 Journey Completed!',
            'next_step': '➡️ Moving to Next Step',
            'retry_step': '🔄 Retrying Current Step'
        };
        
        content += `<strong>Action:</strong> ${actionLabels[action] || action}`;
        
        if (action === 'next_step' && extraData.next_step) {
            content += `<br><strong>Next:</strong> Step ${extraData.progressed_to_order} - ${extraData.next_step.title || 'Next Step'}`;
        }
        
        feedbackDiv.innerHTML = content;
        chatContainer.appendChild(feedbackDiv);
        chatContainer.scrollTop = chatContainer.scrollHeight;
    }

    async function handleStreamResponse(response) {
        const reader = response.body.getReader();
        const decoder = new TextDecoder();
        let aiMessageDiv = null;
        let hasReceivedContent = false;
        let aiHtmlBuffer = '';
        let sseBuffer = '';

        addMessage('🔄 Processing response...', 'system');

        while (true) {
            const { done, value } = await reader.read();
            if (done) {
                if (sseBuffer.trim()) {
                    const trimmedBuffer = sseBuffer.trim();
                    processSseMessage(trimmedBuffer);
                    sseBuffer = '';
                }
                if (!hasReceivedContent) addMessage('⚠️ No content received from AI', 'system');
                break;
            }
            
            sseBuffer += decoder.decode(value, { stream: true });
            let sepIndex;
            while ((sepIndex = sseBuffer.indexOf('\n\n')) !== -1) {
                const rawMsg = sseBuffer.slice(0, sepIndex);
                sseBuffer = sseBuffer.slice(sepIndex + 2);
                processSseMessage(rawMsg);
            }
        }

        function processSseMessage(rawMsg) {
            const lines = rawMsg.split('\n');
            let dataLines = [];
            for (const line of lines) {
                const trimmed = line.trim();
                if (!trimmed) continue;
                if (trimmed.startsWith('event:')) {
                    continue;
                }
                if (trimmed.startsWith('data:')) {
                    dataLines.push(trimmed.substring(5).trimStart());
                }
            }
            if (dataLines.length === 0) return;
            const data = dataLines.join('\n').trim();
            
            if (!data) return;
            
            if (data === '[DONE]') {
                if (hasReceivedContent) addMessage('✅ Response completed', 'system');
                return;
            }
            try {
                const parsed = JSON.parse(data);
                
                if (parsed.type === 'done') {
                    // Finalize the streaming message with complete content
                    if (aiHtmlBuffer) {
                        window.StreamingUtils.finalizeStreamingMessage(aiHtmlBuffer, 'chatContainer');
                        console.log('✅ PreviewChat message finalized with', aiHtmlBuffer.length, 'characters');
                    }
                    
                    if (hasReceivedContent) addMessage('✅ Response completed', 'system');
                    
                    if (parsed.action && (parsed.rating !== null && parsed.rating !== undefined)) {
                        addFeedbackInfo(parsed.rating, parsed.action, {
                            next_step: parsed.next_step,
                            progressed_to_order: parsed.progressed_to_order,
                            current_step_order: parsed.current_step_order,
                            total_steps: parsed.total_steps,
                            step_attempt_count: parsed.step_attempt_count,
                            step_max_attempts: parsed.step_max_attempts
                        });
                    }
                    
                    if (parsed.is_complete) {
                        const previewData = document.getElementById('preview-data');
                        if (previewData) {
                            previewData.setAttribute('data-is-completed', 'true');
                            previewData.setAttribute('data-attempt-status', 'completed');
                        }
                        isSessionCompleted = true;
                        updateChatControls();
                        addMessage('🎉 Journey completed! No further messages can be sent.', 'system');
                    }
                    
                    // Reset variables for next message
                    aiHtmlBuffer = '';
                    aiMessageDiv = null;
                    return;
                }
                
                if (parsed.type === 'metadata' || (parsed.step_id && !parsed.text && !parsed.error)) {
                    const oldStepId = currentStepId;
                    currentStepId = parsed.step_id;
                    if (parsed.attempt_id) currentAttemptId = parsed.attempt_id;
                    
                    if (currentStepId && currentStepId !== oldStepId) {
                        addStepInfo({
                            order: parsed.step_order,
                            title: parsed.step_title,
                            total_steps: parsed.total_steps,
                            attempt_count: parsed.attempt_count,
                            total_attempts: parsed.total_attempts
                        });
                    }
                    return;
                }
                
                // Handle the new streaming format: {text: '...', type: 'chunk', index: ...}
                if (parsed.type === 'chunk' && parsed.text) {
                    hasReceivedContent = true;
                    aiHtmlBuffer += parsed.text;
                    
                    // Use the improved streaming function with video support
                    aiMessageDiv = window.StreamingUtils.updateStreamingMessage(aiHtmlBuffer, 'ai', 'chatContainer');
                    
                    console.log('📝 PreviewChat chunk - Buffer length:', aiHtmlBuffer.length, 'Chunk:', parsed.text.substring(0, 50) + '...');
                    return; // Important: return early to avoid duplicate processing
                }
                
                // Handle legacy content format for backward compatibility
                if (parsed.type === 'content' && parsed.delta) {
                    hasReceivedContent = true;
                    aiHtmlBuffer += parsed.delta;
                    
                    // Use the improved streaming function with video support
                    aiMessageDiv = window.StreamingUtils.updateStreamingMessage(aiHtmlBuffer, 'ai', 'chatContainer');
                    
                    console.log('📝 PreviewChat delta - Buffer length:', aiHtmlBuffer.length, 'Delta:', parsed.delta.substring(0, 50) + '...');
                    return; // Important: return early to avoid duplicate processing
                }
                
                // Handle legacy text format (only if not already handled above)
                if (parsed.text && !parsed.type) {
                    hasReceivedContent = true;
                    aiHtmlBuffer += parsed.text;
                    
                    // Use the improved streaming function with video support
                    aiMessageDiv = window.StreamingUtils.updateStreamingMessage(aiHtmlBuffer, 'ai', 'chatContainer');
                    
                    console.log('📝 PreviewChat legacy text - Buffer length:', aiHtmlBuffer.length, 'Text:', parsed.text.substring(0, 50) + '...');
                }
                if (parsed.error) addMessage(`Error: ${parsed.error.message || parsed.error}`, 'error');
            } catch (e) {
                if (data && data.length > 0) {
                    console.error('Error parsing SSE data:', e, 'Raw:', data);
                    if (data.length > 5) {
                        addMessage(`⚠️ Received malformed data from server (${data.length} chars)`, 'error');
                    }
                }
            }
        }
    }

    // Audio recording functions
    async function initAudioRecording() {
        try {
            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                throw new Error('Audio recording not supported in this browser');
            }

            // Clean up existing MediaRecorder and stream if they exist
            if (mediaRecorder && mediaRecorder.stream) {
                mediaRecorder.stream.getTracks().forEach(track => track.stop());
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
                for (let i = 0; i < audioChunks.length; i++) {
                    const isLastChunk = (i === audioChunks.length - 1);
                    await sendAudioChunk(audioChunks[i], i, isLastChunk);
                }
                await completeAudioRecording();
            };

            return true;
        } catch (error) {
            console.error('Error initializing audio recording:', error);
            addMessage('Error: Could not access microphone. Please check permissions.', 'error');
            return false;
        }
    }

    async function sendAudioChunk(audioBlob, chunkNumber, isFinal = false) {
        if (!recordingSessionId) return;

        try {
            const arrayBuffer = await audioBlob.arrayBuffer();
            const uint8Array = new Uint8Array(arrayBuffer);
            const base64 = btoa(String.fromCharCode.apply(null, uint8Array));

            const response = await fetch('/audio/process-chunk', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    session_id: recordingSessionId,
                    audio_data: base64,
                    chunk_number: chunkNumber,
                    is_final: isFinal
                })
            });

            if (!response.ok) {
                console.error('Failed to send audio chunk:', response.statusText);
            }
        } catch (error) {
            console.error('Error sending audio chunk:', error);
        }
    }

    async function completeAudioRecording() {
        if (!recordingSessionId) return;

        try {
            const response = await fetch('/audio/complete', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    session_id: recordingSessionId
                })
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }

            pollForTranscription();
        } catch (error) {
            console.error('Error completing recording:', error);
            addMessage('Error: Failed to complete recording - ' + error.message, 'error');
        }
    }

    async function pollForTranscription() {
        if (!recordingSessionId) return;

        const maxAttempts = 30;
        let attempts = 0;

        const poll = async () => {
            try {
                const response = await fetch(`/audio/transcription/${recordingSessionId}`, {
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Accept': 'application/json'
                    }
                });

                if (!response.ok) {
                    const errorData = await response.json().catch(() => ({}));
                    throw new Error(errorData.error || `HTTP ${response.status}: ${response.statusText}`);
                }

                const data = await response.json();

                if (data.status === 'completed' && data.transcription) {
                    const userInput = document.getElementById('userInput');
                    if (userInput) {
                        const currentValue = userInput.value.trim();
                        const newValue = currentValue ? currentValue + ' ' + data.transcription : data.transcription;
                        userInput.value = newValue;
                    }
                    
                    const statusText = document.querySelector('#audio-status .status-text');
                    if (statusText) {
                        statusText.textContent = 'Ready';
                        statusText.style.color = 'green';
                    }
                    
                    addMessage('✅ Transcription complete: "' + data.transcription + '"', 'system');
                    recordingSessionId = null;
                    
                    if (userInput && userInput.value.length > 0) {
                        addMessage('🚀 Auto-submitting transcribed message...', 'system');
                        setTimeout(() => {
                            PreviewChat.sendMessage();
                        }, 1000);
                    }
                    return;
                } 
                
                if (data.status === 'failed') {
                    addMessage('❌ Transcription failed. Please try recording again.', 'error');
                    recordingSessionId = null;
                    return;
                }

                attempts++;
                if (attempts < maxAttempts) {
                    setTimeout(poll, 1000);
                } else {
                    addMessage('⏰ Transcription timeout. Please try again.', 'error');
                    recordingSessionId = null;
                }
            } catch (error) {
                console.error('Error polling transcription:', error);
                addMessage('Error: Failed to get transcription - ' + error.message, 'error');
                recordingSessionId = null;
            }
        };

        poll();
    }

    // Subscribe to audio session channel
    function subscribeToAudioChannel(sessionId) {
        if (!sessionId || !window.pusherInstance) return;
        
        try {
            const audioChannel = window.pusherInstance.subscribe('private-audio-session.' + sessionId);
            audioChannel.bind('App\\Events\\AudioChunkReceived', function(data) {
                console.log('Audio chunk received via WebSocket:', data);
                const statusEl = document.querySelector('#audio-status .status-text');
                if (statusEl) {
                    statusEl.textContent = `Chunk #${data.chunk_number} received`;
                }
            });
        } catch (error) {
            console.warn('Failed to subscribe to audio channel:', error);
        }
    }

    // Public API
    return {
        // Initialize the preview chat functionality
        init: function() {
            initializeFromDataAttributes();
            updateChatControls();
            
            // Setup event listeners
            const micButton = document.getElementById('micButton');
            if (micButton) {
                micButton.addEventListener('click', function() {
                    if (!canSendMessages()) {
                        addMessage('Error: This session is completed - no more input allowed', 'error');
                        return;
                    }

                    if (!isChatStarted) {
                        addMessage('Error: Please start a chat session first', 'error');
                        return;
                    }

                    if (isRecording) {
                        PreviewChat.stopAudioRecording();
                    } else {
                        PreviewChat.startAudioRecording();
                    }
                });
            }
        },

        // Start a new chat session
        startChat: async function() {
            if (isProcessing || isChatStarted) {
                return;
            }
            
            const journeyId = document.getElementById('journeyId')?.value;
            if (!journeyId) {
                alert('Please select a Journey');
                return;
            }

            if (isContinueMode && currentAttemptId) {
                isChatStarted = true;
                updateChatControls();
                if (isSessionCompleted) {
                    addMessage('💬 This chat session has been completed. No more messages can be sent.', 'system');
                } else {
                    addMessage('💬 Chat session resumed. You can continue the conversation.', 'system');
                }
                return;
            }

            const variables = collectVariables();

            isProcessing = true;
            const startButton = document.querySelector('button.btn.btn-primary.me-2');
            if (startButton) startButton.setAttribute('disabled', 'disabled');
            
            setSendAndMicButtonStates(true);
            const userInput = document.getElementById('userInput');
            if (userInput) userInput.disabled = true;
            
            disableVariableInputs();

            try {
                addMessage('Starting chat session with variables...', 'system');
                
                if (Object.keys(variables).length > 0) {
                    addMessage(`Variables: ${JSON.stringify(variables, null, 2)}`, 'system');
                }

                const payload = { 
                    journey_id: parseInt(journeyId),
                    variables: variables,
                    variables_json: JSON.stringify(variables)
                };

                const response = await fetch('/api/chat/start-web', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'text/event-stream, application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify(payload)
                });

                const ct = response.headers.get('content-type') || '';
                if (!ct.includes('text/event-stream')) {
                    const preview = await response.text();
                    console.error('Non-SSE response from start:', response.status, ct, preview.slice(0, 400));
                    addMessage('❌ Unexpected response (not a stream). Check authentication and token. See console for details.', 'error');
                    throw new Error(`Unexpected content-type: ${ct}`);
                }

                if (!response.ok) {
                    const errorText = await response.text();
                    throw new Error(`HTTP ${response.status}: ${errorText}`);
                }

                addMessage('✅ Chat session started successfully!', 'system');
                isChatStarted = true;
                await handleStreamResponse(response);

            } catch (error) {
                console.error('Start chat error:', error);
                addMessage(`Error starting chat: ${error.message}`, 'error');
                isChatStarted = false;
                enableVariableInputs();
            } finally {
                isProcessing = false;
                if (startButton) startButton.removeAttribute('disabled');
                setSendAndMicButtonStates(false);
                if (userInput) userInput.disabled = false;
            }
        },

        // Send a message
        sendMessage: async function() {
            const userInput = document.getElementById('userInput');
            if (!userInput) return;
            
            const userInputValue = userInput.value.trim();

            if (!canSendMessages()) {
                alert('This chat session has been completed. No more messages can be sent.');
                return;
            }

            if (!userInputValue) {
                alert('Please enter a message');
                return;
            }

            if (!currentAttemptId) {
                alert('Please start a chat session first');
                return;
            }

            isProcessing = true;
            setSendAndMicButtonStates(true);
            userInput.disabled = true;

            try {
                addMessage(userInputValue, 'user');
                userInput.value = '';

                const payload = {
                    attempt_id: currentAttemptId,
                    user_input: userInputValue
                };

                if (currentStepId) {
                    payload.step_id = currentStepId;
                }

                const response = await fetch('/api/chat/submit-web', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'text/event-stream',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify(payload)
                });

                if (!response.ok) {
                    const errorText = await response.text();
                    throw new Error(`HTTP ${response.status}: ${errorText}`);
                }

                await handleStreamResponse(response);

            } catch (error) {
                console.error('Send message error:', error);
                addMessage(`Error sending message: ${error.message}`, 'error');
            } finally {
                isProcessing = false;
                setSendAndMicButtonStates(false);
                userInput.disabled = false;
            }
        },

        // Clear the chat
        clearChat: function() {
            const journeyId = document.getElementById('journeyId')?.value;
            
            if (journeyId) {
                window.location.href = `/preview-chat?journey=${journeyId}`;
            } else {
                const chatContainer = document.getElementById('chatContainer');
                if (chatContainer) {
                    chatContainer.innerHTML = '<p class="text-muted">Chat cleared. Click "Start Chat" to begin...</p>';
                }
                currentAttemptId = null;
                currentStepId = null;
                isChatStarted = false;
                
                if (!isContinueMode) {
                    enableVariableInputs();
                }
                updateChatControls();
            }
        },

        // Handle key press events
        handleKeyPress: function(event) {
            if (event.key === 'Enter' && !isProcessing) {
                PreviewChat.sendMessage();
            }
        },

        // Start audio recording
        startAudioRecording: async function() {
            if (isRecording) {
                PreviewChat.stopAudioRecording();
                return;
            }

            if (!currentAttemptId) {
                addMessage('Error: No active journey session. Please start a chat first.', 'error');
                return;
            }

            try {
                // Initialize or reset MediaRecorder if needed
                if (!mediaRecorder || mediaRecorder.state === 'recording') {
                    const success = await initAudioRecording();
                    if (!success) return;
                } else if (mediaRecorder.state !== 'inactive') {
                    // If MediaRecorder is in an unexpected state, reinitialize it
                    const success = await initAudioRecording();
                    if (!success) return;
                }

                recordingSessionId = 'audio_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);

                const response = await fetch('/audio/start-recording', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        journey_attempt_id: currentAttemptId,
                        journey_step_id: currentStepId,
                        session_id: recordingSessionId
                    })
                });

                if (!response.ok) {
                    const errorData = await response.json().catch(() => ({}));
                    throw new Error(errorData.error || `HTTP ${response.status}: ${response.statusText}`);
                }

                // Only start if MediaRecorder is in inactive state
                if (mediaRecorder.state === 'inactive') {
                    mediaRecorder.start(1000);
                    isRecording = true;
                } else {
                    throw new Error(`MediaRecorder is in ${mediaRecorder.state} state, cannot start recording`);
                }

                const micButton = document.getElementById('micButton');
                if (micButton) {
                    micButton.innerHTML = '🔴';
                    micButton.title = 'Stop Recording (Max 30s)';
                    micButton.classList.add('btn-danger');
                    micButton.classList.remove('btn-outline-secondary');
                }
                
                const statusText = document.querySelector('#audio-status .status-text');
                if (statusText) {
                    statusText.textContent = 'Recording...';
                    statusText.style.color = 'red';
                }
                
                // Subscribe to WebSocket audio channel (skip if function doesn't exist)
                try {
                    if (typeof subscribeToAudioChannel === 'function') {
                        subscribeToAudioChannel(recordingSessionId);
                    }
                } catch (channelError) {
                    console.warn('Could not subscribe to audio channel:', channelError);
                    // Don't fail the recording for WebSocket issues
                }
                
                addMessage('🎤 Recording started... (Maximum 30 seconds)', 'system');

                recordingTimeout = setTimeout(() => {
                    PreviewChat.stopAudioRecording();
                    addMessage('⏰ Recording stopped - 30 second limit reached', 'system');
                }, 30000);

            } catch (error) {
                console.error('Error starting recording:', error);
                addMessage('Error: Failed to start recording - ' + error.message, 'error');
                
                isRecording = false;
                recordingSessionId = null;
                
                const micButton = document.getElementById('micButton');
                if (micButton) {
                    micButton.innerHTML = '🎤';
                    micButton.title = 'Voice Input';
                    micButton.classList.remove('btn-danger');
                    micButton.classList.add('btn-outline-secondary');
                }
            }
        },

        // Stop audio recording
        stopAudioRecording: async function() {
            if (!isRecording || !mediaRecorder) return;

            try {
                isRecording = false;
                
                if (recordingTimeout) {
                    clearTimeout(recordingTimeout);
                    recordingTimeout = null;
                }

                // Only stop if MediaRecorder is actually recording
                if (mediaRecorder.state === 'recording') {
                    mediaRecorder.stop();
                }

                const micButton = document.getElementById('micButton');
                if (micButton) {
                    micButton.innerHTML = '🎤';
                    micButton.title = 'Voice Input';
                    micButton.classList.remove('btn-danger');
                    micButton.classList.add('btn-outline-secondary');
                }
                
                const statusText = document.querySelector('#audio-status .status-text');
                if (statusText) {
                    statusText.textContent = 'Processing...';
                    statusText.style.color = 'orange';
                }
                
                addMessage('🎤 Recording stopped. Processing...', 'system');

            } catch (error) {
                console.error('Error stopping recording:', error);
                addMessage('Error: Failed to stop recording - ' + error.message, 'error');
                
                isRecording = false;
                const micButton = document.getElementById('micButton');
                if (micButton) {
                    micButton.innerHTML = '🎤';
                    micButton.title = 'Voice Input';
                    micButton.classList.remove('btn-danger');
                    micButton.classList.add('btn-outline-secondary');
                }
            }
        },

        // Add message to chat container
        addMessage: function(content, type) {
            const chatContainer = document.getElementById('chatContainer');
            if (!chatContainer) return;
            
            const messageDiv = document.createElement('div');
            messageDiv.className = `message ${type}-message`;
            messageDiv.innerHTML = content;
            chatContainer.appendChild(messageDiv);
            chatContainer.scrollTop = chatContainer.scrollHeight;
        }
    };
})();

// Initialize PreviewChat when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    // Only initialize if we're on the preview-chat page
    if (document.getElementById('preview-data')) {
        PreviewChat.init();
        
        // Handle preset messages loading
        const previewData = document.getElementById('preview-data');
        if (previewData && previewData.dataset.presetMessages) {
            try {
                const presetMessages = JSON.parse(previewData.dataset.presetMessages);
                if (presetMessages && presetMessages.length > 0) {
                    const chatContainer = document.getElementById('chatContainer');
                    if (chatContainer) {
                        chatContainer.innerHTML = '';
                        presetMessages.forEach(message => {
                            PreviewChat.addMessage(message.content, message.type);
                        });
                    }
                }
            } catch (e) {
                console.error('Error parsing preset messages:', e);
            }
        }
    }
    
    // WebSocket status handling for authenticated users
    if (window.Echo && window.Echo.connector && window.Echo.connector.pusher) {
        const updateStatus = (status, color) => {
            const statusEl = document.querySelector('#websocket-status .status-text');
            if (statusEl) {
                statusEl.textContent = status;
                statusEl.style.color = color;
            }
        };

        window.Echo.connector.pusher.connection.bind('connected', () => {
            console.log('WebSocket status: Connected');
            updateStatus('Connected (Authenticated)', 'green');
        });

        window.Echo.connector.pusher.connection.bind('disconnected', () => {
            console.log('WebSocket status: Disconnected');
            updateStatus('Disconnected', 'red');
        });

        window.Echo.connector.pusher.connection.bind('error', (err) => {
            console.error('WebSocket status: Error', err);
            if (err.error && err.error.data && err.error.data.code === 4009) {
                updateStatus('Authentication Failed', 'red');
            } else {
                updateStatus('Connection Error', 'red');
            }
        });
    } else {
        // Handle unauthenticated or missing Echo
        const statusEl = document.querySelector('#websocket-status .status-text');
        if (statusEl) {
            statusEl.textContent = 'Authentication Required';
            statusEl.style.color = 'orange';
        }
    }
});

// Shared Rendering Utilities for both JourneyStep and PreviewChat modules
window.StreamingUtils = (function() {
    
    /**
     * Enhanced streaming message update with video preservation
     * @param {string} content - The HTML content to display
     * @param {string} type - Message type (ai, user, etc.)
     * @param {string} containerId - ID of the container element
     * @returns {HTMLElement|null} - The updated streaming message element
     */
    function updateStreamingMessage(content, type, containerId = 'chatContainer') {
        const container = document.getElementById(containerId);
        if (!container) {
            console.error('Chat container not found for streaming!', containerId);
            return null;
        }
        
        // Safety check: remove any duplicate streaming messages (should never happen, but just in case)
        const existingStreamingMessages = container.querySelectorAll('.streaming-message');
        if (existingStreamingMessages.length > 1) {
            console.warn('🚨 Multiple streaming messages found! Cleaning up duplicates...');
            // Keep only the last one, remove others
            for (let i = 0; i < existingStreamingMessages.length - 1; i++) {
                existingStreamingMessages[i].remove();
            }
        }
        
        // Check if we have a streaming message in progress
        let streamingMessage = container.querySelector('.streaming-message');
        
        if (!streamingMessage) {
            // Create new streaming message
            streamingMessage = document.createElement('div');
            streamingMessage.className = `message ${type}-message streaming-message`;
            // Add a subtle animation/indicator for streaming
            streamingMessage.style.borderLeft = '3px solid #007bff';
            streamingMessage.style.backgroundColor = '#f8f9fa';
            streamingMessage.innerHTML = content;
            container.appendChild(streamingMessage);
            
            console.log('🔧 Created new streaming message');
        } else {
            // Update existing streaming message
            console.log('🔧 Updating existing streaming message');
            
            // Check if we already have video content loaded and the new content also has video
            const existingVideo = streamingMessage.querySelector('video, iframe');
            const hasCompleteVideo = content.includes('</video>') || content.includes('</iframe>');
            
            if (existingVideo && hasCompleteVideo) {
                // We have loaded video, use surgical updates to avoid flickering
                preserveVideoWhileUpdating(streamingMessage, content);
            } else if (!existingVideo && hasCompleteVideo) {
                // First time getting complete video, do full update
                streamingMessage.innerHTML = content;
                streamingMessage.setAttribute('data-has-video', 'true');
            } else {
                // No video yet or still building up content, do normal update
                streamingMessage.innerHTML = content;
            }
        }
        
        // Auto-scroll to show new content immediately
        requestAnimationFrame(() => {
            container.scrollTop = container.scrollHeight;
        });
        
        return streamingMessage;
    }

    /**
     * Preserve video elements while updating surrounding content
     * @param {HTMLElement} streamingMessage - The streaming message element
     * @param {string} newContent - The new HTML content
     */
    function preserveVideoWhileUpdating(streamingMessage, newContent) {
        // Create a temporary element to parse new content
        const tempDiv = document.createElement('div');
        tempDiv.innerHTML = newContent;
        const newVideo = tempDiv.querySelector('video, iframe');
        const existingVideo = streamingMessage.querySelector('video, iframe');
        
        // Only update if it's the same video source or if there's no existing video
        if (newVideo && existingVideo) {
            const isSameVideo = (
                (newVideo.tagName === 'VIDEO' && existingVideo.tagName === 'VIDEO' && newVideo.src === existingVideo.src) ||
                (newVideo.tagName === 'IFRAME' && existingVideo.tagName === 'IFRAME' && newVideo.src === existingVideo.src)
            );
            
            if (isSameVideo) {
                // Just update the text content areas, preserve the video
                const videoContainer = tempDiv.querySelector('.ainode-video, .video-container');
                if (videoContainer) {
                    videoContainer.remove(); // Remove video container from temp div
                }
                
                // Get the existing video container
                const existingVideoContainer = streamingMessage.querySelector('.ainode-video, .video-container');
                
                // Clear all content except preserve video reference
                const videoHtml = existingVideoContainer ? existingVideoContainer.outerHTML : '';
                streamingMessage.innerHTML = '';
                
                // Add back the existing video container first
                if (videoHtml) {
                    streamingMessage.insertAdjacentHTML('afterbegin', videoHtml);
                }
                
                // Add all the new non-video content
                while (tempDiv.firstChild) {
                    streamingMessage.appendChild(tempDiv.firstChild);
                }
                
                return;
            }
        }
        
        // Different video source or other case, do full update
        streamingMessage.innerHTML = newContent;
    }

    /**
     * Finalize a streaming message by removing streaming indicators and ensuring complete rendering
     * @param {string} finalContent - The final complete content
     * @param {string} containerId - ID of the container element
     */
    function finalizeStreamingMessage(finalContent, containerId = 'chatContainer') {
        const container = document.getElementById(containerId);
        const streamingMessage = container?.querySelector('.streaming-message');
        
        if (streamingMessage && finalContent) {
            console.log('🔧 Finalizing streaming message with complete content');
            
            // Remove streaming class and styling
            streamingMessage.classList.remove('streaming-message');
            streamingMessage.style.borderLeft = '';
            streamingMessage.style.backgroundColor = '';
            
            // Replace with the complete content for final rendering
            streamingMessage.innerHTML = finalContent;
            
            // Scroll to show the finalized content
            container.scrollTop = container.scrollHeight;
            
            console.log('✅ Message finalized with', finalContent.length, 'characters');
            return streamingMessage;
        } else if (!streamingMessage && finalContent) {
            // No streaming message found - this means streaming failed, so add a regular message
            console.log('🔧 No streaming message found, adding regular message instead');
            return addMessage(finalContent, 'ai', containerId);
        }
        
        return null;
    }

    /**
     * Add a regular message (non-streaming)
     * @param {string} content - The message content
     * @param {string} type - Message type (ai, user, system, error)
     * @param {string} containerId - ID of the container element
     * @returns {HTMLElement} - The created message element
     */
    function addMessage(content, type, containerId = 'chatContainer') {
        const container = document.getElementById(containerId);
        if (!container) {
            console.error('Chat container not found!', containerId);
            return null;
        }
        
        const messageDiv = document.createElement('div');
        messageDiv.className = `message ${type}-message`;
        messageDiv.innerHTML = content;
        
        container.appendChild(messageDiv);
        container.scrollTop = container.scrollHeight;
        
        return messageDiv;
    }

    return {
        updateStreamingMessage,
        finalizeStreamingMessage,
        addMessage,
        preserveVideoWhileUpdating
    };
})();

// Journey Step Module
window.JourneyStep = (function() {
    // Private variables
    let isProcessing = false;
    let mediaRecorder = null;
    let audioChunks = [];
    let isRecording = false;
    let recordingStartTime = null;
    let stream = null;
    let recordingTimeout = null;

    // Private functions
    function initializeFromDataAttributes() {
        const journeyData = document.getElementById('journey-data');
        if (!journeyData) {
            console.error('Journey data element not found');
            return null;
        }

        return {
            attemptId: journeyData.dataset.attemptId,
            journeyId: journeyData.dataset.journeyId,
            currentStep: journeyData.dataset.currentStep,
            totalSteps: journeyData.dataset.totalSteps,
            mode: journeyData.dataset.mode,
            status: journeyData.dataset.status
        };
    }

    function initializeButtonStates() {
        const data = initializeFromDataAttributes();
        if (!data) return;

        // If journey is completed, ensure both buttons are disabled
        if (data.status === 'completed') {
            setSendAndMicButtonStates(true);
        } else {
            // Ensure both buttons are in sync (enabled by default for active journeys)
            setSendAndMicButtonStates(false);
        }
    }

    function setSendAndMicButtonStates(disabled) {
        const sendButton = document.getElementById('sendButton');
        const micButton = document.getElementById('micButton');
        
        if (sendButton) sendButton.disabled = disabled;
        if (micButton) micButton.disabled = disabled;
    }

    function updateSendButton(processing) {
        const button = document.getElementById('sendButton');
        const micButton = document.getElementById('micButton');
        const text = document.getElementById('sendButtonText');
        const spinner = document.getElementById('sendSpinner');
        
        if (processing) {
            text.textContent = 'Sending...';
            spinner.classList.remove('d-none');
            button.disabled = true;
            if (micButton) micButton.disabled = true;
        } else {
            text.textContent = 'Send';
            spinner.classList.add('d-none');
            button.disabled = false;
            if (micButton) micButton.disabled = false;
        }
    }

    function updateStreamingMessage(content, type) {
        return window.StreamingUtils.updateStreamingMessage(content, type, 'chatContainer');
    }

    function addMessage(content, type, newLine = true) {
        const container = document.getElementById('chatContainer');
        if (!container) {
            console.error('Chat container not found!');
            return;
        }
        
        // If it's a streaming message update, find and update the last message
        if (!newLine && container.children.length > 0) {
            const lastMessage = container.children[container.children.length - 1];
            if (lastMessage && lastMessage.classList.contains(`${type}-message`)) {
                lastMessage.innerHTML = content;
                container.scrollTop = container.scrollHeight;
                return lastMessage; // Return the updated element
            }
        }
        
        return window.StreamingUtils.addMessage(content, type, 'chatContainer');
    }

    function updateProgressBar(currentStep, totalSteps) {
        const progressBar = document.getElementById('progress-bar');
        const progressText = document.getElementById('progress-text');
        
        if (progressBar && progressText && currentStep && totalSteps) {
            const percentage = Math.round((currentStep / totalSteps) * 100);
            
            progressBar.style.width = percentage + '%';
            progressBar.setAttribute('aria-valuenow', currentStep);
            progressBar.setAttribute('aria-valuemin', '0');
            progressBar.setAttribute('aria-valuemax', totalSteps);
            
            progressText.textContent = `Step ${currentStep} of ${totalSteps}`;
            
            console.log(`📊 Progress updated: ${currentStep}/${totalSteps} (${percentage}%)`);
        }
    }

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

    // Public API
    return {
        // Initialize the journey step functionality
        init: function() {
            const data = initializeFromDataAttributes();
            if (!data) return;

            // First check if user is authenticated
            checkAuthentication();
            
            // Initialize progress bar with current data
            if (data.currentStep && data.totalSteps) {
                updateProgressBar(parseInt(data.currentStep), parseInt(data.totalSteps));
            }
            
            // Check if messages were pre-loaded from PHP
            const container = document.getElementById('chatContainer');
            const hasPreloadedMessages = container && container.children.length > 0;
            
            // Scroll to bottom on page load (with small delay to ensure content is rendered)
            if (container) {
                setTimeout(() => {
                    container.scrollTop = container.scrollHeight;
                }, 100);
            }
            
            if (!hasPreloadedMessages) {
                // Only load from API if no messages were pre-loaded
                this.loadExistingMessages();
            }
            
            // Auto-start chat if no messages exist
            setTimeout(() => {
                if (container && container.children.length === 0) {
                    this.startJourneyChat();
                }
            }, 100);
            
            // Ensure button states are synchronized on page load
            initializeButtonStates();

            // Setup event listeners
            const micButton = document.getElementById('micButton');
            if (micButton) {
                micButton.addEventListener('click', () => {
                    if (data.status === 'completed') {
                        return;
                    }

                    if (isRecording) {
                        this.stopAudioRecording();
                    } else {
                        this.startAudioRecording();
                    }
                });
            }

            // Setup keyboard event listener for Enter key
            const messageInput = document.getElementById('messageInput');
            if (messageInput) {
                messageInput.addEventListener('keypress', this.handleKeyPress);
            }

            // Setup sendButton event listener
            const sendButton = document.getElementById('sendButton');
            if (sendButton) {
                sendButton.addEventListener('click', () => {
                    if (data.status === 'completed') {
                        return;
                    }
                    this.sendMessage();
                });
            }
        },

        // Add placeholder methods for now - we'll fill these in next
        loadExistingMessages: async function() {
            // Load existing messages from this attempt's step responses
            const data = initializeFromDataAttributes();
            if (!data) return;
            
            try {
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                if (!csrfToken) {
                    return;
                }
                
                const response = await fetch(`/api/journey-attempts/${data.attemptId}/messages`, {
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    credentials: 'same-origin'
                });
                
                if (response.ok) {
                    const responseData = await response.json();
                    
                    if (responseData.success && responseData.messages && responseData.messages.length > 0) {
                        responseData.messages.forEach((message, index) => {
                            addMessage(message.content, message.type);
                        });
                    }
                } else {
                    console.error('Failed to load messages:', response.status);
                }
            } catch (error) {
                console.error('Error loading existing messages:', error);
            }
        },

        startJourneyChat: async function() {
            if (isProcessing) return;
            
            const data = initializeFromDataAttributes();
            if (!data) return;

            if (data.status === 'completed') {
                addMessage('✅ This journey has been completed!', 'system');
                return;
            }
            
            console.log('🚀 Starting journey chat for first AI message...');
            
            isProcessing = true;
            updateSendButton(true);
            
            try {
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                if (!csrfToken) {
                    throw new Error('CSRF token not found. Please refresh the page.');
                }
                
                console.log('Starting chat with journey_id:', data.journeyId, 'attempt_id:', data.attemptId);
                
                const response = await fetch('/api/chat/start-web', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'text/event-stream',
                        'X-CSRF-TOKEN': csrfToken,
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({
                        journey_id: parseInt(data.journeyId),
                        attempt_id: parseInt(data.attemptId)
                    })
                });

                if (!response.ok) {
                    const errorText = await response.text();
                    console.error('Chat start failed:', response.status, errorText);
                    throw new Error(`Failed to start journey chat: ${response.status} - ${errorText}`);
                }

                console.log('✅ Starting to process streaming response...');
                await this.handleStreamResponse(response);

            } catch (error) {
                console.error('Error starting journey chat:', error);
                addMessage(`❌ Error starting journey chat: ${error.message}`, 'error');
            } finally {
                isProcessing = false;
                updateSendButton(false);
            }
        },

        sendMessage: async function() {
            const messageInput = document.getElementById('messageInput');
            const data = initializeFromDataAttributes();
            
            if (!messageInput || !data || isProcessing) return;

            const message = messageInput.value.trim();
            if (!message) return;

            if (data.status === 'completed') {
                return; // Silently do nothing if completed
            }

            isProcessing = true;
            updateSendButton(true);
            messageInput.disabled = true;

            try {
                // Add user message to UI immediately (for instant feedback)
                addMessage(message, 'user');
                messageInput.value = '';

                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                if (!csrfToken) {
                    return;
                }

                const response = await fetch('/api/chat/submit-web', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'text/event-stream',
                        'X-CSRF-TOKEN': csrfToken,
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({
                        attempt_id: parseInt(data.attemptId),
                        user_input: message
                    })
                });

                if (!response.ok) {
                    console.error('Message sending failed:', response.status);
                    return;
                }

                await this.handleStreamResponse(response);

            } catch (error) {
                console.error('Send message error:', error);
            } finally {
                isProcessing = false;
                updateSendButton(false);
                messageInput.disabled = false;
            }
        },

        handleStreamResponse: async function(response) {
            const reader = response.body.getReader();
            const decoder = new TextDecoder();
            let accumulatedText = '';
            let sseBuffer = '';
            let streamTimeout;
            let readerReleased = false;

            console.log('📡 Starting to handle stream response...');

            // Add timeout for stream reading (60 seconds - increased from 30)
            streamTimeout = setTimeout(() => {
                console.warn('⚠️ Stream timeout reached');
                if (!readerReleased) {
                    try {
                        reader.releaseLock();
                        readerReleased = true;
                    } catch (e) {
                        console.log('Reader already released');
                    }
                }
            }, 600000);

            // Disable inputs during streaming (like PreviewChat)
            const messageInput = document.getElementById('messageInput');
            if (messageInput) messageInput.disabled = true;
            setSendAndMicButtonStates(true);

            try {
                while (true) {
                    const { done, value } = await reader.read();
                    if (done) {
                        console.log('📡 Stream finished, processing any remaining buffer...');
                        // Process any remaining buffer
                        if (sseBuffer.trim()) {
                            processSseMessage(sseBuffer.trim());
                        }
                        break;
                    }

                    sseBuffer += decoder.decode(value, { stream: true });
                    let sepIndex;
                    while ((sepIndex = sseBuffer.indexOf('\n\n')) !== -1) {
                        const rawMsg = sseBuffer.slice(0, sepIndex);
                        sseBuffer = sseBuffer.slice(sepIndex + 2);
                        processSseMessage(rawMsg);
                    }
                }

                function processSseMessage(rawMsg) {
                    const lines = rawMsg.split('\n');
                    let dataLines = [];
                    for (const line of lines) {
                        const trimmed = line.trim();
                        if (!trimmed) continue;
                        if (trimmed.startsWith('data:')) {
                            dataLines.push(trimmed.substring(5).trimStart());
                        }
                    }
                    if (dataLines.length === 0) return;
                    const data = dataLines.join('\n').trim();
                    
                    if (!data || data === '[DONE]') return;
                    
                    try {
                        const parsed = JSON.parse(data);
                        console.log('📥 Received streaming data:', parsed);
                        
                        // Handle the actual streaming format: {text: '...', type: 'chunk', index: ...}
                        if (parsed.type === 'chunk' && parsed.text) {
                            accumulatedText += parsed.text;
                            console.log('📝 Current accumulated text length:', accumulatedText.length);
                            console.log('📝 Chunk received:', parsed.text);
                            console.log('📝 Text preview:', accumulatedText.substring(0, 100) + '...');
                            
                            // Use the improved streaming function
                            const streamingElement = updateStreamingMessage(accumulatedText, 'ai');
                            console.log('📝 Streaming element updated:', streamingElement ? 'SUCCESS' : 'FAILED');
                        }
                        
                        // Also handle the old format for backward compatibility
                        if (parsed.type === 'content' && parsed.delta) {
                            accumulatedText += parsed.delta;
                            console.log('📝 Current accumulated text length:', accumulatedText.length);
                            console.log('📝 Delta received:', parsed.delta);
                            console.log('📝 Text preview:', accumulatedText.substring(0, 100) + '...');
                            
                            // Use the improved streaming function
                            const streamingElement = updateStreamingMessage(accumulatedText, 'ai');
                            console.log('📝 Streaming element updated:', streamingElement ? 'SUCCESS' : 'FAILED');
                        }
                        
                        if (parsed.type === 'done') {
                            console.log('✅ Stream completed:', parsed);
                            
                            // Clear the timeout since we received completion signal
                            if (streamTimeout) {
                                clearTimeout(streamTimeout);
                            }
                            
                            // FINALIZATION: Replace streaming message with complete rendered version
                            if (accumulatedText) {
                                window.StreamingUtils.finalizeStreamingMessage(accumulatedText, 'chatContainer');
                            }
                            
                            // Update progress bar if step progression occurred
                            if (parsed.progressed_to_order && parsed.total_steps) {
                                updateProgressBar(parsed.progressed_to_order, parsed.total_steps);
                                
                                // Update journey data attributes
                                const journeyData = document.getElementById('journey-data');
                                if (journeyData) {
                                    journeyData.setAttribute('data-current-step', parsed.progressed_to_order);
                                }
                            }
                            
                            if (parsed.is_complete) {
                                addMessage('🎉 Congratulations! You have completed this journey!', 'system');
                                // Permanently disable inputs for completed journey
                                if (messageInput) messageInput.disabled = true;
                                setSendAndMicButtonStates(true);
                                
                                // Update journey data status
                                const journeyData = document.getElementById('journey-data');
                                if (journeyData) {
                                    journeyData.setAttribute('data-status', 'completed');
                                }
                                
                                // Reload page after delay to show updated UI
                                setTimeout(() => window.location.reload(), 2000);
                            } else {
                                // Re-enable inputs for continued conversation
                                if (messageInput) messageInput.disabled = false;
                                setSendAndMicButtonStates(false);
                            }
                        }
                    } catch (e) {
                        console.warn('❌ Error parsing SSE data:', e, 'Raw data:', data);
                        
                        // Handle malformed streaming data - common in production
                        if (data && data.length > 10) {
                            // If data looks like complete HTML/JSON, try to display it
                            if (data.includes('<') || data.startsWith('{')) {
                                console.log('🔧 Attempting to handle malformed complete response');
                                try {
                                    // Try to extract meaningful content
                                    if (data.includes('<')) {
                                        // HTML response - extract text content
                                        const tempDiv = document.createElement('div');
                                        tempDiv.innerHTML = data;
                                        const textContent = tempDiv.textContent || tempDiv.innerText || '';
                                        if (textContent.length > 50) {
                                            accumulatedText = textContent;
                                            updateStreamingMessage(accumulatedText, 'ai');
                                        }
                                    } else if (data.startsWith('{')) {
                                        // JSON response - try to parse and extract content
                                        const jsonData = JSON.parse(data);
                                        if (jsonData.text || jsonData.message || jsonData.content) {
                                            accumulatedText = jsonData.text || jsonData.message || jsonData.content;
                                            updateStreamingMessage(accumulatedText, 'ai');
                                        }
                                    }
                                } catch (parseError) {
                                    console.error('Failed to recover malformed data:', parseError);
                                    
                                }
                            }
                        }
                    }
                }
            } catch (error) {
                console.error('Stream reading error:', error);
                addMessage(`❌ Stream error: ${error.message}`, 'error');
            } finally {
                // Clear the stream timeout
                if (streamTimeout) {
                    clearTimeout(streamTimeout);
                }
                
                // Only release reader if not already released
                if (!readerReleased) {
                    try {
                        reader.releaseLock();
                    } catch (e) {
                        console.log('Reader was already released');
                    }
                }
                
                // Always re-enable inputs when streaming ends (unless journey is completed)
                const data = initializeFromDataAttributes();
                if (data && data.status !== 'completed') {
                    const messageInput = document.getElementById('messageInput');
                    if (messageInput) messageInput.disabled = false;
                    setSendAndMicButtonStates(false);
                }
            }
        },

        handleKeyPress: function(event) {
            if (event.key === 'Enter' && !isProcessing) {
                window.JourneyStep.sendMessage();
            }
        },

        startAudioRecording: async function() {
            const micButton = document.getElementById('micButton');
            const recordingIcon = document.getElementById('recordingIcon');
            const recordingText = document.getElementById('recordingText');
            const data = initializeFromDataAttributes();
            
            if (!data || data.status === 'completed' || isRecording || isProcessing) {
                console.log('Recording blocked:', { isRecording, isProcessing, status: data?.status });
                return;
            }

            try {
                // Request audio permissions
                stream = await navigator.mediaDevices.getUserMedia({
                    audio: {
                        echoCancellation: true,
                        noiseSuppression: true,
                        autoGainControl: true,
                        sampleRate: 44100
                    }
                });

                // Create MediaRecorder
                const mimeTypes = [
                    'audio/webm;codecs=opus',
                    'audio/webm',
                    'audio/mp4',
                    'audio/ogg',
                    'audio/wav'
                ];

                let selectedMimeType = null;
                for (const mimeType of mimeTypes) {
                    if (MediaRecorder.isTypeSupported(mimeType)) {
                        selectedMimeType = mimeType;
                        break;
                    }
                }

                if (!selectedMimeType) {
                    throw new Error('No supported audio MIME type found');
                }

                console.log('Using MIME type:', selectedMimeType);

                mediaRecorder = new MediaRecorder(stream, {
                    mimeType: selectedMimeType,
                    audioBitsPerSecond: 128000
                });

                audioChunks = [];
                isRecording = true;

                // Update UI to recording state
                if (micButton) {
                    micButton.classList.add('btn-danger');
                    micButton.classList.remove('btn-secondary');
                }
                if (recordingIcon) {
                    recordingIcon.className = 'fas fa-stop';
                }
                if (recordingText) {
                    recordingText.textContent = 'Stop Recording';
                }

                // Start recording
                recordingStartTime = Date.now();
                mediaRecorder.start();

                // Set timeout for max recording duration (60 seconds)
                recordingTimeout = setTimeout(() => {
                    console.log('Recording timeout reached');
                    this.stopAudioRecording();
                }, 60000);

                // Handle data events
                mediaRecorder.addEventListener('dataavailable', (event) => {
                    if (event.data.size > 0) {
                        audioChunks.push(event.data);
                        console.log('Audio chunk received:', event.data.size, 'bytes');
                    }
                });

                // Handle recording completion
                mediaRecorder.addEventListener('stop', () => {
                    console.log('MediaRecorder stopped, processing audio...');
                    this.processAudioRecording();
                });

                console.log('Recording started');

            } catch (error) {
                console.error('Failed to start recording:', error);
                addMessage(`❌ Recording failed: ${error.message}`, 'error');
                this.resetRecordingState();
            }
        },

        stopAudioRecording: function() {
            if (!isRecording || !mediaRecorder) {
                console.log('Not recording or no MediaRecorder');
                return;
            }

            // Clear the timeout
            if (recordingTimeout) {
                clearTimeout(recordingTimeout);
                recordingTimeout = null;
            }

            // Only stop if recorder is in a valid state
            if (mediaRecorder.state === 'recording' || mediaRecorder.state === 'paused') {
                console.log('Stopping MediaRecorder, current state:', mediaRecorder.state);
                mediaRecorder.stop();
            }

            // Stop all audio tracks
            if (stream) {
                stream.getTracks().forEach(track => {
                    track.stop();
                });
                stream = null;
            }

            this.resetRecordingState();
        },

        resetRecordingState: function() {
            const micButton = document.getElementById('micButton');
            const recordingIcon = document.getElementById('recordingIcon');
            const recordingText = document.getElementById('recordingText');

            isRecording = false;
            recordingStartTime = null;

            // Reset UI to default state
            if (micButton) {
                micButton.classList.remove('btn-danger');
                micButton.classList.add('btn-secondary');
            }
            if (recordingIcon) {
                recordingIcon.className = 'fas fa-microphone';
            }
            if (recordingText) {
                recordingText.textContent = 'Record Audio';
            }
        },

        processAudioRecording: async function() {
            if (audioChunks.length === 0) {
                console.log('No audio chunks to process');
                addMessage('❌ No audio data recorded', 'error');
                return;
            }

            const recordingDuration = recordingStartTime ? (Date.now() - recordingStartTime) / 1000 : 0;
            console.log(`Processing ${audioChunks.length} audio chunks, duration: ${recordingDuration}s`);

            try {
                // Create blob from audio chunks
                const audioBlob = new Blob(audioChunks, { type: audioChunks[0].type });
                console.log('Audio blob created:', audioBlob.size, 'bytes, type:', audioBlob.type);

                if (audioBlob.size === 0) {
                    throw new Error('Audio blob is empty');
                }

                // Send audio to server
                await this.sendAudioMessage(audioBlob);

            } catch (error) {
                console.error('Error processing audio:', error);
                addMessage(`❌ Audio processing failed: ${error.message}`, 'error');
            } finally {
                audioChunks = [];
                mediaRecorder = null;
            }
        },

        sendAudioMessage: async function(audioBlob) {
            const data = initializeFromDataAttributes();
            if (!data || data.status === 'completed') {
                return;
            }

            isProcessing = true;
            updateSendButton(true);

            try {
                // Get fresh CSRF token from page
                let csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                
                // Fallback: try to get from a hidden input if meta tag doesn't exist
                if (!csrfToken) {
                    const csrfInput = document.querySelector('input[name="_token"]');
                    if (csrfInput) {
                        csrfToken = csrfInput.value;
                    }
                }
                
                if (!csrfToken) {
                    return;
                }

                // Create FormData for file upload
                const formData = new FormData();
                formData.append('audio', audioBlob, 'recording.webm');
                formData.append('_token', csrfToken);
                formData.append('session_id', data.attemptId);
                formData.append('journey_attempt_id', data.attemptId);

                // Transcribe the audio silently in background
                const response = await fetch('/audio/transcribe', {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    credentials: 'same-origin',
                    body: formData
                });

                if (!response.ok) {
                    throw new Error(`Transcription failed: ${response.status}`);
                }

                const transcriptionResult = await response.json();

                if (transcriptionResult.success && transcriptionResult.transcription) {
                    // Put transcribed text into the textarea for visual feedback
                    const messageInput = document.getElementById('messageInput');
                    if (messageInput) {
                        messageInput.value = transcriptionResult.transcription;
                        
                        // Show transcribed message in UI immediately
                        addMessage(transcriptionResult.transcription, 'user');
                        messageInput.value = ''; // Clear input after adding to UI
                        
                        // Submit the transcribed message directly to backend
                        const response = await fetch('/api/chat/submit-web', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'text/event-stream',
                                'X-CSRF-TOKEN': csrfToken,
                                'X-Requested-With': 'XMLHttpRequest'
                            },
                            credentials: 'same-origin',
                            body: JSON.stringify({
                                attempt_id: parseInt(data.attemptId),
                                user_input: transcriptionResult.transcription
                            })
                        });

                        if (response.ok) {
                            // Handle the streaming AI response
                            await window.JourneyStep.handleStreamResponse(response);
                        } else {
                            console.error('Message sending failed:', response.status);
                            addMessage('❌ Failed to send transcribed message', 'error');
                        }
                    }
                }

            } catch (error) {
                console.error('Audio transcription error:', error);
            } finally {
                isProcessing = false;
                updateSendButton(false);
            }
        }
    };
})();

// Journey Start Modal Module
// Shared functionality for starting journeys from different pages
window.JourneyStartModal = (function() {
    let selectedJourneyId = null;
    let selectedJourneyType = null;
    
    function showStartJourneyModal(journeyId, journeyTitle, type) {
        selectedJourneyId = journeyId;
        selectedJourneyType = type;
        
        const titleElement = document.getElementById('journeyTitleText');
        const typeElement = document.getElementById('journeyTypeText');
        
        if (titleElement) titleElement.textContent = journeyTitle;
        if (typeElement) typeElement.textContent = type;
        
        const modalElement = document.getElementById('startJourneyModal');
        if (modalElement && window.bootstrap) {
            const modal = new bootstrap.Modal(modalElement);
            modal.show();
        }
    }
    
    async function confirmStartJourney() {
        if (!selectedJourneyId || !selectedJourneyType) {
            return;
        }

        const spinner = document.getElementById('startJourneySpinner');
        const buttonText = document.getElementById('startJourneyText');
        const button = document.getElementById('confirmStartJourney');
        
        // Show loading state
        if (spinner) spinner.classList.remove('d-none');
        if (buttonText) buttonText.textContent = 'Starting...';
        if (button) button.disabled = true;

        try {
            console.log('🚀 Starting journey with session authentication...');
            
            // Get CSRF token for session authentication
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            if (!csrfToken) {
                throw new Error('CSRF token not found. Please refresh the page.');
            }
            
            // Get current user ID from Laravel
            const currentUserId = window.Laravel?.user?.id || document.querySelector('body[data-user-id]')?.dataset.userId || document.body.dataset.userId;
            console.log('🔍 Current user ID:', currentUserId);
            
            if (!currentUserId) {
                throw new Error('User ID not found. Please refresh the page and try again.');
            }
            
            // Use session-based authentication instead of API tokens
            const response = await fetch('/api/start-journey', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                    journey_id: selectedJourneyId,
                    user_id: currentUserId,
                    type: selectedJourneyType
                })
            });

            console.log('🌐 Start journey response status:', response.status, response.statusText);

            if (!response.ok) {
                const errorText = await response.text();
                console.error('❌ Start journey failed:', response.status, errorText);
                throw new Error(`Failed to start journey: ${response.status} - ${errorText}`);
            }

            const data = await response.json();

            if (data.success) {
                console.log('✅ Journey started successfully, redirecting...');
                // Redirect to the journey attempt page using the correct Laravel route
                window.location.href = data.redirect_url;
            } else {
                alert('Error: ' + (data.error || 'Failed to start journey'));
            }
        } catch (error) {




            console.error('💥 Error starting journey:', error);
            alert('Failed to start journey: ' + error.message);
        } finally {
            // Reset button state
            if (spinner) spinner.classList.add('d-none');
            if (buttonText) buttonText.textContent = 'Yes, Start Journey';
            if (button) button.disabled = false;
            
            // Close modal
            const modalElement = document.getElementById('startJourneyModal');
            if (modalElement && window.bootstrap) {
                const modal = bootstrap.Modal.getInstance(modalElement);
                if (modal) modal.hide();
            }
        }
    }
    
    // Initialize the modal functionality
    function init() {
        const confirmButton = document.getElementById('confirmStartJourney');
        if (confirmButton) {
            confirmButton.addEventListener('click', confirmStartJourney);
        }
    }
    
    return {
        showStartJourneyModal,
        init
    };
})();

// Initialize modules when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    // Initialize JourneyStartModal on all pages
    if (window.JourneyStartModal) {
        window.JourneyStartModal.init();
        console.log('✅ JourneyStartModal module initialized');
    }
    
    // Only initialize JourneyStep if we're on the journey step page
    if (document.getElementById('journey-data')) {
        console.log('🎯 Journey step page detected, initializing JourneyStep module...');
        
        JourneyStep.init();
        console.log('✅ JourneyStep module initialized');
        
        // Check if we need to start the journey chat (no messages case)
        const chatContainer = document.getElementById('chatContainer');
        const messages = chatContainer ? chatContainer.querySelectorAll('.message') : [];
        
        console.log('🔍 Found', messages.length, 'existing messages');
        
        if (messages.length === 0) {
            console.log('🚀 No messages found, starting journey chat automatically...');
            JourneyStep.startJourneyChat();
        }
    }
    
    // Initialize Voice page functionality if we're on the voice journey page
    if (document.getElementById('journey-data-voice')) {
        console.log('🎤 Voice journey page detected, initializing voice functionality...');
        window.VoiceMode.init();
    }
});

window.VoiceEcho = new Echo({
    broadcaster: 'reverb',
    key: config.app_key,
    wsHost: config.host,
    wsPort: config.port,
    wssPort: config.port,
    forceTLS: config.forceTLS,
    encrypted: config.encrypted,
    enabledTransports: config.enabledTransports,
    disableStats: config.disableStats,
    authEndpoint: '/broadcasting/auth',
    
});


// Add comprehensive error handling
window.VoiceEcho.connector.pusher.connection.bind('error', function(err) {
    console.error('WebSocket connection error:', err);
    if (err.error && err.error.data && err.error.data.code === 4009) {
        console.error('Voice WebSocket authentication failed. Please log in.');
    }
});

window.VoiceEcho.connector.pusher.connection.bind('connected', function() {
    console.log('Voice WebSocket connected successfully');
});

window.VoiceEcho.connector.pusher.connection.bind('disconnected', function() {
    console.log('Voice WebSocket disconnected');
});


window.VoiceMode = (function() {
    // Private variables for voice streaming
    let isStreaming = false;
    let currentStreamingMessage = null;
    let audioContext = null;
    let audioBuffer = [];
    let audioChunks = [];
    let isPlayingAudio = false;
    let currentAudioSource = null;
    let nextStartTime = 0;
    let sampleRate = 24000; // OpenAI Realtime API uses 24kHz for PCM16

    function init() {
        console.log('🎤 VoiceMode module initialized');
        
        // Initialize Web Audio API
        initializeAudioContext();
        
        // Get voice data container
        const voiceDataContainer = document.getElementById('journey-data-voice');
        if (!voiceDataContainer) {
            console.error('❌ Voice data container not found');
            return;
        }
        
        const attemptId = voiceDataContainer.getAttribute('data-attempt-id');
        if (!attemptId) {
            console.error('❌ Attempt ID not found in voice data container');
            return;
        }
        
        console.log('✅ VoiceMode initialized with attempt ID:', attemptId);
        
        // Subscribe to voice channel and listen for events
        if (!window.VoiceEcho) {
            console.error('❌ VoiceEcho not available');
            return;
        }
        
        try {
            console.log(`🎤 Setting up VoiceMode channel listener for attempt: ${attemptId}`);
            console.log('🔌 VoiceEcho connection state:', window.VoiceEcho.connector.pusher.connection.state);
            
            const channelName = `voice.mode.${attemptId}`;
            const voiceChannel = window.VoiceEcho.private(channelName);
            
            // Add subscription debugging
            voiceChannel.subscribed(() => {
                console.log('✅ VoiceMode - Successfully subscribed to channel:', channelName);
            });
            
            voiceChannel.error((error) => {
                console.error('❌ VoiceMode - Channel subscription error:', error);
            });
            
            voiceChannel.listen('.voice.chunk.sent', (e) => {
                console.log('🎤 VoiceMode - Voice chunk received:', e);
                
                // Ignore chunks with index 0 or less
                if (!e.index || e.index <= 0) {
                    console.log('🎤 VoiceMode - Ignoring chunk with index:', e.index);
                    return;
                }
                
                // Handle text streaming to voiceTextArea
                if (e.type === 'text' && e.message) {
                    streamTextToVoiceArea(e.message, e.index);
                }
                
                // Handle audio chunks for continuous playback
                if (e.type === 'audio' && e.message) {
                    handleAudioChunk(e.message, e.index);
                }
            });
            
            console.log('✅ VoiceMode channel listener setup complete');
            
        } catch (error) {
            console.error('❌ Error setting up VoiceMode channel:', error);
        }
        
        // Add click event for startContinueButton
        const startContinueButton = document.getElementById('startContinueButton');
        if (startContinueButton) {
            startContinueButton.addEventListener('click', function() {
                const voiceOverlay = document.getElementById('voiceOverlay');
                if (voiceOverlay) {
                    voiceOverlay.classList.add('hidden');
                }

                // Make call to start voice journey
                fetch('/journeys/voice/start', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify({ attemptid: attemptId, input: 'Start' })
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                    }
                    
                    const contentType = response.headers.get('content-type');
                    if (contentType && contentType.includes('application/json')) {
                        return response.json();
                    } else {
                        return response.text().then(text => {
                            console.warn('🎤 Non-JSON response received:', text);
                            throw new Error('Expected JSON response but got: ' + contentType);
                        });
                    }
                })
                .then(data => {
                    console.log('🎤 Voice start response:', data);
                })
                .catch(error => {
                    console.error('❌ Voice start error:', error);
                });
            });
        }
    }

    /**
     * Initialize Web Audio API context
     */
    function initializeAudioContext() {
        try {
            audioContext = new (window.AudioContext || window.webkitAudioContext)();
            console.log('🔊 Audio context initialized with sample rate:', audioContext.sampleRate);
        } catch (error) {
            console.error('❌ Failed to initialize audio context:', error);
        }
    }

    /**
     * Handle incoming audio chunks and prepare for continuous playback
     * @param {string} audioData - Base64 encoded PCM16 audio data
     * @param {number} index - Chunk index
     */
    function handleAudioChunk(audioData, index) {
        if (!audioContext) {
            console.error('❌ Audio context not available');
            return;
        }

        try {
            // Store the chunk with its index for ordered playback
            audioChunks.push({
                data: audioData,
                index: index,
                processed: false
            });

            console.log(`🎵 Audio chunk received (index: ${index}, length: ${audioData.length})`);

            // Sort chunks by index to ensure correct order
            audioChunks.sort((a, b) => a.index - b.index);

            // Process and play available chunks in sequence
            processAudioChunks();

        } catch (error) {
            console.error('❌ Error handling audio chunk:', error);
        }
    }

    /**
     * Convert PCM16 base64 data to AudioBuffer
     * @param {string} base64Data - Base64 encoded PCM16 data
     * @returns {AudioBuffer} - Web Audio API AudioBuffer
     */
    function pcm16ToAudioBuffer(base64Data) {
        try {
            // Decode base64 to binary
            const binaryString = atob(base64Data);
            const bytes = new Uint8Array(binaryString.length);
            for (let i = 0; i < binaryString.length; i++) {
                bytes[i] = binaryString.charCodeAt(i);
            }

            // Convert bytes to 16-bit signed integers (PCM16)
            const pcm16Data = new Int16Array(bytes.buffer);
            
            // Create AudioBuffer
            const numSamples = pcm16Data.length;
            const audioBuffer = audioContext.createBuffer(1, numSamples, sampleRate);
            const channelData = audioBuffer.getChannelData(0);

            // Convert PCM16 to float32 (Web Audio API format)
            for (let i = 0; i < numSamples; i++) {
                channelData[i] = pcm16Data[i] / 32768.0; // Convert to -1.0 to 1.0 range
            }

            return audioBuffer;
        } catch (error) {
            console.error('❌ Error converting PCM16 to AudioBuffer:', error);
            return null;
        }
    }

    /**
     * Process audio chunks and play them continuously
     */
    async function processAudioChunks() {
        if (!audioContext || audioChunks.length === 0) {
            return;
        }

        // Resume audio context if suspended (required by some browsers)
        if (audioContext.state === 'suspended') {
            await audioContext.resume();
            console.log('🔊 Audio context resumed');
        }

        // Process unprocessed chunks in order
        for (let chunk of audioChunks) {
            if (!chunk.processed) {
                try {
                    // Convert PCM16 data to AudioBuffer
                    const audioBuffer = pcm16ToAudioBuffer(chunk.data);
                    
                    if (audioBuffer) {
                        // Play the audio chunk
                        playAudioChunk(audioBuffer);
                        chunk.processed = true;
                        console.log(`🎵 Audio chunk ${chunk.index} processed and queued for playback`);
                    } else {
                        console.error(`❌ Failed to create AudioBuffer for chunk ${chunk.index}`);
                        chunk.processed = true; // Mark as processed to avoid retry
                    }

                } catch (error) {
                    console.error(`❌ Error processing audio chunk ${chunk.index}:`, error);
                    chunk.processed = true; // Mark as processed to avoid retry
                }
            }
        }
    }

    /**
     * Play an audio chunk with seamless continuation
     * @param {AudioBuffer} buffer - The audio buffer to play
     */
    function playAudioChunk(buffer) {
        if (!audioContext || !buffer) {
            return;
        }

        try {
            const source = audioContext.createBufferSource();
            source.buffer = buffer;
            source.connect(audioContext.destination);

            // Calculate when to start this chunk for seamless playback
            const now = audioContext.currentTime;
            const startTime = Math.max(now, nextStartTime);
            
            source.start(startTime);
            
            // Update the next start time for seamless continuation
            nextStartTime = startTime + buffer.duration;
            
            console.log(`🎵 Audio chunk playing at ${startTime.toFixed(3)}s, duration: ${buffer.duration.toFixed(3)}s, next start: ${nextStartTime.toFixed(3)}s`);

            // Handle source ending
            source.onended = () => {
                console.log('🎵 Audio chunk playback completed');
            };

        } catch (error) {
            console.error('❌ Error playing audio chunk:', error);
        }
    }

    /**
     * Stop all audio playback
     */
    function stopAudioPlayback() {
        try {
            if (currentAudioSource) {
                currentAudioSource.stop();
                currentAudioSource = null;
            }
            
            // Reset timing
            nextStartTime = audioContext ? audioContext.currentTime : 0;
            isPlayingAudio = false;
            
            console.log('🔇 Audio playback stopped');
        } catch (error) {
            console.error('❌ Error stopping audio:', error);
        }
    }

    /**
     * Clear all audio chunks and reset audio state
     */
    function clearAudioChunks() {
        stopAudioPlayback();
        audioChunks = [];
        audioBuffer = [];
        nextStartTime = audioContext ? audioContext.currentTime : 0;
        console.log('🗑️ Audio chunks cleared');
    }

    /**
     * Stream text to the voiceTextArea element with real-time updates
     * @param {string} content - The complete text content to display
     * @param {number} index - The chunk index for tracking progress
     */
    function streamTextToVoiceArea(content, index) {
        const voiceTextArea = document.getElementById('voiceTextArea');
        if (!voiceTextArea) {
            console.error('❌ voiceTextArea element not found');
            return;
        }

        // Start streaming if not already started
        if (!isStreaming) {
            isStreaming = true;
            console.log('🎤 Starting voice text streaming...');
            
            // Add streaming visual indicator
            voiceTextArea.style.borderLeft = '3px solid #007bff';
            voiceTextArea.style.backgroundColor = '#f8f9fa';
        }

        // Update the content
        voiceTextArea.innerHTML = content;
        
        // Auto-scroll to bottom to show new content
        requestAnimationFrame(() => {
            voiceTextArea.scrollTop = voiceTextArea.scrollHeight;
        });

        console.log(`🎤 Voice text updated (index: ${index}, length: ${content.length})`);
        
        // Store reference to current streaming message
        currentStreamingMessage = voiceTextArea;
    }

    /**
     * Finalize the voice text streaming
     * @param {string} finalContent - The final complete content
     */
    function finalizeVoiceStreaming(finalContent) {
        const voiceTextArea = document.getElementById('voiceTextArea');
        if (!voiceTextArea || !isStreaming) {
            return;
        }

        console.log('🎤 Finalizing voice text streaming...');
        
        // Remove streaming indicators
        voiceTextArea.style.borderLeft = '';
        voiceTextArea.style.backgroundColor = '';
        
        // Set final content if provided
        if (finalContent) {
            voiceTextArea.innerHTML = finalContent;
        }
        
        // Reset streaming state
        isStreaming = false;
        currentStreamingMessage = null;
        
        console.log('✅ Voice text streaming finalized');
    }

    /**
     * Clear the voice text area
     */
    function clearVoiceText() {
        const voiceTextArea = document.getElementById('voiceTextArea');
        if (voiceTextArea) {
            voiceTextArea.innerHTML = '';
            finalizeVoiceStreaming();
        }
    }

    /**
     * Resume audio context if suspended (required by some browsers)
     */
    function resumeAudioContext() {
        if (audioContext && audioContext.state === 'suspended') {
            audioContext.resume().then(() => {
                console.log('🔊 Audio context resumed');
            });
        }
    }

    return {
        init: init,
        finalizeVoiceStreaming: finalizeVoiceStreaming,
        clearVoiceText: clearVoiceText,
        stopAudioPlayback: stopAudioPlayback,
        clearAudioChunks: clearAudioChunks,
        resumeAudioContext: resumeAudioContext
    };
}());
