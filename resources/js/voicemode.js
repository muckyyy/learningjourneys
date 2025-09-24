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
