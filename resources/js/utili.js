// Utility functions and shared modules
// Contains StreamingUtils, JourneyStep, and JourneyStartModal

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
            if (text) text.textContent = 'Sending...';
            if (spinner) spinner.classList.remove('d-none');
            if (button) button.disabled = true;
            if (micButton) micButton.disabled = true;
        } else {
            if (text) text.textContent = 'Send';
            if (spinner) spinner.classList.add('d-none');
            if (button) button.disabled = false;
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

        // Load existing messages from API
        loadExistingMessages: async function() {
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

        // Start initial journey chat
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

        // Send user message
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

        // Handle streaming response from API
        handleStreamResponse: async function(response) {
            const reader = response.body.getReader();
            const decoder = new TextDecoder();
            let accumulatedText = '';
            let sseBuffer = '';
            let streamTimeout;
            let readerReleased = false;

            console.log('📡 Starting to handle stream response...');

            // Add timeout for stream reading
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

            // Disable inputs during streaming
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
                            
                            // Use the improved streaming function
                            const streamingElement = updateStreamingMessage(accumulatedText, 'ai');
                            console.log('📝 Streaming element updated:', streamingElement ? 'SUCCESS' : 'FAILED');
                        }
                        
                        // Also handle the old format for backward compatibility
                        if (parsed.type === 'content' && parsed.delta) {
                            accumulatedText += parsed.delta;
                            const streamingElement = updateStreamingMessage(accumulatedText, 'ai');
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

        // Handle keyboard events
        handleKeyPress: function(event) {
            if (event.key === 'Enter' && !isProcessing) {
                window.JourneyStep.sendMessage();
            }
        },

        // Audio recording methods
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
