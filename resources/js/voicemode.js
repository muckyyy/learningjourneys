// VoiceMode Module - Handles voice streaming functionality
// Requires: app.js config object and Echo setup (VoiceEcho is created in app.js)

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

    // === New: audio transcription recording state ===
    let mediaRecorder = null;
    let recordingStream = null;
    let recordingSessionId = null;
    let isRecording = false;
    let recordingTimeout = null;
    let recChunks = [];
    let voiceAttemptId = null;
    // config for paragraph classes received from backend
    let paragraphStyles = null;

    // Throttled text streaming state
    // Default reading speed (words per second) can be overridden by:
    // 1. window.VoiceModeConfig.wordsPerSecond
    // 2. data-words-per-second attribute on #journey-data-voice element
    // 3. VoiceMode.configure({ wordsPerSecond: <number> }) at runtime
    const defaultWordsPerSecond = 3.3; // updated per request
    let wordsPerSecond = defaultWordsPerSecond; // temporary, will be replaced in init by applyConfiguredRate()
    let throttlingIntervalMs = 250; // granularity of updates
    let throttlingTimer = null;
    let throttlingState = {
        latestRawContent: '',
        tokens: [], // token objects: {type:'tag', html:'<p>', isOpen:true}|{type:'word', text:'Hello '} 
        displayedWordCount: 0,
        totalWordCount: 0,
        pendingRebuild: false
    };

    const VOID_ELEMENTS = new Set(['area','base','br','col','embed','hr','img','input','link','meta','param','source','track','wbr']);

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
        voiceAttemptId = attemptId; // make available to other helpers

        // Apply configured words-per-second (from global or data attribute) before streaming begins
        applyConfiguredRate(voiceDataContainer);
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
                
                const hasValidIndex = typeof e.index === 'number' && e.index > 0;

                // Handle styles sync from backend (paragraph classes mapping)
                if (e.type === 'styles' && e.message) {
                    try {
                        paragraphStyles = JSON.parse(e.message);
                        console.log('🎨 VoiceMode styles config updated');
                    } catch (err) {
                        console.warn('⚠️ Failed to parse styles config payload:', err);
                        paragraphStyles = e.message; // allow lenient parsing downstream
                    }
                    return; // styles event doesn't carry text/audio
                }

                // Handle text streaming to voiceTextArea
                if ((e.type === 'text' || e.type === 'response_text') && e.message) {
                    streamTextToVoiceArea(e.message, hasValidIndex ? e.index : 1);
                }

                // Handle audio chunks for continuous playback
                if ((e.type === 'audio' || e.type === 'response_audio') && e.message) {
                    const idx = hasValidIndex ? e.index : ((audioChunks[audioChunks.length-1]?.index || 0) + 1);
                    handleAudioChunk(e.message, idx);
                }

                // Handle progress updates (sync with chat mode)
                if (e.type === 'progress' && e.message != null) {
                    const progressBar = document.getElementById('progress-bar');
                    if (progressBar) {
                        const pct = String(e.message).includes('%') ? e.message : (e.message + '%');
                        progressBar.style.width = pct;
                    }
                }

                // Completion signal: finalize stream UI and enforce completed lock if applicable
                if (e.type === 'complete') {
                    finalizeVoiceStreaming();
                    tryDisableInputsIfCompleted();
                }
            });
            
            console.log('✅ VoiceMode channel listener setup complete');
            
        } catch (error) {
            console.error('❌ Error setting up VoiceMode channel:', error);
        }
        
        // === New: mic button toggle for 30s-limited recording/transcribe ===
        const micButton = document.getElementById('micButton');
        if (micButton) {
            micButton.addEventListener('click', async () => {
                if (isRecording) {
                    stopVoiceRecording();
                } else {
                    startVoiceRecording();
                }
            });
        }

        // Add click event for startContinueButton
        const startContinueButton = document.getElementById('startContinueButton');
        if (startContinueButton) {
            startContinueButton.addEventListener('click', function() {
                const voiceOverlay = document.getElementById('voiceOverlay');
                if (voiceOverlay) {
                    voiceOverlay.classList.add('hidden');
                }
                const isContinue = startContinueButton.classList.contains('voice-continue');
                if (isContinue) {
                    // Replay last AI response text (throttled) and audio
                    const dataEl = document.getElementById('journey-data-voice');
                    if (dataEl) {
                        const encodedHtml = dataEl.getAttribute('data-last-ai-response');
                        const audioId = dataEl.getAttribute('data-last-ai-audio-id');
                        if (encodedHtml) {
                            try {
                                const decodedHtml = decodeBase64Utf8(encodedHtml);
                                replayLastResponse(decodedHtml);
                            } catch (e) { console.error('❌ Failed to decode last AI response HTML:', e); }
                        }
                        if (audioId) {
                            fetchAndPlayExistingAudio(audioId, attemptId);
                        }
                    }
                } else {
                    // Fresh start -> notify server to produce first response
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
                }
            });
        }

        // Hook up send button to submit text input to voice submit route (POST)
        const sendButton = document.getElementById('sendButton');
        if (sendButton) {
            sendButton.addEventListener('click', async function() {
                try {
                    const voiceTextArea = document.getElementById('voiceTextArea');
                    if (voiceTextArea) voiceTextArea.innerHTML = '';
                    const inputEl = document.getElementById('voiceMessageInput') || document.getElementById('messageInput');
                    VoiceMode.clearVoiceText();
                    VoiceMode.clearAudioChunks();

                    if (!voiceAttemptId) {
                        console.error('❌ Attempt ID not found; cannot submit voice message');
                        return;
                    }
                    if (!inputEl) {
                        console.error('❌ No input element found for voice message');
                        return;
                    }
                    const message = (inputEl.value || '').trim();
                    if (!message) {
                        console.warn('⚠️ Empty input; ignoring send');
                        return;
                    }

                    // Use shared helper
                    await sendVoiceMessage(message);
                    inputEl.value = '';
                } catch (err) {
                    console.error('❌ Error submitting voice message:', err);
                } finally {
                    const textEl = document.getElementById('sendButtonText');
                    const spinnerEl = document.getElementById('sendSpinner');
                    const inputEl = document.getElementById('voiceMessageInput') || document.getElementById('messageInput');
                    if (textEl) textEl.textContent = 'Send';
                    if (spinnerEl) spinnerEl.classList.add('d-none');
                    if (sendButton) sendButton.disabled = false;
                    if (inputEl) inputEl.disabled = false;
                }
            });
        }
    }

    // === New: shared sender used by Send button and auto-submit after transcription ===
    async function sendVoiceMessage(message) {
        const sendButton = document.getElementById('sendButton');
        const inputEl = document.getElementById('voiceMessageInput') || document.getElementById('messageInput');
        const textEl = document.getElementById('sendButtonText');
        const spinnerEl = document.getElementById('sendSpinner');

        // CSRF token
        let csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        if (!csrfToken) {
            const csrfInput = document.querySelector('input[name="_token"]');
            if (csrfInput) csrfToken = csrfInput.value;
        }
        if (!csrfToken) {
            console.error('❌ CSRF token not found; cannot POST');
            return;
        }
        if (!voiceAttemptId) {
            console.error('❌ Attempt ID not found; cannot submit voice message');
            return;
        }

        // Disable UI while sending
        if (textEl) textEl.textContent = 'Sending...';
        if (spinnerEl) spinnerEl.classList.remove('d-none');
        if (sendButton) sendButton.disabled = true;
        if (inputEl) inputEl.disabled = true;

        try {
            const url = `/journeys/voice/submit`;
            const res = await fetch(url, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin',
                body: JSON.stringify({ attemptid: parseInt(voiceAttemptId, 10), input: message })
            });

            if (res.ok) {
                try {
                    const data = await res.json();
                    console.log('🎤 Voice submit response:', data);
                    // If journey finished, lock UI and set progress to 100%
                    if (data && data.action === 'finish_journey') {
                        const progress = document.getElementById('progress-bar');
                        if (progress) progress.style.width = '100%';
                        disableInputs();
                        // Mark status on container for future checks
                        const container = document.getElementById('journey-data-voice');
                        if (container) container.setAttribute('data-status', 'completed');
                    }
                } catch {
                    console.warn('⚠️ Voice submit returned non-JSON body');
                }
            } else {
                const errText = await res.text();
                console.error('❌ Voice submit failed:', res.status, errText);
            }
        } catch (err) {
            console.error('❌ Error sending voice message:', err);
        } finally {
            if (textEl) textEl.textContent = 'Send';
            if (spinnerEl) spinnerEl.classList.add('d-none');
            if (sendButton) sendButton.disabled = false;
            if (inputEl) inputEl.disabled = false;
        }
    }

    // === New: Recording + transcription (30s cap) ===
    async function initAudioRecording() {
        try {
            // Clean old recorder/stream
            if (mediaRecorder && mediaRecorder.stream) {
                mediaRecorder.stream.getTracks().forEach(t => t.stop());
            }

            recordingStream = await navigator.mediaDevices.getUserMedia({
                audio: {
                    sampleRate: 16000,
                    channelCount: 1,
                    echoCancellation: true,
                    noiseSuppression: true,
                    autoGainControl: true
                }
            });

            mediaRecorder = new MediaRecorder(recordingStream, {
                mimeType: 'audio/webm;codecs=opus'
            });

            recChunks = [];

            mediaRecorder.ondataavailable = (event) => {
                if (event.data.size > 0) {
                    recChunks.push(event.data);
                }
            };

            mediaRecorder.onstop = async () => {
                try {
                    // Send chunks sequentially
                    for (let i = 0; i < recChunks.length; i++) {
                        const isLast = (i === recChunks.length - 1);
                        await sendAudioChunk(recChunks[i], i, isLast);
                    }
                    await completeAudioRecording();
                } catch (e) {
                    console.error('❌ Error finalizing audio recording:', e);
                } finally {
                    recChunks = [];
                }
            };

            return true;
        } catch (error) {
            console.error('❌ Error initializing audio recording:', error);
            return false;
        }
    }

    async function startVoiceRecording() {
        if (isRecording) return;
        if (!voiceAttemptId) {
            console.error('❌ No attempt id for recording');
            return;
        }

        try {
            stopAudioPlayback(); // avoid echo/overlap

            const ok = await initAudioRecording();
            if (!ok || !mediaRecorder) return;

            // Create a session and notify backend
            recordingSessionId = 'audio_' + Date.now() + '_' + Math.random().toString(36).slice(2, 9);

            const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            if (!csrf) throw new Error('CSRF token missing');

            const res = await fetch('/audio/start-recording', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    journey_attempt_id: voiceAttemptId,
                    session_id: recordingSessionId
                })
            });
            if (!res.ok) {
                const err = await res.json().catch(() => ({}));
                throw new Error(err.error || `HTTP ${res.status} ${res.statusText}`);
            }

            // Start recording and cap at 30s
            mediaRecorder.start(1000); // 1s chunking
            isRecording = true;

            const micButton = document.getElementById('micButton');
            if (micButton) {
                micButton.innerHTML = '🔴';
                micButton.title = 'Stop Recording (Max 30s)';
                micButton.classList.add('btn-danger');
                micButton.classList.remove('btn-outline-secondary');
            }

            recordingTimeout = setTimeout(() => {
                stopVoiceRecording();
                console.log('⏰ Recording stopped - 30 second limit reached');
            }, 30000);

            console.log('🎤 Recording started... (Maximum 30 seconds)');
        } catch (e) {
            console.error('❌ Failed to start recording:', e);
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
    }

    function stopVoiceRecording() {
        if (!isRecording || !mediaRecorder) return;

        try {
            isRecording = false;

            if (recordingTimeout) {
                clearTimeout(recordingTimeout);
                recordingTimeout = null;
            }

            if (mediaRecorder.state === 'recording') {
                mediaRecorder.stop();
            }

            if (recordingStream) {
                recordingStream.getTracks().forEach(t => t.stop());
                recordingStream = null;
            }

            const micButton = document.getElementById('micButton');
            if (micButton) {
                micButton.innerHTML = '🎤';
                micButton.title = 'Voice Input';
                micButton.classList.remove('btn-danger');
                micButton.classList.add('btn-outline-secondary');
            }

            // === New: clear text/audio buffers and the voiceTextArea (same as Send button) ===
            try {
                const voiceTextArea = document.getElementById('voiceTextArea');
                if (voiceTextArea) voiceTextArea.innerHTML = '';
                clearVoiceText();
                clearAudioChunks();
                // reset throttling state to avoid stale carry-over
                throttlingState.latestRawContent = '';
                throttlingState.tokens = [];
                throttlingState.displayedWordCount = 0;
                throttlingState.totalWordCount = 0;
                stopThrottlingTimer();
                fractionalWordsCarry = 0;
            } catch (clearErr) {
                console.warn('⚠️ VoiceMode buffer clear warning:', clearErr);
            }

            console.log('🎤 Recording stopped. Processing...');
        } catch (e) {
            console.error('❌ Error stopping recording:', e);
        }
    }

    async function sendAudioChunk(audioBlob, chunkNumber, isFinal = false) {
        if (!recordingSessionId) return;

        try {
            const arrayBuffer = await audioBlob.arrayBuffer();
            const uint8Array = new Uint8Array(arrayBuffer);
            let binary = '';
            const chunkSize = 0x8000;
            for (let i = 0; i < uint8Array.length; i += chunkSize) {
                binary += String.fromCharCode.apply(null, uint8Array.subarray(i, i + chunkSize));
            }
            const base64 = btoa(binary);

            const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            const response = await fetch('/audio/process-chunk', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf,
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
                console.error('❌ Failed to send audio chunk:', response.statusText);
            }
        } catch (error) {
            console.error('❌ Error sending audio chunk:', error);
        }
    }

    async function completeAudioRecording() {
        if (!recordingSessionId) return;

        try {
            const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            const response = await fetch('/audio/complete', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ session_id: recordingSessionId })
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }

            await pollForTranscription(recordingSessionId);
        } catch (error) {
            console.error('❌ Error completing recording:', error);
        } finally {
            recordingSessionId = null;
        }
    }

    async function pollForTranscription(sessionId) {
        if (!sessionId) return;

        const maxAttempts = 30;
        let attempts = 0;

        const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

        const poll = async () => {
            try {
                const response = await fetch(`/audio/transcription/${sessionId}`, {
                    headers: {
                        'X-CSRF-TOKEN': csrf,
                        'Accept': 'application/json'
                    }
                });

                if (!response.ok) {
                    const errorData = await response.json().catch(() => ({}));
                    throw new Error(errorData.error || `HTTP ${response.status}: ${response.statusText}`);
                }

                const data = await response.json();

                if (data.status === 'completed' && data.transcription) {
                    const inputEl = document.getElementById('voiceMessageInput') || document.getElementById('messageInput');
                    if (inputEl) {
                        const currentValue = (inputEl.value || '').trim();
                        inputEl.value = currentValue ? currentValue + ' ' + data.transcription : data.transcription;
                    }
                    console.log('✅ Transcription complete:', data.transcription);

                    // Auto-submit
                    if (inputEl && inputEl.value.trim()) {
                        console.log('🚀 Auto-submitting transcribed message...');
                        setTimeout(() => {
                            sendVoiceMessage(inputEl.value.trim());
                            inputEl.value = '';
                        }, 700);
                    }
                    return;
                }

                if (data.status === 'failed') {
                    console.warn('❌ Transcription failed. Please try again.');
                    return;
                }

                attempts++;
                if (attempts < maxAttempts) {
                    setTimeout(poll, 1000);
                } else {
                    console.warn('⏰ Transcription timeout.');
                }
            } catch (error) {
                console.error('❌ Error polling transcription:', error);
            }
        };

        poll();
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
            
            //console.log(`🎵 Audio chunk playing at ${startTime.toFixed(3)}s, duration: ${buffer.duration.toFixed(3)}s, next start: ${nextStartTime.toFixed(3)}s`);

            // Handle source ending
            source.onended = () => {
                //console.log('🎵 Audio chunk playback completed');
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
        // Start streaming visual styling if needed
        if (!isStreaming) {
            isStreaming = true;
            voiceTextArea.style.borderLeft = '3px solid #007bff';
            voiceTextArea.style.backgroundColor = '#f8f9fa';
            console.log('🎤 Starting throttled voice text streaming...');
            // Disable user inputs while streaming
            disableInputs();
        }

        // Ignore if content unchanged
        if (content === throttlingState.latestRawContent) {
            return;
        }

        // Apply same paragraph formatting rules as Chat mode using StreamingUtils
        try {
            if (window.StreamingUtils && typeof window.StreamingUtils.formatStreamingContent === 'function') {
                throttlingState.latestRawContent = window.StreamingUtils.formatStreamingContent(content, paragraphStyles);
            } else {
                throttlingState.latestRawContent = content;
            }
        } catch (e) {
            console.warn('⚠️ Failed to apply paragraph formatting, falling back to raw content:', e);
            throttlingState.latestRawContent = content;
        }
        rebuildTokens();
        ensureThrottlingTimer();
        currentStreamingMessage = voiceTextArea; // reference
        console.log(`🎤 Voice text buffer updated (index: ${index}, total tokens: ${throttlingState.tokens.length}, words: ${throttlingState.totalWordCount})`);
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
        // Re-enable inputs if journey not completed
        const container = document.getElementById('journey-data-voice');
        const status = container?.getAttribute('data-status');
        if (status !== 'completed') {
            enableInputs();
        }
        
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

    /**
     * Determine and apply configured words-per-second from multiple sources
     * Priority: runtime configure() > window.VoiceModeConfig > data attribute > default
     */
    function applyConfiguredRate(containerEl) {
        let configured = null;
        // Global script config
        if (window.VoiceModeConfig && typeof window.VoiceModeConfig.wordsPerSecond === 'number') {
            configured = window.VoiceModeConfig.wordsPerSecond;
        }
        // Data attribute fallback
        if (!configured && containerEl) {
            const attrVal = containerEl.getAttribute('data-words-per-second');
            if (attrVal) {
                const parsed = parseFloat(attrVal);
                if (!isNaN(parsed) && parsed > 0) configured = parsed;
            }
        }
        if (configured && configured > 0) {
            wordsPerSecond = configured;
            console.log(`⚙️ VoiceMode wordsPerSecond (initial) set to ${wordsPerSecond}`);
        } else {
            console.log(`⚙️ VoiceMode using default wordsPerSecond ${wordsPerSecond}`);
        }
    }

    // ================= Throttling Helper Functions (now inside closure) =================
    function rebuildTokens() {
        try {
            throttlingState.tokens = tokenizeHtml(throttlingState.latestRawContent);
            throttlingState.totalWordCount = throttlingState.tokens.filter(t=>t.type==='word').length;
            // Align fractional carry with what we've already displayed to avoid jumps on new content
            fractionalWordsCarry = throttlingState.displayedWordCount;
        } catch (e) {
            console.error('❌ Tokenization failed:', e);
        }
    }

    function ensureThrottlingTimer() {
        if (throttlingTimer) return;
        // Reset fractional progress to current displayed count when (re)starting
        fractionalWordsCarry = throttlingState.displayedWordCount;
        throttlingTimer = setInterval(tickThrottle, throttlingIntervalMs);
    }

    function stopThrottlingTimer() {
        if (throttlingTimer) {
            clearInterval(throttlingTimer);
            throttlingTimer = null;
        }
    }

    let fractionalWordsCarry = 0; // accumulate fractional words per tick
    function tickThrottle() {
        const voiceTextArea = document.getElementById('voiceTextArea');
        if (!voiceTextArea) return;

        if (throttlingState.totalWordCount === 0) {
            return;
        }

        // Use a minimum effective interval to limit bursts from timer jitter
        const effectiveIntervalMs = Math.max(throttlingIntervalMs, 100);
        const wordsPerTick = wordsPerSecond * (effectiveIntervalMs / 1000);
        // Clamp max advance per tick to avoid sudden jumps if timer lags
        const maxAdvance = Math.max(1, Math.ceil(wordsPerSecond * 0.5));
        fractionalWordsCarry += Math.min(wordsPerTick, maxAdvance);
        const targetDisplay = Math.min(
            throttlingState.totalWordCount,
            Math.floor(fractionalWordsCarry)
        );

        if (targetDisplay > throttlingState.displayedWordCount) {
            throttlingState.displayedWordCount = targetDisplay;
            const partialHtml = buildPartialHtml(throttlingState.tokens, throttlingState.displayedWordCount);
            updateVoiceAreaHtml(voiceTextArea, partialHtml);
        }

        if (throttlingState.displayedWordCount >= throttlingState.totalWordCount) {
            stopThrottlingTimer();
            // Do not auto-finalize here; wait for explicit 'complete' event to avoid flicker
        }
    }

    function updateVoiceAreaHtml(el, newHtml) {
        try {
            if (window.StreamingUtils && el.querySelector('video,iframe') && /<\/(video|iframe)>/i.test(newHtml)) {
                window.StreamingUtils.preserveVideoWhileUpdating(el, newHtml);
            } else {
                el.innerHTML = newHtml;
            }
            requestAnimationFrame(()=>{ el.scrollTop = el.scrollHeight; });
        } catch (e) {
            console.error('❌ Failed updating throttled HTML:', e);
        }
    }

    function tokenizeHtml(html) {
        const container = document.createElement('div');
        container.innerHTML = html;
        const tokens = [];
        traverse(container, tokens);
        return tokens;
    }

    function traverse(node, tokens) {
        for (let child = node.firstChild; child; child = child.nextSibling) {
            if (child.nodeType === Node.ELEMENT_NODE) {
                const tag = child.tagName.toLowerCase();
                const attrs = [...child.attributes].map(a=>`${a.name}="${a.value}"`).join(' ');
                if (child.childNodes.length === 0 || VOID_ELEMENTS.has(tag)) {
                    const selfHtml = child.outerHTML || `<${tag}${attrs? ' '+attrs:''}>`;
                    tokens.push({type:'tag', html:selfHtml, isOpen:false});
                    continue;
                }
                tokens.push({type:'tag', html:`<${tag}${attrs? ' '+attrs:''}>`, isOpen:true, tag});
                traverse(child, tokens);
                tokens.push({type:'tag', html:`</${tag}>`, isOpen:false, tag});
            } else if (child.nodeType === Node.TEXT_NODE) {
                addTextTokens(child.nodeValue, tokens);
            }
        }
    }

    function addTextTokens(text, tokens) {
        if (!text) return;
        let currentWord = '';
        for (let i=0;i<text.length;i++) {
            const ch = text[i];
            if (/\s/.test(ch)) {
                // end current word if any
                if (currentWord) {
                    tokens.push({type:'word', text: currentWord});
                    currentWord = '';
                }
                // append whitespace to last word/space token or create new space token
                const last = tokens[tokens.length-1];
                if (last && (last.type === 'word' || last.type === 'space')) {
                    last.text += ch;
                } else {
                    tokens.push({type:'space', text: ch});
                }
            } else {
                currentWord += ch;
            }
        }
        if (currentWord) {
            tokens.push({type:'word', text: currentWord});
        }
    }

    function buildPartialHtml(tokens, wordLimit) {
        let wordsAdded = 0;
        const out = [];
        const openStack = [];
        for (let t of tokens) {
            if (t.type === 'tag') {
                out.push(t.html);
                if (t.isOpen) openStack.push(t.tag);
                else if (t.tag) openStack.pop();
            } else if (t.type === 'space') {
                out.push(t.text);
            } else if (t.type === 'word') {
                if (wordsAdded < wordLimit) {
                    out.push(t.text);
                    wordsAdded++;
                } else {
                    break;
                }
            }
        }
        for (let i = openStack.length - 1; i >= 0; i--) {
            const tag = openStack[i];
            if (tag) out.push(`</${tag}>`);
        }
        return out.join('');
    }

    // ================= Resume / Replay Helpers =================
    function replayLastResponse(html) {
        // Reset throttling state and feed content gradually
        throttlingState.latestRawContent = '';
        throttlingState.tokens = [];
        throttlingState.displayedWordCount = 0;
        throttlingState.totalWordCount = 0;
        fractionalWordsCarry = 0;
        // Use existing stream function to leverage token rebuild + throttle
        streamTextToVoiceArea(html, 1);
    }

    async function fetchAndPlayExistingAudio(stepResponseId, attemptId) {
        if (!attemptId) return;
        try {
            // Endpoint returns raw audio file; we attempt mp3 then wav fallback
            const urls = [
                `/journeys/aivoice/${stepResponseId}?attempt=${attemptId}&format=mp3`,
                `/journeys/aivoice/${stepResponseId}?attempt=${attemptId}&format=wav`
            ];
            for (let url of urls) {
                const res = await fetch(url);
                if (res.ok) {
                    const blob = await res.blob();
                    const arrayBuffer = await blob.arrayBuffer();
                    if (!audioContext) initializeAudioContext();
                    const decoded = await audioContext.decodeAudioData(arrayBuffer);
                    // Clear any prior queued audio and play immediately for replay
                    stopAudioPlayback();
                    nextStartTime = audioContext.currentTime; // reset scheduling
                    playAudioChunk(decoded);
                    console.log('▶️ Replaying existing AI audio from', url);
                    break;
                }
            }
        } catch (e) {
            console.error('❌ Failed to fetch/play existing audio:', e);
        }
    }

    // Proper UTF-8 base64 decoder (atob returns Latin-1 which causes mojibake for multi-byte chars)
    function decodeBase64Utf8(b64) {
        try {
            const binary = atob(b64);
            // Convert binary string to Uint8Array
            const bytes = new Uint8Array(binary.length);
            for (let i = 0; i < binary.length; i++) bytes[i] = binary.charCodeAt(i);
            if (window.TextDecoder) {
                return new TextDecoder('utf-8').decode(bytes);
            }
            // Fallback using escape/decodeURIComponent
            let latin1 = '';
            for (let i = 0; i < bytes.length; i++) latin1 += String.fromCharCode(bytes[i]);
            return decodeURIComponent(escape(latin1));
        } catch (e) {
            console.warn('⚠️ decodeBase64Utf8 fallback triggered', e);
            try {
                return atob(b64); // last resort
            } catch { return ''; }
        }
    }

    function setWordsPerSecondInternal(rate) {
        if (typeof rate === 'number' && rate > 0) {
            wordsPerSecond = rate;
            console.log(`⚙️ VoiceMode wordsPerSecond set to ${wordsPerSecond}`);
        }
    }

    function configure(opts = {}) {
        if (opts.wordsPerSecond) setWordsPerSecondInternal(opts.wordsPerSecond);
    }

    // Mirror Chat mode: if attempt status is already completed, disable input and set progress to 100%
    function tryDisableInputsIfCompleted() {
        try {
            const container = document.getElementById('journey-data-voice');
            const status = container?.getAttribute('data-status');
            if (status === 'completed') {
                const progress = document.getElementById('progress-bar');
                if (progress) progress.style.width = '100%';
                disableInputs();
            }
        } catch (e) {
            console.warn('⚠️ Failed to apply completed state in VoiceMode:', e);
        }
    }

    function disableInputs() {
        const inputEl = document.getElementById('voiceMessageInput') || document.getElementById('messageInput');
        const micEl = document.getElementById('micButton');
        const sendEl = document.getElementById('sendButton');
        if (inputEl) inputEl.disabled = true;
        if (micEl) micEl.disabled = true;
        if (sendEl) sendEl.disabled = true;
    }

    function enableInputs() {
        const inputEl = document.getElementById('voiceMessageInput') || document.getElementById('messageInput');
        const micEl = document.getElementById('micButton');
        const sendEl = document.getElementById('sendButton');
        if (inputEl) inputEl.disabled = false;
        if (micEl) micEl.disabled = false;
        if (sendEl) sendEl.disabled = false;
    }

    return {
        init: init,
        finalizeVoiceStreaming: finalizeVoiceStreaming,
        clearVoiceText: clearVoiceText,
        stopAudioPlayback: stopAudioPlayback,
        clearAudioChunks: clearAudioChunks,
        resumeAudioContext: resumeAudioContext,
        setWordsPerSecond: setWordsPerSecondInternal,
        configure: configure,
        _debugThrottlingState: throttlingState,

        // === New (optional public) ===
        startVoiceRecording: startVoiceRecording,
        stopVoiceRecording: stopVoiceRecording
    };
}());
