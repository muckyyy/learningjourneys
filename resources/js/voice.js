// VoiceMode Module - Handles voice streaming functionality
// Requires: app.js config object and Echo setup (VoiceEcho is created in app.js)

const { start } = require("@popperjs/core");
const { message } = require("laravel-mix/src/Log");

window.VoiceMode = (function() {
    const REPORT_RENDER_DELAY_MS = 2000; // wait before showing final report
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
    // Output volume control (for generated AI audio)
    let volumeMuted = false; // default: sound on
    // Streaming autoscroll state
    let streamScrollSession = 0;
    let userLockedStreamScroll = false;
    const STREAM_SCROLL_GAP_PX = 40; // distance from bottom that counts as "scrolled up"
    // Streaming audio activity tracking and HTML audio coordination
    let activeStreamSources = 0; // number of currently scheduled/playing WebAudio BufferSources
    let currentPlayingHtmlAudio = null; // currently playing <audio.voice-recording>
    let reportRenderTimer = null; // tracks scheduled final report render
    let awaitingFeedback = false;
    let feedbackSubmitting = false;
    let outputComplete = false;

    function setReproductionInProgress(active) {
        reproductioninprogress = !!active;
        window.VoiceMode.reproductioninprogress = reproductioninprogress;
        if (reproductioninprogress) {
            // Stop any HTML audio when streaming starts
            stopAllVoiceRecordings();
        }
    }

    function stopAllVoiceRecordings() {
        try {
            document.querySelectorAll('audio.voice-recording').forEach(a => {
                try {
                    if (!a.paused) a.pause();
                    a.currentTime = 0;
                } catch {}
            });
        } catch {}
        currentPlayingHtmlAudio = null;
    }

    function attachHandlersToVoiceRecording(audioEl) {
        if (!audioEl || audioEl.__voiceHandlersAttached) return;
        audioEl.__voiceHandlersAttached = true;
        audioEl.addEventListener('play', () => {
            // If streaming audio is underway, immediately stop this
            if (reproductioninprogress) {
                try {
                    audioEl.pause();
                    audioEl.currentTime = 0;
                } catch {}
                return;
            }
            // Enforce single playback: pause any other HTML audio
            try {
                document.querySelectorAll('audio.voice-recording').forEach(other => {
                    if (other !== audioEl && !other.paused) {
                        try { other.pause(); } catch {}
                    }
                });
            } catch {}
            currentPlayingHtmlAudio = audioEl;
        });
        audioEl.addEventListener('ended', () => {
            if (currentPlayingHtmlAudio === audioEl) currentPlayingHtmlAudio = null;
        });
        audioEl.addEventListener('pause', () => {
            if (currentPlayingHtmlAudio === audioEl) currentPlayingHtmlAudio = null;
        });
    }
    
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

    // Helper: scroll chat container to bottom with optional behavior
    function scrollChatToBottom(behavior = 'auto') {
        const chatContainer = document.getElementById('chatContainer');
        if (!chatContainer) {
            scrollBodyToBottom(behavior);
            return;
        }
        try {
            chatContainer.scrollTo({ top: chatContainer.scrollHeight, behavior });
        } catch (e) {
            chatContainer.scrollTop = chatContainer.scrollHeight;
        }
    }

    // Streaming autoscroll controls (reset each stream)
    function startStreamScrollSession() {
        streamScrollSession += 1;
        userLockedStreamScroll = false;
    }

    function lockStreamAutoscroll() {
        userLockedStreamScroll = true;
    }

    function scrollChatToBottomIfAllowed(behavior = 'auto') {
        if (userLockedStreamScroll) return;
        requestAnimationFrame(() => scrollChatToBottom(behavior));
    }

    function bindChatScrollWatcher(chatContainer) {
        if (!chatContainer || chatContainer.__voiceStreamScrollBound) return;
        const handler = () => {
            if (userLockedStreamScroll) return;
            const bottomGap = chatContainer.scrollHeight - (chatContainer.scrollTop + chatContainer.clientHeight);
            if (bottomGap > STREAM_SCROLL_GAP_PX) {
                lockStreamAutoscroll();
            }
        };
        chatContainer.addEventListener('scroll', handler, { passive: true });
        chatContainer.__voiceStreamScrollBound = true;
    }

    function normalizeParagraphStylesMap(styleMap) {
        if (!styleMap || typeof styleMap !== 'object') {
            return styleMap || {};
        }

        if (Array.isArray(styleMap)) {
            return styleMap;
        }

        const keys = Object.keys(styleMap);
        if (!keys.length) {
            return {};
        }

        const numericKeys = keys
            .map((key) => Number.parseInt(key, 10))
            .filter((value) => Number.isFinite(value));

        if (numericKeys.length !== keys.length) {
            return styleMap;
        }

        const minIndex = Math.min(...numericKeys);
        if (minIndex === 0) {
            return styleMap;
        }

        const normalized = {};
        numericKeys
            .sort((a, b) => a - b)
            .forEach((key, index) => {
                normalized[index] = styleMap[key] ?? styleMap[String(key)] ?? '';
            });

        return normalized;
    }

    function parseIncomingParagraphStyles(payload) {
        if (!payload) {
            return {};
        }

        if (typeof payload === 'object') {
            return normalizeParagraphStylesMap(payload);
        }

        if (typeof payload === 'string') {
            try {
                return normalizeParagraphStylesMap(JSON.parse(payload));
            } catch (err) {
                console.warn('⚠️ Failed to parse styles config payload:', err);
                return normalizeParagraphStylesMap(payload);
            }
        }

        return {};
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
        const hasStarted = voiceElement.getAttribute('data-has-started') === '1';
        // Attach variables to VoiceMode object for external access
        window.VoiceMode.voiceStream = voiceStream;
        window.VoiceMode.reproductioninprogress = reproductioninprogress;
        window.VoiceMode.attemptId = attemptId;
        window.VoiceMode.journeyId = journeyId;
        window.VoiceMode.currentStep = currentStep;
        window.VoiceMode.totalSteps = totalSteps;
        window.VoiceMode.mode = mode;
        window.VoiceMode.status = status;
        window.VoiceMode.hasStarted = hasStarted;
        window.VoiceMode.textBuffer = textBuffer;
        window.VoiceMode.audioChunks = audioChunks;
        window.VoiceMode.audioBuffer = audioBuffer;
        window.VoiceMode.paragraphStyles = paragraphStyles;
        window.VoiceMode.wps = wps;
        window.VoiceMode.startedAt = startedAt;
        window.VoiceMode.recordtime = recordtime ? parseFloat(recordtime) : 0;
        window.VoiceMode.feedbackEndpoint = voiceElement.getAttribute('data-feedback-url') || '/journeys/voice/feedback';
        outputComplete = status === 'completed' || status === 'awaiting_feedback';
        window.VoiceMode.outputComplete = outputComplete;

        const needsFeedback = voiceElement.getAttribute('data-needs-feedback') === '1';
        const hasFeedback = voiceElement.getAttribute('data-has-feedback') === '1';
        setAwaitingFeedback(needsFeedback);
        window.VoiceMode.feedbackSubmitted = hasFeedback;
        if (needsFeedback) {
            scrollBodyToBottom('smooth');
        }

        //Setup listening channel
    const channelName = `voice.mode.${attemptId}`;
    const voiceChannel = window.VoiceEcho.private(channelName);
    // Keep references for later cleanup
    window.VoiceMode.channelName = channelName;
    window.VoiceMode.voiceChannel = voiceChannel;

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

        // Ensure we unsubscribe/leave the channel when the page is closed or navigated away
        if (!window.VoiceMode._cleanupBound) {
            const leaveVoiceChannel = () => {
                try {
                    const chName = window.VoiceMode?.channelName;
                    const ch = window.VoiceMode?.voiceChannel;
                    if (ch) {
                        try { ch.stopListening?.('.voice.chunk.sent'); } catch {}
                        // For some drivers, an explicit unsubscribe exists
                        try { ch.unsubscribe?.(); } catch {}
                    }
                    if (chName && window.VoiceEcho && typeof window.VoiceEcho.leave === 'function') {
                        window.VoiceEcho.leave(chName);
                    } else if (ch && typeof ch.cancel === 'function') {
                        // Fallback for other connectors
                        try { ch.cancel(); } catch {}
                    }
                } catch (err) {
                    console.warn('⚠️ VoiceMode channel cleanup failed:', err);
                } finally {
                    disableVoiceScrollLock();
                }
            };
            window.addEventListener('beforeunload', leaveVoiceChannel, { once: true });
            window.addEventListener('unload', leaveVoiceChannel, { once: true });
            window.VoiceMode._cleanupBound = true;
        }

        // Attach click handler to start/continue button
        const requiresStartInteraction = status === 'in_progress' && !hasStarted;
        const startContinueBtn = document.getElementById('startContinueButton');
        if (startContinueBtn) {
            startContinueBtn.addEventListener('click', handleStartContinueClick, { once: false });
        } else if (requiresStartInteraction) {
            console.warn('#startContinueButton not found even though the journey has not started yet');

        }
        // Attach click handler to submit button
        const submitButton = document.getElementById('sendButton');
        if (submitButton) {
            submitButton.addEventListener('click', handleSubmitClick, { once: false });
        } else {
            console.warn('#sendButton not found');

        }
        const chatContainer = document.getElementById('chatContainer');
        bindChatScrollWatcher(chatContainer);
        requestAnimationFrame(() => { scrollChatToBottom('smooth'); });
        enableVoiceScrollLock();
        
        // Attach click handler to mic button for recording
        const micButton = document.getElementById('micButton');
        if (micButton) {
            micButton.addEventListener('click', handleMicClick, { once: false });
        } else {
            console.warn('#micButton not found');
        }

        // Attach click handlers to volume icons
        const volUpIcon = document.getElementById('volumeUpIcon');
        const volOffIcon = document.getElementById('volumeOffIcon');
        const voiceSoundToggle = document.getElementById('voiceSoundToggle');
        if (voiceSoundToggle) {
            voiceSoundToggle.addEventListener('click', () => toggleMute(!volumeMuted));
        } else {
            if (volUpIcon) volUpIcon.addEventListener('click', () => toggleMute(true)); // clicking volume-up mutes
            if (volOffIcon) volOffIcon.addEventListener('click', () => toggleMute(false)); // clicking volume-off unmutes
        }
        // Sync initial state (icons and any pre-rendered audio tags)
        toggleMute(volumeMuted);

        // Attach handlers to any pre-rendered voice recordings
        try {
            document.querySelectorAll('audio.voice-recording').forEach(attachHandlersToVoiceRecording);
        } catch {}

        setupFeedbackForm();
    }

    function toggleMute(mute) {
        try {
            volumeMuted = !!mute;
            // Update icon visibility
            const volUpIcon = document.getElementById('volumeUpIcon');
            const volOffIcon = document.getElementById('volumeOffIcon');
            if (volUpIcon && volOffIcon) {
                if (volumeMuted) {
                    volUpIcon.classList.add('d-none');
                    volOffIcon.classList.remove('d-none');
                } else {
                    volUpIcon.classList.remove('d-none');
                    volOffIcon.classList.add('d-none');
                }
            }

            const toggleBtn = document.getElementById('voiceSoundToggle');
            if (toggleBtn) {
                toggleBtn.classList.toggle('btn-outline-secondary', volumeMuted);
                toggleBtn.classList.toggle('btn-primary', !volumeMuted);
                toggleBtn.classList.toggle('text-white', !volumeMuted);
                toggleBtn.setAttribute('aria-pressed', (!volumeMuted).toString());
            }

            // Apply to WebAudio gain node if present
            if (window.VoiceMode && window.VoiceMode.outputGain) {
                window.VoiceMode.outputGain.gain.setTargetAtTime(volumeMuted ? 0.0 : 1.0, window.VoiceMode.audioContext?.currentTime || 0, 0.01);
            }

            // Also apply to any HTML audio elements rendered for completed messages
            try {
                document.querySelectorAll('audio.voice-recording').forEach(a => {
                    a.muted = volumeMuted;
                });
            } catch {}
        } catch (e) {
            console.warn('⚠️ Volume toggle failed:', e);
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
            
            // Scroll to the new message and reset autoscroll for this stream
            startStreamScrollSession();
            requestAnimationFrame(() => { scrollChatToBottom('smooth'); });
        }

        // Clear input and reset state for new response
        if (inputEl) {
            inputEl.value = '';
            resetTextareaToSingleLine();
        }
        outputComplete = false;
        window.VoiceMode.outputComplete = false;
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
            
            // Check for journey completion - but don't show message yet
            const journeyStatus = ((data?.journey_status ?? data?.joruney_status ?? data?.action) || '').toString().trim();
            if (journeyStatus === 'finish_journey') {
                const awaiting = !!data?.awaiting_feedback;
                const nextStatus = awaiting ? 'awaiting_feedback' : 'completed';
                const voiceElement = document.getElementById('journey-data-voice');
                if (voiceElement) {
                    voiceElement.setAttribute('data-status', nextStatus);
                    if (awaiting) {
                        voiceElement.setAttribute('data-needs-feedback', '1');
                    }
                }
                window.VoiceMode.status = nextStatus;

                // Set flag for completion message to be shown later
                window.VoiceMode.journeyCompleted = true;
                if (typeof data?.report === 'string' && data.report.trim()) {
                    window.VoiceMode.finalReport = data.report;
                }
                if (awaiting) {
                    setAwaitingFeedback(true);
                } else {
                    scheduleFinalReportRender();
                }
            } else {
                // Reset button state but keep inputs disabled for ongoing streaming
                if (textEl) textEl.textContent = 'Send';
                if (spinnerEl) spinnerEl.classList.add('d-none');
            }
        })
        .catch(error => {
            // Reset button state on error
            if (textEl) textEl.textContent = 'Send';
            if (spinnerEl) spinnerEl.classList.add('d-none');
            // Only enable inputs on actual network/server errors for non-completed journeys
            const voiceElement = document.getElementById('journey-data-voice');
            const statusAttr = voiceElement?.getAttribute('data-status');
            const isLocked = statusAttr === 'completed' || statusAttr === 'awaiting_feedback';
            if (!isLocked) {
                enableInputs();
            }
        });
    }

    function handleSentPacket(e) {
        // Handle styles sync from backend (paragraph classes mapping)
        if (e.type === 'styles' && e.message) {
            window.VoiceMode.paragraphStyles = parseIncomingParagraphStyles(e.message);
            console.log('🎨 VoiceMode styles config updated');
            return; // styles event doesn't carry text/audio
        }
        if (e.type === 'stepinfo' && e.message) {
            try {
                const chatContainer = document.getElementById('chatContainer');
                if (chatContainer) {
                    const normalizedTitle = String(e.message).trim();
                    const existingHeadings = chatContainer.querySelectorAll('.step-info h4');
                    if (existingHeadings && existingHeadings.length) {
                        const lastHeading = existingHeadings[existingHeadings.length - 1];
                        if (lastHeading && lastHeading.textContent.trim() === normalizedTitle) {
                            return; // identical title already rendered most recently
                        }
                    }
                    const stepDiv = document.createElement('div');
                    stepDiv.className = 'step-info';
                    const heading = document.createElement('h4');
                    // Use the incoming message as the step text when present, otherwise fall back to the provided default
                    heading.textContent = normalizedTitle;
                    stepDiv.appendChild(heading);
                    chatContainer.appendChild(stepDiv);

                    // Small delay before positioning relative to last AI message
                    setTimeout(() => {
                        try {
                            const aiMessages = chatContainer.querySelectorAll('.message.ai-message');
                            if (aiMessages && aiMessages.length) {
                                const lastAi = aiMessages[aiMessages.length - 1];
                                if (lastAi && lastAi !== stepDiv) {
                                    chatContainer.insertBefore(stepDiv, lastAi);
                                }
                            }
                            requestAnimationFrame(() => { scrollChatToBottom('smooth'); });
                        } catch (err2) {
                            console.warn('⚠️ Step info post-insert adjustment failed:', err2);
                        }
                    }, 120); // slight delay to let AI message render first
                }
            } catch (err) {
                console.error('⚠️ Failed to render step info:', err);
            }
            return;
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
            updateProgressBar(e.message);
        }

        // Completion of a streaming segment - mark as complete but don't stop throttling
        if (e.type === 'complete') {
            window.VoiceMode.streamingComplete = true;
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
            outputComplete = true;
            window.VoiceMode.outputComplete = true;
            tryRevealFeedbackForm();
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
                    try { audioElem.muted = !!volumeMuted; } catch {}
                    const sourceElem = document.createElement('source');
                    sourceElem.src = `/journeys/aivoice/${jsrid}`;
                    sourceElem.type = 'audio/mpeg';
                    sourceElem.setAttribute('controlsList', 'nodownload noplaybackrate');
                    audioElem.appendChild(sourceElem);
                    audioElem.appendChild(sourceElem);
                    audioElem.appendChild(document.createTextNode('Your browser does not support the audio element.'));
                    lastAiMessage.appendChild(audioElem);
                    // Ensure playback coordination handlers are attached
                    attachHandlersToVoiceRecording(audioElem);
                    // Scroll to the completion message unless user opted out this stream
                    scrollChatToBottomIfAllowed('smooth');
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
                    // Hide input controls when journey is complete
                    const inputGroup = document.getElementById('inputGroup');
                    if (inputGroup) {
                        inputGroup.style.display = 'none';
                    }
                    // Also hide the entire chat input wrapper container
                    try {
                        const wrapper = document.querySelector('.chat-input-wrapper');
                        if (wrapper) wrapper.style.display = 'none';
                    } catch {}
                    // Scroll to the completion message unless user opted out this stream
                    scrollChatToBottomIfAllowed('smooth');

                    if (window.VoiceMode.awaitingFeedback) {
                        tryRevealFeedbackForm();
                    } else {
                        scheduleFinalReportRender();
                    }
                }
                
                // Clear the flag
                window.VoiceMode.journeyCompleted = false;
                console.log('🎯 Journey completed - completion message shown after streaming finished');
            }
            
            // Only re-enable inputs if journey is not completed
            const voiceElement = document.getElementById('journey-data-voice');
            const statusAttr = voiceElement?.getAttribute('data-status');
            const isLocked = statusAttr === 'completed' || statusAttr === 'awaiting_feedback';
            if (!isLocked) {
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

    function hideInputZone() {
        const zone = document.querySelector('.journey-input-zone');
        if (zone) zone.classList.add('d-none');
        const group = document.getElementById('inputGroup');
        if (group) group.classList.add('d-none');
        const wrapper = document.querySelector('.chat-input-wrapper');
        if (wrapper) wrapper.classList.add('d-none');
    }

    function enableInputs() {
        const inputEl = document.getElementById('voiceMessageInput') || document.getElementById('messageInput');
        const micEl = document.getElementById('micButton');
        const sendEl = document.getElementById('sendButton');
        if (inputEl) inputEl.disabled = false;
        if (micEl) micEl.disabled = false;
        if (sendEl) sendEl.disabled = false;
    }

    // Ensure the chat textarea collapses back to a single line (UX tidy-up)
    function resetTextareaToSingleLine() {
        try {
            const ta = document.getElementById('voiceMessageInput') || document.getElementById('messageInput');
            if (!ta) return;
            // Force single-line appearance
            ta.rows = 1;
            ta.style.height = '';
            ta.style.overflowY = 'hidden';
            // Trigger any autosize listeners to recalc from empty state
            try {
                const ev = new Event('input', { bubbles: true });
                ta.dispatchEvent(ev);
            } catch {}
        } catch {}
    }

    function setupFeedbackForm() {
        const form = document.getElementById('journeyFeedbackForm');
        if (!form || form.__voiceFeedbackBound) return;
        form.__voiceFeedbackBound = true;
        form.addEventListener('submit', handleFeedbackFormSubmit);
    }

    function handleFeedbackFormSubmit(event) {
        event.preventDefault();
        if (feedbackSubmitting) return;
        clearFeedbackAlerts();
        const form = event.currentTarget;
        const ratingInput = form.querySelector('input[name="journey_rating"]:checked');
        const feedbackInput = document.getElementById('journeyFeedbackText');
        const rating = ratingInput ? parseInt(ratingInput.value, 10) : null;
        const feedbackText = feedbackInput ? feedbackInput.value.trim() : '';

        if (!rating || rating < 1 || rating > 5) {
            showFeedbackError('Select a rating between 1 and 5.');
            return;
        }
        if (!feedbackText) {
            showFeedbackError('Please share your thoughts in the feedback box.');
            return;
        }

        submitFeedbackPayload(rating, feedbackText);
    }

    function submitFeedbackPayload(rating, feedbackText) {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        if (!csrfToken) {
            showFeedbackError('Missing CSRF token. Reload and try again.');
            return;
        }

        const endpoint = window.VoiceMode.feedbackEndpoint || '/journeys/voice/feedback';
        toggleFeedbackSpinner(true);
        setFeedbackFormDisabled(true);
        feedbackSubmitting = true;

        fetch(endpoint, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                attemptid: parseInt(window.VoiceMode.attemptId, 10),
                rating,
                feedback: feedbackText
            })
        })
        .then(async (response) => {
            if (!response.ok) {
                const err = await response.json().catch(() => ({}));
                throw new Error(err?.message || `HTTP ${response.status}: ${response.statusText}`);
            }
            return response.json();
        })
        .then((data) => {
            window.VoiceMode.feedbackSubmitted = true;
            setAwaitingFeedback(false);
            updateProgressBar('100');
            hideFeedbackForm();
            appendFeedbackSummary(data?.rating ?? rating, data?.feedback ?? feedbackText);
            if (typeof data?.report === 'string' && data.report.trim()) {
                window.VoiceMode.finalReport = data.report;
            }
            scheduleFinalReportRender();
            showFeedbackSuccess('Thanks for your feedback!');
            const voiceEl = document.getElementById('journey-data-voice');
            if (voiceEl) {
                voiceEl.setAttribute('data-has-feedback', '1');
                voiceEl.setAttribute('data-needs-feedback', '0');
                voiceEl.setAttribute('data-status', 'completed');
            }
            window.VoiceMode.status = 'completed';
        })
        .catch((error) => {
            console.error('❌ Feedback submission failed:', error);
            showFeedbackError(error?.message || 'Unable to submit feedback. Please try again.');
            setFeedbackFormDisabled(false);
        })
        .finally(() => {
            feedbackSubmitting = false;
            toggleFeedbackSpinner(false);
        });
    }

    function showFeedbackForm() {
        const wrapper = document.getElementById('feedbackFormWrapper');
        if (wrapper) {
            wrapper.classList.remove('d-none');
            requestAnimationFrame(() => { scrollBodyToBottom('smooth'); });
        }
    }

    function hideFeedbackForm() {
        const wrapper = document.getElementById('feedbackFormWrapper');
        if (wrapper) {
            wrapper.classList.add('d-none');
        }
    }

    function toggleFeedbackSpinner(active) {
        const spinner = document.getElementById('feedbackSubmitSpinner');
        const label = document.querySelector('#feedbackSubmitButton .feedback-submit-label');
        if (spinner) spinner.classList.toggle('d-none', !active);
        if (label) label.textContent = active ? 'Submitting…' : 'Submit feedback';
    }

    function showFeedbackError(message) {
        const errorEl = document.getElementById('feedbackError');
        if (errorEl) {
            errorEl.textContent = message;
            errorEl.classList.remove('d-none');
        }
    }

    function showFeedbackSuccess(message) {
        const successEl = document.getElementById('feedbackSuccess');
        if (successEl) {
            successEl.textContent = message;
            successEl.classList.remove('d-none');
        }
        const errorEl = document.getElementById('feedbackError');
        if (errorEl) errorEl.classList.add('d-none');
    }

    function clearFeedbackAlerts() {
        const errorEl = document.getElementById('feedbackError');
        if (errorEl) errorEl.classList.add('d-none');
        const successEl = document.getElementById('feedbackSuccess');
        if (successEl) successEl.classList.add('d-none');
    }

    function setFeedbackFormDisabled(disabled) {
        const form = document.getElementById('journeyFeedbackForm');
        if (!form) return;
        Array.from(form.elements || []).forEach((el) => {
            if (typeof el.disabled !== 'undefined') {
                el.disabled = disabled;
            }
        });
    }

    function appendFeedbackSummary(rating, feedbackText) {
        const chatContainer = document.getElementById('chatContainer');
        if (!chatContainer) return;
        let summary = chatContainer.querySelector('.user-feedback-summary');
        if (!summary) {
            summary = document.createElement('div');
            summary.className = 'message system-message user-feedback-summary mt-2';
            chatContainer.appendChild(summary);
        }
        summary.innerHTML = '';

        const heading = document.createElement('strong');
        heading.className = 'd-block mb-1';
        heading.textContent = 'Your feedback';
        summary.appendChild(heading);

        if (rating) {
            const badge = document.createElement('span');
            badge.className = 'badge bg-primary mb-2';
            badge.textContent = `Rating: ${rating}/5`;
            summary.appendChild(badge);
        }

        if (feedbackText) {
            const copy = document.createElement('p');
            copy.className = 'mb-0';
            copy.textContent = feedbackText;
            summary.appendChild(copy);
        }

        requestAnimationFrame(() => { scrollBodyToBottom('smooth'); });
    }

    function setAwaitingFeedback(active) {
        awaitingFeedback = !!active;
        window.VoiceMode.awaitingFeedback = awaitingFeedback;
        if (awaitingFeedback) {
            window.VoiceMode.status = 'awaiting_feedback';
            const voiceEl = document.getElementById('journey-data-voice');
            if (voiceEl) {
                voiceEl.setAttribute('data-status', 'awaiting_feedback');
                voiceEl.setAttribute('data-needs-feedback', window.VoiceMode.feedbackSubmitted ? '0' : '1');
            }
            hideInputZone();
            disableInputs();
            gateProgressBar();
            tryRevealFeedbackForm();
        } else {
            hideFeedbackForm();
            clearFeedbackAlerts();
            const progressBar = document.getElementById('progress-bar');
            if (progressBar) progressBar.classList.remove('progress-awaiting-feedback');
        }
    }

    function tryRevealFeedbackForm() {
        if (!awaitingFeedback || !outputComplete) return;
        showFeedbackForm();
    }

    function gateProgressBar() {
        const progressBar = document.getElementById('progress-bar');
        if (!progressBar) return;
        progressBar.classList.add('progress-awaiting-feedback');
        const numeric = parseFloat(progressBar.style.width);
        if (!Number.isFinite(numeric) || numeric > 95) {
            progressBar.style.width = '95%';
        }
    }

    function updateProgressBar(value) {
        const progressBar = document.getElementById('progress-bar');
        if (!progressBar) return;
        const strValue = typeof value === 'string' ? value.trim() : String(value || '');
        const parsed = parseFloat(strValue.replace('%', ''));
        if (awaitingFeedback && Number.isFinite(parsed) && parsed >= 100 && !window.VoiceMode.feedbackSubmitted) {
            gateProgressBar();
            return;
        }
        const width = Number.isFinite(parsed) ? parsed : 0;
        progressBar.style.width = `${width}%`;
        if (awaitingFeedback && !window.VoiceMode.feedbackSubmitted) {
            progressBar.classList.add('progress-awaiting-feedback');
        } else {
            progressBar.classList.remove('progress-awaiting-feedback');
        }
    }

    function scheduleFinalReportRender() {
        if (awaitingFeedback || reportRenderTimer) return;
        const reportCandidate = typeof window.VoiceMode.finalReport === 'string' ? window.VoiceMode.finalReport.trim() : '';
        if (!reportCandidate) return;
        reportRenderTimer = setTimeout(() => {
            try {
                renderFinalReport(reportCandidate);
            } finally {
                reportRenderTimer = null;
                window.VoiceMode.finalReport = null;
            }
        }, REPORT_RENDER_DELAY_MS);
    }

    function renderFinalReport(html) {
        if (!html) return;
        const chatContainer = document.getElementById('chatContainer');
        if (!chatContainer) return;
        let wrapper = chatContainer.querySelector('.report-message');
        if (!wrapper) {
            wrapper = document.createElement('div');
            wrapper.className = 'message system-message report-message mt-2';
            chatContainer.appendChild(wrapper);
        }
        wrapper.innerHTML = html;
        requestAnimationFrame(() => { scrollBodyToBottom('smooth'); });
    }

    function enableVoiceScrollLock() {
        try {
            if (window.VoiceMode._scrollLockActive) {
                return;
            }
            const chatContainer = document.getElementById('chatContainer');
            if (!chatContainer) {
                return;
            }

            const htmlEl = document.documentElement;
            const bodyEl = document.body;
            htmlEl.classList.add('voice-scroll-locked');
            bodyEl.classList.add('voice-scroll-locked');

            const allowNativeScroll = (target) => {
                if (!target) return false;
                return Boolean(target.closest('textarea, input, select, [data-scroll-exempt="true"]'));
            };

            const wheelOptions = { passive: false };
            const wheelHandler = (event) => {
                if (!chatContainer || allowNativeScroll(event.target)) {
                    return;
                }
                event.preventDefault();
                chatContainer.scrollTop += event.deltaY;
            };
            window.addEventListener('wheel', wheelHandler, wheelOptions);

            let touchProxyActive = false;
            let lastTouchY = 0;
            const touchStartOptions = false;
            const touchMoveOptions = { passive: false };
            const touchStartHandler = (event) => {
                if (!chatContainer) return;
                if (allowNativeScroll(event.target)) {
                    touchProxyActive = false;
                    return;
                }
                touchProxyActive = true;
                lastTouchY = event.touches[0]?.clientY || 0;
            };
            const touchMoveHandler = (event) => {
                if (!touchProxyActive || !chatContainer) return;
                const currentY = event.touches[0]?.clientY;
                if (typeof currentY !== 'number') return;
                const delta = lastTouchY - currentY;
                if (delta === 0) return;
                event.preventDefault();
                chatContainer.scrollTop += delta;
                lastTouchY = currentY;
            };
            const touchEndHandler = () => {
                touchProxyActive = false;
            };
            window.addEventListener('touchstart', touchStartHandler, touchStartOptions);
            window.addEventListener('touchmove', touchMoveHandler, touchMoveOptions);
            window.addEventListener('touchend', touchEndHandler, false);
            window.addEventListener('touchcancel', touchEndHandler, false);

            const keyHandler = (event) => {
                if (!chatContainer || allowNativeScroll(event.target)) {
                    return;
                }
                const viewportStep = chatContainer.clientHeight * 0.9 || 200;
                let delta = 0;
                switch (event.key) {
                    case 'PageDown':
                    case ' ':
                        delta = viewportStep;
                        break;
                    case 'PageUp':
                        delta = -viewportStep;
                        break;
                    case 'ArrowDown':
                        delta = 40;
                        break;
                    case 'ArrowUp':
                        delta = -40;
                        break;
                    case 'Home':
                        delta = -chatContainer.scrollTop;
                        break;
                    case 'End':
                        delta = chatContainer.scrollHeight;
                        break;
                    default:
                        return;
                }
                event.preventDefault();
                chatContainer.scrollTop += delta;
            };
            window.addEventListener('keydown', keyHandler, false);

            const cleanup = () => {
                window.removeEventListener('wheel', wheelHandler, wheelOptions);
                window.removeEventListener('touchstart', touchStartHandler, touchStartOptions);
                window.removeEventListener('touchmove', touchMoveHandler, touchMoveOptions);
                window.removeEventListener('touchend', touchEndHandler, false);
                window.removeEventListener('touchcancel', touchEndHandler, false);
                window.removeEventListener('keydown', keyHandler, false);
                htmlEl.classList.remove('voice-scroll-locked');
                bodyEl.classList.remove('voice-scroll-locked');
                window.VoiceMode._scrollLockActive = false;
                if (window.VoiceMode._scrollLockCleanup === cleanup) {
                    window.VoiceMode._scrollLockCleanup = null;
                }
            };

            window.VoiceMode._scrollLockActive = true;
            window.VoiceMode._scrollLockCleanup = cleanup;
        } catch (err) {
            console.warn('⚠️ Failed to enable voice scroll lock:', err);
        }
    }

    function disableVoiceScrollLock() {
        try {
            if (typeof window.VoiceMode._scrollLockCleanup === 'function') {
                window.VoiceMode._scrollLockCleanup();
            }
        } catch (err) {
            console.warn('⚠️ Failed to disable voice scroll lock:', err);
        }
    }

    function handleStartContinueClick(e) {
        e.preventDefault();
        disableInputs();
        const voiceOverlay = document.getElementById('voiceOverlay');
        if (voiceOverlay) {
            voiceOverlay.classList.add('hidden');
            voiceOverlay.style.display = 'none';
        }
        const mobileBottomNav = document.querySelector('.mobile-bottom-nav');
        if (mobileBottomNav) {
            mobileBottomNav.classList.add('d-none');
            mobileBottomNav.style.setProperty('display', 'none', 'important');
        }
        const mobileTopBar = document.querySelector('.mobile-topbar');
        if (mobileTopBar) {
            mobileTopBar.classList.add('d-none');
            mobileTopBar.style.setProperty('display', 'none', 'important');
        }
        const btn = e.currentTarget || e.target;
        const classList = btn ? Array.from(btn.classList) : [];

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
            startStreamScrollSession();
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
                        // During streaming, keep following the latest output unless user scrolled up
                        scrollChatToBottomIfAllowed('auto');
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
                // Create a single gain node for output volume control and connect to destination
                try {
                    window.VoiceMode.outputGain = window.VoiceMode.audioContext.createGain();
                    window.VoiceMode.outputGain.gain.value = volumeMuted ? 0.0 : 1.0;
                    window.VoiceMode.outputGain.connect(window.VoiceMode.audioContext.destination);
                } catch (ge) {
                    console.warn('⚠️ Failed to create output gain node:', ge);
                }
            } catch (error) {
                console.error('❌ Failed to initialize audio context:', error);
                return;
            }
        }

        const audioContext = window.VoiceMode.audioContext;
        // Ensure gain node exists if context was present before volume feature was added
        if (audioContext && !window.VoiceMode.outputGain) {
            try {
                window.VoiceMode.outputGain = audioContext.createGain();
                window.VoiceMode.outputGain.gain.value = volumeMuted ? 0.0 : 1.0;
                window.VoiceMode.outputGain.connect(audioContext.destination);
            } catch (ge) {
                console.warn('⚠️ Late gain node creation failed:', ge);
            }
        }
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
                // Route through output gain (if available) so we can control volume
                if (window.VoiceMode.outputGain) {
                    try {
                        source.connect(window.VoiceMode.outputGain);
                    } catch (ce) {
                        console.warn('⚠️ Failed to connect source to gain node, falling back to destination:', ce);
                        source.connect(audioContext.destination);
                    }
                } else {
                    source.connect(audioContext.destination);
                }

                // Calculate when to start this chunk for seamless playback
                const now = audioContext.currentTime;
                const startTime = Math.max(now, window.VoiceMode.nextStartTime);
                
                source.start(startTime);
                // Mark active streaming
                activeStreamSources++;
                setReproductionInProgress(true);
                
                // Update the next start time for seamless continuation
                window.VoiceMode.nextStartTime = startTime + audioBuffer.duration;
                // Handle source ending
                source.onended = () => {
                    // Decrement active stream count and update flag
                    activeStreamSources = Math.max(0, activeStreamSources - 1);
                    if (activeStreamSources === 0) {
                        setReproductionInProgress(false);
                    }
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
        const voiceElement = document.getElementById('journey-data-voice');
        if (voiceElement) {
            voiceElement.setAttribute('data-has-started', '1');
        }
        window.VoiceMode.hasStarted = true;
        disableInputs();
        // Fresh start -> notify server to produce first response
        // Add new AI message div to chat container
        const chatContainer = document.getElementById('chatContainer');
        if (chatContainer) {
            const aiMessageDiv = document.createElement('div');
            aiMessageDiv.className = 'message ai-message';
            chatContainer.appendChild(aiMessageDiv);
            
            // Start a new autoscroll session for streaming output
            startStreamScrollSession();
            requestAnimationFrame(() => { scrollChatToBottom('smooth'); });
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
            stopAllVoiceRecordings(); // enforce no HTML audio while recording

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
                // Do not suspend or close context; volume is controlled via gain node
                if (window.VoiceMode.outputGain) {
                    window.VoiceMode.outputGain.gain.setTargetAtTime(volumeMuted ? 0.0 : 1.0, window.VoiceMode.audioContext.currentTime, 0.01);
                }
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

            // Consolidate every chunk into a single blob so we only upload once
            let uploadBlob = recordedBlob;
            if (!uploadBlob && recChunks && recChunks.length) {
                const mimeType = (mediaRecorder && mediaRecorder.mimeType) ? mediaRecorder.mimeType : 'audio/webm';
                uploadBlob = new Blob(recChunks, { type: mimeType });
            }
            if (!uploadBlob) {
                console.error('❌ No recorded audio available to upload');
                enableInputs();
                return;
            }

            await sendAudioChunk(uploadBlob, 0, true);
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
