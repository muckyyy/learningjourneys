// ChatMode Module - Handles PreviewChat functionality and related initialization
// Requires: app.js config, StreamingUtils from utili.js

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
                    return; // Important: return early to avoid duplicate processing
                }
                
                // Handle legacy content format for backward compatibility
                if (parsed.type === 'content' && parsed.delta) {
                    hasReceivedContent = true;
                    aiHtmlBuffer += parsed.delta;
                    
                    // Use the improved streaming function with video support
                    aiMessageDiv = window.StreamingUtils.updateStreamingMessage(aiHtmlBuffer, 'ai', 'chatContainer');
                    return; // Important: return early to avoid duplicate processing
                }
                
                // Handle legacy text format (only if not already handled above)
                if (parsed.text && !parsed.type) {
                    hasReceivedContent = true;
                    aiHtmlBuffer += parsed.text;
                    
                    // Use the improved streaming function with video support
                    aiMessageDiv = window.StreamingUtils.updateStreamingMessage(aiHtmlBuffer, 'ai', 'chatContainer');
                }
                if (parsed.error) addMessage(`Error: ${parsed.error.message || parsed.error}`, 'error');
            } catch (e) {
                if (data && data.length > 0) {
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
            }
        } catch (error) {
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
                const statusEl = document.querySelector('#audio-status .status-text');
                if (statusEl) {
                    statusEl.textContent = `Chunk #${data.chunk_number} received`;
                }
            });
        } catch (error) {
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
                    // Don't fail the recording for WebSocket issues
                }
                
                addMessage('🎤 Recording started... (Maximum 30 seconds)', 'system');

                recordingTimeout = setTimeout(() => {
                    PreviewChat.stopAudioRecording();
                    addMessage('⏰ Recording stopped - 30 second limit reached', 'system');
                }, 30000);

            } catch (error) {
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

// PreviewChat initialization and WebSocket status handling
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
            updateStatus('Connected (Authenticated)', 'green');
        });

        window.Echo.connector.pusher.connection.bind('disconnected', () => {
            updateStatus('Disconnected', 'red');
        });

        window.Echo.connector.pusher.connection.bind('error', (err) => {
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
