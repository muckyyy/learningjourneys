// VoiceMode Module - Handles voice streaming functionality
// Requires: app.js config object and Echo setup (VoiceEcho is created in app.js)

const { start } = require("@popperjs/core");

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
    function init() {
        console.log('🎤 VoiceMode module initialized');
        document.getElementById('journey-data-voice');
        const voiceElement = document.getElementById('journey-data-voice');
        const attemptId = voiceElement.getAttribute('data-attempt-id');
        const journeyId = voiceElement.getAttribute('data-journey-id');
        const currentStep = voiceElement.getAttribute('data-current-step');
        const totalSteps = voiceElement.getAttribute('data-total-steps');
        const mode = voiceElement.getAttribute('data-mode');
        const status = voiceElement.getAttribute('data-status');
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
    }

    function handleSentPacket(e) {
    
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
                }
            }
            enableInputs(); // Re-enable inputs when everything is done
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
                        requestAnimationFrame(() => { chatContainer.scrollTop = chatContainer.scrollHeight; });
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
            
            // Scroll to the new message
            requestAnimationFrame(() => {
                chatContainer.scrollTop = chatContainer.scrollHeight;
            });
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


    return {
        init: init,
        
    };
}());
