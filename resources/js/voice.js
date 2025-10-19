// VoiceMode Module - Handles voice streaming functionality
// Requires: app.js config object and Echo setup (VoiceEcho is created in app.js)

const { start } = require("@popperjs/core");
const { message } = require("laravel-mix/src/Log");

window.VoiceMode = (function() {
    // Private variables for voice streaming
    let voiceStream = 0;
    let reproductioninprogress = false;
    let attemptId = 0;
    let textBuffer = '';
    let audioChunks = [];
    let audioBuffer = [];
    let startedAt = null;
    let paragraphStyles = {};
    let wps = 3.00; // words per second for text-to-speech pacing
    let recordtime = 0;
    let mediaRecorder = null;
    let recordingStream = null;
    let recordingSessionId = null;
    let isRecording = false;
    let recordingTimeout = null;
    let recChunks = [];
    let recordingStartTime = null;
    let recordingIndicatorInterval = null;
    // New UI/recording state helpers
    let recordingAudioContext = null;
    let analyser = null;
    let animationFrameId = null;
    let visualizerCanvas = null;
    let visualizerCtx = null;
    let recordingUIEl = null;
    let countdownInterval = null;
    let recordedBlob = null; // preview-able blob after stop
    let sendWhenStopped = false; // if true, send immediately after stop
    
    // Helper: scroll the page body to the bottom (default: auto to avoid jank during streaming)
    function scrollBodyToBottom(behavior = 'auto') {
        try {
            const target = Math.max(document.body.scrollHeight, document.documentElement.scrollHeight);
            window.scrollTo({ top: target, behavior });
        } catch (e) {
            // Fallback
            window.scrollTo(0, document.body.scrollHeight || document.documentElement.scrollHeight || 0);
        }
    }
    
    function init() {
        
        document.getElementById('journey-data-voice');
        const voiceElement = document.getElementById('journey-data-voice');
        const attemptId = voiceElement.getAttribute('data-attempt-id');
        const journeyId = voiceElement.getAttribute('data-journey-id');
        const currentStep = voiceElement.getAttribute('data-current-step');
        const totalSteps = voiceElement.getAttribute('data-total-steps');
        const mode = voiceElement.getAttribute('data-mode');
        const status = voiceElement.getAttribute('data-status');
        const recordtime = voiceElement.getAttribute('data-recordtime');
        // Attach variables to VoiceMode object for external access
        window.VoiceMode.voiceStream = voiceStream;
        window.VoiceMode.reproductioninprogress = reproductioninprogress;
        window.VoiceMode.attemptId = attemptId;
        window.VoiceMode.journeyId = journeyId;
        window.VoiceMode.currentStep = currentStep;
        window.VoiceMode.totalSteps = totalSteps;
        window.VoiceMode.mode = mode;
        window.VoiceMode.status = status;
        window.VoiceMode.textBuffer = textBuffer;
        window.VoiceMode.audioChunks = audioChunks;
        window.VoiceMode.audioBuffer = audioBuffer;
        window.VoiceMode.paragraphStyles = paragraphStyles;
        window.VoiceMode.wps = wps;
        window.VoiceMode.startedAt = startedAt;
        window.VoiceMode.recordtime = recordtime ? parseFloat(recordtime) : 0;

        //Setup listening channel
        const channelName = `voice.mode.${attemptId}`;
        const voiceChannel = window.VoiceEcho.private(channelName);

        // Add subscription debugging
        voiceChannel.subscribed(() => {
            console.log('✅ Subscribe:', channelName);
        });
        
        voiceChannel.error((error) => {
            console.error('❌ VoiceMode - Channel subscription error:', error);
        });
        
        voiceChannel.listen('.voice.chunk.sent', (e) => {
            handleSentPacket(e);
        });

        // Attach click handler to start/continue button
        const startContinueBtn = document.getElementById('startContinueButton');
        if (startContinueBtn) {
            startContinueBtn.addEventListener('click', handleStartContinueClick, { once: false });
        } else {
            console.warn('#startContinueButton not found');

        }
        // Attach click handler to submit button
        const submitButton = document.getElementById('sendButton');
        if (submitButton) {
            submitButton.addEventListener('click', handleSubmitClick, { once: false });
        } else {
            console.warn('#sendButton not found');

        }
        const chatContainer = document.getElementById('chatContainer');
        requestAnimationFrame(() => { scrollBodyToBottom('smooth'); });
        
        // Attach click handler to mic button for recording
        const micButton = document.getElementById('micButton');
        if (micButton) {
            micButton.addEventListener('click', handleMicClick, { once: false });
        } else {
            console.warn('#micButton not found');
        }
    }

    function handleMicClick(e) {
        e.preventDefault();
        if (isRecording) {
            stopVoiceRecording();
        } else {
            startVoiceRecording();
        }
    }

    function handleSubmitClick(e) {
        e.preventDefault(); // Prevent default form submission
        // If currently recording: stop and send immediately
        if (isRecording) {
            sendWhenStopped = true;
            // Immediate UI update to reflect we're finalizing and sending the recording
            try {
                const micBtn = document.getElementById('micButton');
                if (micBtn) micBtn.disabled = true;
                const textEl = document.getElementById('sendButtonText');
                const spinnerEl = document.getElementById('sendSpinner');
                if (textEl) textEl.textContent = 'Sending recording…';
                if (spinnerEl) spinnerEl.classList.remove('d-none');
                // Replace visualizer with finalizing notice until blob is ready
                if (recordingUIEl) {
                    recordingUIEl.innerHTML = `
                        <div class="d-flex align-items-center gap-2 small text-muted">
                            <span class="processing-spinner"></span>
                            <span>Finalizing recording…</span>
                        </div>
                    `;
                }
            } catch {}
            // Stop recording to flush data and trigger send
            stopVoiceRecording();
            return;
        }

        // If we have a recorded blob in preview state, send it for transcription
        if (recordedBlob) {
            sendRecordedAudio();
            return;
        }

        disableInputs();
        
        const sendButton = document.getElementById('sendButton');
        const inputEl = document.getElementById('voiceMessageInput') || document.getElementById('messageInput');
        const textEl = document.getElementById('sendButtonText');
        const spinnerEl = document.getElementById('sendSpinner');
        const message = inputEl ? inputEl.value.trim() : '';
        
        if (!message) {
            enableInputs();
            return;
        }
        
        if (textEl) textEl.textContent = 'Sending...';
        if (spinnerEl) spinnerEl.classList.remove('d-none');

        // CSRF token
        let csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        if (!csrfToken) {
            const csrfInput = document.querySelector('input[name="_token"]');
            if (csrfInput) csrfToken = csrfInput.value;
        }
        if (!csrfToken) {
            console.error('❌ CSRF token not found; cannot POST');
            enableInputs();
            return;
        }

        // Add user message to chat container
        const chatContainer = document.getElementById('chatContainer');
        if (chatContainer) {
            const userMessageDiv = document.createElement('div');
            userMessageDiv.className = 'message user-message';
            userMessageDiv.textContent = message;
            chatContainer.appendChild(userMessageDiv);
            
            // Add new AI message div to chat container for the response
            const aiMessageDiv = document.createElement('div');
            aiMessageDiv.className = 'message ai-message';
            chatContainer.appendChild(aiMessageDiv);
            
            // Scroll page to the new message
            requestAnimationFrame(() => { scrollBodyToBottom('smooth'); });
        }

        // Clear input and reset state for new response
        if (inputEl) inputEl.value = '';
        window.VoiceMode.textBuffer = '';
        window.VoiceMode.audioBuffer = [];
        window.VoiceMode.startedAt = null;
        window.VoiceMode.throttlingState = null;
        window.VoiceMode.streamingComplete = false;

        const url = `/journeys/voice/submit`;
        fetch(url, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin',
            body: JSON.stringify({ attemptid: parseInt(window.VoiceMode.attemptId, 10), input: message })
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
            console.log('🎤 Voice submit response:', data);
            
            // Check for journey completion - but don't show message yet
            const journeyStatus = ((data?.journey_status ?? data?.joruney_status ?? data?.action) || '').toString().trim();
            if (journeyStatus === 'finish_journey') {
                const progressBar = document.getElementById('progress-bar');
                if (progressBar) progressBar.style.width = '100%';
                
                // Mark status on container for future checks
                const voiceElement = document.getElementById('journey-data-voice');
                if (voiceElement) voiceElement.setAttribute('data-status', 'completed');
                
                // Set flag for completion message to be shown later
                window.VoiceMode.journeyCompleted = true;
                
                console.log('🎯 Journey completion detected - will show message after streaming completes');
            } else {
                // Reset button state but keep inputs disabled for ongoing streaming
                if (textEl) textEl.textContent = 'Send';
                if (spinnerEl) spinnerEl.classList.add('d-none');
            }
        })
        .catch(error => {
            console.error('❌ Voice submit error:', error);
            // Reset button state on error
            if (textEl) textEl.textContent = 'Send';
            if (spinnerEl) spinnerEl.classList.add('d-none');
            // Only enable inputs on actual network/server errors for non-completed journeys
            const voiceElement = document.getElementById('journey-data-voice');
            const isCompleted = voiceElement?.getAttribute('data-status') === 'completed';
            if (!isCompleted) {
                enableInputs();
            }
        });
    }

    function handleSentPacket(e) {
        console.log('🎤 VoiceMode received packet:', e);
        // Handle styles sync from backend (paragraph classes mapping)
        if (e.type === 'styles' && e.message) {
            try {
                window.VoiceMode.paragraphStyles = JSON.parse(e.message);
                console.log('🎨 VoiceMode styles config updated');
            } catch (err) {
                console.warn('⚠️ Failed to parse styles config payload:', err);
                window.VoiceMode.paragraphStyles = e.message; // allow lenient parsing downstream
            }
            return; // styles event doesn't carry text/audio
        }
        if (e.type === 'jsrid' && e.message) {
            console.log('🎤 VoiceMode received jsrid:', e.message);
            const chatContainer = document.getElementById('chatContainer');
            if (chatContainer) {
                const lastAiMessage = chatContainer.querySelector('.message.ai-message:last-child');
                if (lastAiMessage) {
                    lastAiMessage.setAttribute('data-jsrid', e.message);
                }
            }
        }

        // Handle text streaming to voiceTextArea
        if ((e.type === 'text' || e.type === 'response_text') && e.message) {
            window.VoiceMode.textBuffer += e.message;
            startStreamedAudioPlayback(); // Continue throttling with new text
        }

        // Handle audio chunks for continuous playback
        if ((e.type === 'audio' || e.type === 'response_audio') && e.message) {
            handleAudioChunk(e);
        }

        // Handle progress updates (sync with chat mode)
        if (e.type === 'progress' && e.message != null) {
            const progressBar = document.getElementById('progress-bar');
            if (progressBar) {
                const pct = String(e.message).includes('%') ? e.message : (e.message + '%');
                progressBar.style.width = pct;
            }
        }

        // Completion of a streaming segment - mark as complete but don't stop throttling
        if (e.type === 'complete') {
            window.VoiceMode.streamingComplete = true;
            console.log('🎤 Streaming completed, continuing throttling...');
            // Don't stop here - let throttling finish naturally
            ensureThrottlingCompletes();
        }
    }

    function ensureThrottlingCompletes() {
        if (!window.VoiceMode.throttlingInterval) {
            window.VoiceMode.throttlingInterval = setInterval(() => {
                const throttlingState = window.VoiceMode.throttlingState;
                if (throttlingState && throttlingState.displayedWordCount < throttlingState.totalWordCount) {
                    startStreamedAudioPlayback(); // Continue throttling
                } else {
                    // Throttling complete
                    clearInterval(window.VoiceMode.throttlingInterval);
                    window.VoiceMode.throttlingInterval = null;
                    checkOutputComplete();
                }
            }, 100); // Check every 100ms
        }
    }
    function checkOutputComplete() {
        const textComplete = window.VoiceMode.throttlingState && 
                            window.VoiceMode.throttlingState.displayedWordCount >= window.VoiceMode.throttlingState.totalWordCount;
        const audioComplete = window.VoiceMode.audioBuffer && window.VoiceMode.audioBuffer.length === 0;
        const streamingComplete = window.VoiceMode.streamingComplete;
        
        if (textComplete && audioComplete && streamingComplete) {
            // Find last AI message and get data-jsrid
            const chatContainer = document.getElementById('chatContainer');
            const lastAiMessage = chatContainer ? chatContainer.querySelector('.message.ai-message:last-child') : null;
            if (lastAiMessage) {
                const jsrid = lastAiMessage.getAttribute('data-jsrid');
                if (jsrid && !lastAiMessage.querySelector('.voice-recording')) {
                    // Create audio element
                    const audioElem = document.createElement('audio');
                    audioElem.controls = true;
                    audioElem.className = 'mt-2 voice-recording';
                    const sourceElem = document.createElement('source');
                    sourceElem.src = `/journeys/aivoice/${jsrid}`;
                    sourceElem.type = 'audio/mpeg';
                    audioElem.appendChild(sourceElem);
                    audioElem.appendChild(document.createTextNode('Your browser does not support the audio element.'));
                    lastAiMessage.appendChild(audioElem);
                    // Scroll page to the completion message
                    requestAnimationFrame(() => { scrollBodyToBottom('smooth'); });
                }
            }
            
            // Check if journey was completed and show completion message now
            if (window.VoiceMode.journeyCompleted) {
                const chatContainer = document.getElementById('chatContainer');
                if (chatContainer) {
                    const completionMessageDiv = document.createElement('div');
                    completionMessageDiv.className = 'message system-message text-muted small mt-2';
                    completionMessageDiv.textContent = 'This journey is complete. You may close this window or navigate away.';
                    chatContainer.appendChild(completionMessageDiv);
                    const inputGroup = document.getElementById('inputGroup');
                    if (inputGroup) {
                        inputGroup.style.display = 'none';
                    }
                    // Scroll page to the completion message
                    requestAnimationFrame(() => { scrollBodyToBottom('smooth'); });

                }
                
                // Clear the flag
                window.VoiceMode.journeyCompleted = false;
                console.log('🎯 Journey completed - completion message shown after streaming finished');
            }
            
            // Only re-enable inputs if journey is not completed
            const voiceElement = document.getElementById('journey-data-voice');
            const isCompleted = voiceElement?.getAttribute('data-status') === 'completed';
            if (!isCompleted) {
                enableInputs(); // Re-enable inputs when everything is done
            }
        }

    }
   
    function disableInputs($mictoo = true) {
        const inputEl = document.getElementById('voiceMessageInput') || document.getElementById('messageInput');
        const micEl = document.getElementById('micButton');
        const sendEl = document.getElementById('sendButton');
        if (inputEl) inputEl.disabled = true;
        if ($mictoo && micEl) micEl.disabled = true;
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

    function handleStartContinueClick(e) {
        e.preventDefault();
        disableInputs();
        const btn = e.currentTarget || e.target;
        const classList = btn ? Array.from(btn.classList) : [];
        const voiceOverlay = document.getElementById('voiceOverlay');
        if (voiceOverlay) {
            voiceOverlay.classList.add('hidden');
        }

        const isStart = btn && btn.classList.contains('voice-start');
        const isContinue = btn && btn.classList.contains('voice-continue');
        if (!isStart && !isContinue) {
            console.warn('Start/Continue button clicked but neither .voice-start nor .voice-continue present', classList);
            return;
        }
        if (isStart && isContinue) {
            console.warn('Button has both .voice-start and .voice-continue. Choose one.', classList);
        }
        const action = isStart ? 'start' : 'continue';
        // Example branching (stub logic)
        if (isStart) {
           startNewJourney();
        } else if (isContinue) {
            enableInputs();
            console.log('Continuing existing voice journey...');
        }
    }
    /*
    Handle incoming audio chunk for playback
    */
    function handleAudioChunk(e) {
        if (e.type === 'audio' && e.message) {
            window.VoiceMode.audioBuffer.push(e.message);
        }
        startStreamedAudioPlayback();
    }
    function startStreamedAudioPlayback() {
        if (window.VoiceMode.startedAt === null) {
            window.VoiceMode.startedAt = Date.now();
        }
        
        // Handle text streaming first - target the last AI message div
        const chatContainer = document.getElementById('chatContainer');
        const lastAiMessage = chatContainer ? chatContainer.querySelector('.message.ai-message:last-child') : null;
        
        if (lastAiMessage && window.VoiceMode.textBuffer) {
            // Initialize throttling state if not exists
            if (!window.VoiceMode.throttlingState) {
                window.VoiceMode.throttlingState = {
                    latestRawContent: '',
                    tokens: [],
                    displayedWordCount: 0,
                    totalWordCount: 0,
                    fractionalWordsCarry: 0
                };
            }

            const throttlingState = window.VoiceMode.throttlingState;
            
            // Check if content has changed
            if (window.VoiceMode.textBuffer !== throttlingState.latestRawContent) {
                // Apply paragraph formatting using StreamingUtils (same as voicemode.js)
                try {
                    if (window.StreamingUtils && typeof window.StreamingUtils.formatStreamingContent === 'function') {
                        throttlingState.latestRawContent = window.StreamingUtils.formatStreamingContent(window.VoiceMode.textBuffer, window.VoiceMode.paragraphStyles);
                    } else {
                        throttlingState.latestRawContent = window.VoiceMode.textBuffer;
                    }
                } catch (e) {
                    console.warn('⚠️ Failed to apply paragraph formatting, falling back to raw content:', e);
                    throttlingState.latestRawContent = window.VoiceMode.textBuffer;
                }
                
                // Tokenize HTML content
                try {
                    throttlingState.tokens = tokenizeHtml(throttlingState.latestRawContent);
                    throttlingState.totalWordCount = throttlingState.tokens.filter(t => t.type === 'word').length;
                } catch (e) {
                    console.error('❌ Tokenization failed:', e);
                }
            }

            // Calculate throttled display progress based on elapsed time
            if (throttlingState.totalWordCount > 0) {
                const elapsedMs = Date.now() - window.VoiceMode.startedAt;
                const elapsedSeconds = elapsedMs / 1000;
                const targetWordsToShow = Math.min(
                    throttlingState.totalWordCount,
                    Math.floor(elapsedSeconds * window.VoiceMode.wps)
                );

                if (targetWordsToShow > throttlingState.displayedWordCount) {
                    throttlingState.displayedWordCount = targetWordsToShow;
                    const partialHtml = buildPartialHtml(throttlingState.tokens, throttlingState.displayedWordCount);
                    
                    // Update the last AI message div with video preservation
                    try {
                        if (window.StreamingUtils && lastAiMessage.querySelector('video,iframe') && /<\/(video|iframe)>/i.test(partialHtml)) {
                            window.StreamingUtils.preserveVideoWhileUpdating(lastAiMessage, partialHtml);
                        } else {
                            lastAiMessage.innerHTML = partialHtml;
                        }
                        // During streaming, use auto to avoid excessive smooth animations
                        requestAnimationFrame(() => { scrollBodyToBottom('auto'); });
                    } catch (e) {
                        console.error('❌ Failed updating throttled HTML:', e);
                    }
                }
            }
        }

        // Handle audio playback
        if (!window.VoiceMode.audioBuffer || window.VoiceMode.audioBuffer.length === 0) {
            return; // No audio to play, but text streaming continues
        }

        // Initialize audio context if not exists
        if (!window.VoiceMode.audioContext) {
            try {
                window.VoiceMode.audioContext = new (window.AudioContext || window.webkitAudioContext)();
                console.log('🔊 Audio context initialized with sample rate:', window.VoiceMode.audioContext.sampleRate);
            } catch (error) {
                console.error('❌ Failed to initialize audio context:', error);
                return;
            }
        }

        const audioContext = window.VoiceMode.audioContext;
        const sampleRate = 24000; // OpenAI Realtime API uses 24kHz for PCM16

        // Resume audio context if suspended
        if (audioContext.state === 'suspended') {
            audioContext.resume().then(() => {
                console.log('🔊 Audio context resumed');
            });
        }

        // Initialize next start time if not set
        if (!window.VoiceMode.nextStartTime) {
            window.VoiceMode.nextStartTime = audioContext.currentTime;
        }

        // Process each audio chunk in buffer
        while (window.VoiceMode.audioBuffer.length > 0) {
            const audioData = window.VoiceMode.audioBuffer.shift();
            
            try {
                // Convert base64 PCM16 to AudioBuffer
                const binaryString = atob(audioData);
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

                // Create and configure audio source
                const source = audioContext.createBufferSource();
                source.buffer = audioBuffer;
                source.connect(audioContext.destination);

                // Calculate when to start this chunk for seamless playback
                const now = audioContext.currentTime;
                const startTime = Math.max(now, window.VoiceMode.nextStartTime);
                
                source.start(startTime);
                
                // Update the next start time for seamless continuation
                window.VoiceMode.nextStartTime = startTime + audioBuffer.duration;
                
                console.log(`🎵 Audio chunk playing at ${startTime.toFixed(3)}s, duration: ${audioBuffer.duration.toFixed(3)}s`);

                // Handle source ending
                source.onended = () => {
                    console.log('🎵 Audio chunk playback completed');
                    checkOutputComplete();

                };

            } catch (error) {
                console.error('❌ Error processing audio chunk:', error);
            }
        }

        // Helper functions for text tokenization (embedded within function)
        function tokenizeHtml(html) {
            const container = document.createElement('div');
            container.innerHTML = html;
            const tokens = [];
            traverse(container, tokens);
            return tokens;
        }

        function traverse(node, tokens) {
            const VOID_ELEMENTS = new Set(['area','base','br','col','embed','hr','img','input','link','meta','param','source','track','wbr']);
            
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
            const parts = text.split(/(\s+)/);
            for (let i = 0; i < parts.length; i++) {
                const part = parts[i];
                if (part.length === 0) continue;
                
                if (/^\s+$/.test(part)) {
                    // Whitespace
                    tokens.push({type: 'space', text: part});
                } else {
                    // Non-whitespace (word)
                    tokens.push({type: 'word', text: part});
                }
            }
        }

        function buildPartialHtml(tokens, wordLimit) {
            let wordsAdded = 0;
            const out = [];
            const openStack = [];
            
            for (let i = 0; i < tokens.length; i++) {
                const t = tokens[i];
                
                if (t.type === 'tag') {
                    out.push(t.html);
                    if (t.isOpen) openStack.push(t.tag);
                    else if (t.tag) openStack.pop();
                } else if (t.type === 'word') {
                    if (wordsAdded < wordLimit) {
                        out.push(t.text);
                        wordsAdded++;
                    } else {
                        break;
                    }
                } else if (t.type === 'space') {
                    // Include all spaces up to the word limit
                    if (wordsAdded <= wordLimit) {
                        out.push(t.text);
                    }
                }
            }
            
            // Close any open tags
            for (let i = openStack.length - 1; i >= 0; i--) {
                const tag = openStack[i];
                if (tag) out.push(`</${tag}>`);
            }
            
            return out.join('');
        }
    }
    function startNewJourney() {
        disableInputs();
        // Fresh start -> notify server to produce first response
        // Add new AI message div to chat container
        const chatContainer = document.getElementById('chatContainer');
        if (chatContainer) {
            const aiMessageDiv = document.createElement('div');
            aiMessageDiv.className = 'message ai-message';
            chatContainer.appendChild(aiMessageDiv);
            
            // Scroll page to the new message
            requestAnimationFrame(() => { scrollBodyToBottom('smooth'); });
        }
        fetch('/journeys/voice/start', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({ attemptid: window.VoiceMode.attemptId, input: 'Start' })
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
            console.log('Final text:',window.VoiceMode.textBuffer);
            console.log('Final audio chunks:',window.VoiceMode.audioBuffer);
            
        })
        .catch(error => {
            console.error('❌ Voice start error:', error);
        });  
    }
    // Voice recording and transcription functions
    async function startVoiceRecording() {
        if (isRecording) return;
        if (!window.VoiceMode.attemptId) {
            console.error('❌ No attempt id for recording');
            return;
        }
        disableInputs(false);
        // During recording, keep Send enabled so user can stop+send immediately
        try {
            const sendEl = document.getElementById('sendButton');
            if (sendEl) sendEl.disabled = false;
        } catch {}
        // Clear any previous recording preview state
        recordedBlob = null;
        recChunks = [];
        // Prepare UI: hide textarea and show recording UI with visualizer and countdown
        showRecordingUI();

        const maxRecordTime = (window.VoiceMode.recordtime || 30) * 1000; // Convert to milliseconds
        
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
                    journey_attempt_id: window.VoiceMode.attemptId,
                    session_id: recordingSessionId
                })
            });
            if (!res.ok) {
                const err = await res.json().catch(() => ({}));
                throw new Error(err.error || `HTTP ${res.status} ${res.statusText}`);
            }

            // Start recording and cap at recordtime limit
            mediaRecorder.start(250); // smaller chunking for responsiveness
            isRecording = true;
            recordingStartTime = Date.now();

            updateMicButtonRecording();
            showRecordingIndicator();
            startRecordingTimer(maxRecordTime);
            startVisualizer(recordingStream);

            recordingTimeout = setTimeout(() => {
                stopVoiceRecording();
                console.log(`⏰ Recording stopped - ${window.VoiceMode.recordtime || 30} second limit reached`);
            }, maxRecordTime);

            console.log(`🎤 Recording started... (Maximum ${window.VoiceMode.recordtime || 30} seconds)`);
        } catch (e) {
            console.error('❌ Failed to start recording:', e);
            resetRecordingState();
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

            if (recordingIndicatorInterval) {
                clearInterval(recordingIndicatorInterval);
                recordingIndicatorInterval = null;
            }

            if (mediaRecorder.state === 'recording') {
                mediaRecorder.stop();
            }

            if (recordingStream) {
                recordingStream.getTracks().forEach(t => t.stop());
                recordingStream = null;
            }

            updateMicButtonNormal();
            hideRecordingIndicator();
            stopVisualizer();

            // Clear text/audio buffers
            window.VoiceMode.textBuffer = '';
            window.VoiceMode.audioBuffer = [];
            window.VoiceMode.startedAt = null;
            window.VoiceMode.throttlingState = null;
            window.VoiceMode.streamingComplete = false;

            console.log('🎤 Recording stopped. Processing...');
        } catch (e) {
            console.error('❌ Error stopping recording:', e);
        }
    }

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
                    // Build a preview-able blob
                    recordedBlob = new Blob(recChunks, { type: mediaRecorder.mimeType });
                    if (sendWhenStopped) {
                        // Show preview immediately so user sees what was captured, then send
                        showRecordingPreview(recordedBlob);
                        await sendRecordedAudio();
                    } else {
                        showRecordingPreview(recordedBlob);
                    }
                } catch (e) {
                    console.error('❌ Error finalizing audio recording:', e);
                } finally {
                    sendWhenStopped = false;
                }
            };

            return true;
        } catch (error) {
            console.error('❌ Error initializing audio recording:', error);
            return false;
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
            // After sending, reset UI back to initial (show textarea, remove recording UI)
            resetRecordingUI();
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
                    const inputEl = document.getElementById('messageInput');
                    if (inputEl) {
                        const currentValue = (inputEl.value || '').trim();
                        inputEl.value = currentValue ? currentValue + ' ' + data.transcription : data.transcription;
                    }
                    console.log('✅ Transcription complete:', data.transcription);

                    // Enable inputs before auto-submit
                    enableInputs();

                    // Auto-submit
                    if (inputEl && inputEl.value.trim()) {
                        console.log('🚀 Auto-submitting transcribed message...');
                        setTimeout(() => {
                            const sendButton = document.getElementById('sendButton');
                            if (sendButton && !sendButton.disabled) {
                                sendButton.click();
                            }
                        }, 700);
                    }
                    return;
                }

                if (data.status === 'failed') {
                    console.warn('❌ Transcription failed. Please try again.');
                    // Re-enable inputs on failure
                    enableInputs();
                    return;
                }

                attempts++;
                if (attempts < maxAttempts) {
                    setTimeout(poll, 1000);
                } else {
                    console.warn('⏰ Transcription timeout.');
                    // Re-enable inputs on timeout
                    enableInputs();
                }
            } catch (error) {
                console.error('❌ Error polling transcription:', error);
                // Re-enable inputs on error
                enableInputs();
            }
        };

        poll();
    }

    function updateMicButtonRecording() {
        const micButton = document.getElementById('micButton');
        const recordingIcon = document.getElementById('recordingIcon');
        const recordingText = document.getElementById('recordingText');
        
        if (micButton) {
            micButton.classList.add('btn-recording');
            micButton.classList.remove('btn-secondary');
        }
        if (recordingIcon) {
            // Use Bootstrap Icons
            recordingIcon.className = 'bi bi-stop-fill';
        }
        if (recordingText) {
            recordingText.textContent = 'Stop Recording';
        }
    }

    function updateMicButtonNormal() {
        const micButton = document.getElementById('micButton');
        const recordingIcon = document.getElementById('recordingIcon');
        const recordingText = document.getElementById('recordingText');
        
        if (micButton) {
            micButton.classList.remove('btn-recording');
            micButton.classList.add('btn-secondary');
        }
        if (recordingIcon) {
            // Use Bootstrap Icons
            recordingIcon.className = 'bi bi-mic-fill';
        }
        if (recordingText) {
            recordingText.textContent = 'Record Audio';
        }
    }

    function showRecordingIndicator() {
        const chatContainer = document.getElementById('chatContainer');
        if (!chatContainer) return;

        // Create recording indicator if it doesn't exist
        let indicator = document.getElementById('recordingIndicator');
        if (!indicator) {
            indicator = document.createElement('div');
            indicator.id = 'recordingIndicator';
            indicator.className = 'recording-indicator-discreet mb-2';
            indicator.innerHTML = `
                <span class="recording-icon-small me-2"></span>
                <span class="recording-text">Recording:</span>
                <span class="recording-time-small ms-2">0s</span>
                <span class="recording-time-remaining-small ms-2">/ ${window.VoiceMode.recordtime || 30}s</span>
            `;
            chatContainer.parentNode.insertBefore(indicator, chatContainer);
        }
        
        indicator.classList.add('show');
    }

    function hideRecordingIndicator() {
        const indicator = document.getElementById('recordingIndicator');
        if (indicator) {
            indicator.classList.remove('show');
        }
    }

    function startRecordingTimer(maxTime) {
        const indicator = document.getElementById('recordingIndicator');
        if (!indicator || !recordingStartTime) return;

        recordingIndicatorInterval = setInterval(() => {
            const elapsed = Math.floor((Date.now() - recordingStartTime) / 1000);
            const remaining = Math.max(0, Math.floor((maxTime - (Date.now() - recordingStartTime)) / 1000));
            
            const timeEl = indicator.querySelector('.recording-time-small');
            const remainingEl = indicator.querySelector('.recording-time-remaining-small');
            
            if (timeEl) timeEl.textContent = `${elapsed}s`;
            if (remainingEl) remainingEl.textContent = `/ ${Math.ceil(maxTime / 1000)}s (${remaining}s left)`;
            
            if (remaining <= 0) {
                clearInterval(recordingIndicatorInterval);
                recordingIndicatorInterval = null;
            }
        }, 100);
    }

    function resetRecordingState() {
        isRecording = false;
        recordingSessionId = null;
        recordingStartTime = null;
        
        if (recordingTimeout) {
            clearTimeout(recordingTimeout);
            recordingTimeout = null;
        }
        
        if (recordingIndicatorInterval) {
            clearInterval(recordingIndicatorInterval);
            recordingIndicatorInterval = null;
        }
        
        updateMicButtonNormal();
        hideRecordingIndicator();
        stopVisualizer();
        clearCountdown();
        recordedBlob = null;
        removeRecordingPreview();
        resetRecordingUI();
    }

    function stopAudioPlayback() {
        // Stop any ongoing audio playback to prevent echo
        try {
            if (window.VoiceMode.audioContext) {
                window.VoiceMode.nextStartTime = window.VoiceMode.audioContext.currentTime;
            }
        } catch (e) {
            console.warn('⚠️ Audio playback stop warning:', e);
        }
    }

    // ---------- New UI helpers for recording flow ----------
    function showRecordingUI() {
        const inputEl = document.getElementById('messageInput');
        const inputGroup = document.getElementById('inputGroup');
        if (!inputGroup) return;
        if (inputEl) inputEl.style.display = 'none';

        // Create container if needed
        recordingUIEl = document.getElementById('recordingUI');
        const uiHtml = `
            <div class="d-flex align-items-center justify-content-between mb-2">
                <div class="small text-muted">Recording in progress…</div>
                <div class="fw-semibold" id="recordingCountdown">--:--</div>
            </div>
            <canvas id="recordingCanvas" height="64" style="width: 100%; background: #f8fafc; border-radius: 8px;"></canvas>
        `;
        if (!recordingUIEl) {
            recordingUIEl = document.createElement('div');
            recordingUIEl.id = 'recordingUI';
            recordingUIEl.className = 'flex-grow-1 d-flex flex-column align-items-stretch p-2 border rounded';
            recordingUIEl.innerHTML = uiHtml;
            // Insert before mic button
            const micBtn = document.getElementById('micButton');
            inputGroup.insertBefore(recordingUIEl, micBtn);
        } else {
            // Replace any preview content with live visualizer
            recordingUIEl.className = 'flex-grow-1 d-flex flex-column align-items-stretch p-2 border rounded';
            recordingUIEl.innerHTML = uiHtml;
        }

        visualizerCanvas = recordingUIEl.querySelector('#recordingCanvas');
        visualizerCtx = visualizerCanvas.getContext('2d');
        startCountdown();
    }

    function resetRecordingUI() {
        const inputEl = document.getElementById('messageInput');
        if (inputEl) inputEl.style.display = '';
        if (recordingUIEl) {
            recordingUIEl.remove();
            recordingUIEl = null;
            visualizerCanvas = null;
            visualizerCtx = null;
        }
        clearCountdown();
    }

    function startVisualizer(stream) {
        try {
            recordingAudioContext = new (window.AudioContext || window.webkitAudioContext)();
            const source = recordingAudioContext.createMediaStreamSource(stream);
            analyser = recordingAudioContext.createAnalyser();
            analyser.fftSize = 2048;
            source.connect(analyser);

            const bufferLength = analyser.frequencyBinCount;
            const dataArray = new Uint8Array(bufferLength);

            const draw = () => {
                animationFrameId = requestAnimationFrame(draw);
                if (!visualizerCtx || !visualizerCanvas) return;

                analyser.getByteTimeDomainData(dataArray);
                const width = visualizerCanvas.width = visualizerCanvas.clientWidth;
                const height = visualizerCanvas.height;
                visualizerCtx.clearRect(0, 0, width, height);
                visualizerCtx.lineWidth = 2;
                visualizerCtx.strokeStyle = '#10a37f';
                visualizerCtx.beginPath();
                const sliceWidth = width * 1.0 / bufferLength;
                let x = 0;
                for (let i = 0; i < bufferLength; i++) {
                    const v = dataArray[i] / 128.0;
                    const y = v * height / 2;
                    if (i === 0) {
                        visualizerCtx.moveTo(x, y);
                    } else {
                        visualizerCtx.lineTo(x, y);
                    }
                    x += sliceWidth;
                }
                visualizerCtx.lineTo(width, height / 2);
                visualizerCtx.stroke();
            };
            draw();
        } catch (e) {
            console.warn('⚠️ Visualizer failed to start:', e);
        }
    }

    function stopVisualizer() {
        try {
            if (animationFrameId) cancelAnimationFrame(animationFrameId);
            animationFrameId = null;
            if (recordingAudioContext) {
                recordingAudioContext.close().catch(()=>{});
                recordingAudioContext = null;
            }
            analyser = null;
        } catch (e) {
            // ignore
        }
    }

    function startCountdown() {
        const countdownEl = document.getElementById('recordingCountdown');
        if (!countdownEl) return;
        const maxSec = Math.ceil(window.VoiceMode.recordtime || 30);
        const start = Date.now();
        const fmt = (s)=>{
            const m = Math.floor(s/60); const ss = s%60; return `${String(m).padStart(2,'0')}:${String(ss).padStart(2,'0')}`;
        };
        countdownInterval = setInterval(()=>{
            const elapsed = Math.floor((Date.now()-start)/1000);
            const remaining = Math.max(0, maxSec - elapsed);
            countdownEl.textContent = `${fmt(remaining)} / ${fmt(maxSec)}`;
            if (remaining <= 0) {
                clearInterval(countdownInterval);
                countdownInterval = null;
            }
        }, 200);
    }

    function clearCountdown() {
        if (countdownInterval) {
            clearInterval(countdownInterval);
            countdownInterval = null;
        }
    }

    function showRecordingPreview(blob) {
        // Replace visualizer area with audio playback only; reuse mic/send buttons
        if (!recordingUIEl) showRecordingUI();
        if (!recordingUIEl) return;
        // Clear existing content and render preview UI
        const url = URL.createObjectURL(blob);
        const wrap = document.createElement('div');
        wrap.className = 'd-flex flex-column gap-2 w-100';
        wrap.innerHTML = `
            <audio id="recordingPreviewAudio" controls src="${url}"></audio>
          
        `;
        recordingUIEl.innerHTML = '';
        recordingUIEl.appendChild(wrap);
        // Ensure Send is enabled for preview state
        const sendEl = document.getElementById('sendButton');
        if (sendEl) sendEl.disabled = false;
    }

    function removeRecordingPreview() {
        // Clear preview content but keep UI hidden state managed by resetRecordingUI
        if (recordingUIEl) recordingUIEl.innerHTML = '';
    }

    async function sendRecordedAudio() {
        try {
            disableInputs();
            if (!recordingSessionId) {
                // If, for some reason, there's no session (e.g., page state), start one now
                const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                const res = await fetch('/audio/start-recording', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        journey_attempt_id: window.VoiceMode.attemptId,
                        session_id: 'audio_' + Date.now() + '_' + Math.random().toString(36).slice(2,9)
                    })
                });
                if (!res.ok) throw new Error('Failed to create recording session');
                recordingSessionId = (await res.json().catch(()=>({}))).session_id || recordingSessionId;
            }

            // If we have chunks from MediaRecorder use them; else slice blob to chunks
            let chunksToSend = recChunks && recChunks.length ? recChunks : (recordedBlob ? [recordedBlob] : []);
            if (!chunksToSend.length && recordedBlob) chunksToSend = [recordedBlob];

            for (let i = 0; i < chunksToSend.length; i++) {
                const isLast = (i === chunksToSend.length - 1);
                await sendAudioChunk(chunksToSend[i], i, isLast);
            }
            await completeAudioRecording();
            // Clear local preview state after sending
            recordedBlob = null;
            recChunks = [];
        } catch (e) {
            console.error('❌ Failed to send recorded audio:', e);
            enableInputs();
        }
    }

    return {
        init: init,
        
    };
}());
