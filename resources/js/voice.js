// VoiceMode Module — Handles voice streaming, recording, and step-based journey UI.
// Requires: app.js config + Echo setup (VoiceEcho created in app.js)

const { start } = require("@popperjs/core");
const { message } = require("laravel-mix/src/Log");

window.VoiceMode = (function () {

    // ═══════════════════════════════════════════════════════════════════
    //  CONSTANTS
    // ═══════════════════════════════════════════════════════════════════

    const REPORT_RENDER_DELAY_MS = 2000;
    const STREAM_SCROLL_GAP_PX  = 40;

    // ═══════════════════════════════════════════════════════════════════
    //  STATE
    // ═══════════════════════════════════════════════════════════════════

    // -- Core --
    let voiceStream = 0;
    let reproductioninprogress = false;
    let attemptId = 0;

    // -- Streaming pipeline --
    let textBuffer         = '';
    let audioChunks        = [];
    let audioBuffer        = [];
    let startedAt          = null;
    let paragraphStyles    = {};
    let wps                = 3.00;   // words-per-second throttle
    let outputComplete     = false;
    let jsridQueue         = [];     // queue of jsrid values; first → current div, rest → future divs

    // -- Recording --
    let recordtime          = 0;
    let mediaRecorder       = null;
    let recordingStream     = null;
    let recordingSessionId  = null;
    let isRecording         = false;
    let recordingTimeout    = null;
    let recChunks           = [];
    let recordingStartTime  = null;
    let recordingAudioContext = null;
    let analyser            = null;
    let animationFrameId    = null;
    let visualizerCanvas    = null;
    let visualizerCtx       = null;
    let recordingUIEl       = null;
    let countdownInterval   = null;
    let recordedBlob        = null;
    let sendWhenStopped     = false;

    // -- Volume --
    let volumeMuted = false;

    // -- Scroll --
    let streamScrollSession     = 0;
    let userLockedStreamScroll  = false;

    // -- Audio coordination --
    let activeStreamSources     = 0;
    let currentPlayingHtmlAudio = null;

    // -- Response queuing (ensures one response finishes streaming before next begins) --
    let pendingResponseQueue     = [];
    let queueingForNextResponse  = false;

    // -- Feedback / completion --
    let reportRenderTimer  = null;
    let awaitingFeedback   = false;
    let feedbackSubmitting = false;

    // ═══════════════════════════════════════════════════════════════════
    //  SCROLL HELPERS
    // ═══════════════════════════════════════════════════════════════════

    function scrollBodyToBottom(behavior = 'auto') {
        try {
            const target = Math.max(document.body.scrollHeight, document.documentElement.scrollHeight);
            window.scrollTo({ top: target, behavior });
        } catch (e) {
            window.scrollTo(0, document.body.scrollHeight || document.documentElement.scrollHeight || 0);
        }
    }

    function scrollChatToBottom(behavior = 'auto') {
        const cc = document.getElementById('chatContainer');
        if (!cc) { scrollBodyToBottom(behavior); return; }
        try { cc.scrollTo({ top: cc.scrollHeight, behavior }); }
        catch (e) { cc.scrollTop = cc.scrollHeight; }
    }

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
        chatContainer.addEventListener('scroll', () => {
            if (userLockedStreamScroll) return;
            const gap = chatContainer.scrollHeight - (chatContainer.scrollTop + chatContainer.clientHeight);
            if (gap > STREAM_SCROLL_GAP_PX) lockStreamAutoscroll();
        }, { passive: true });
        chatContainer.__voiceStreamScrollBound = true;
    }

    // ═══════════════════════════════════════════════════════════════════
    //  AUDIO PLAYBACK COORDINATION
    // ═══════════════════════════════════════════════════════════════════

    function setReproductionInProgress(active) {
        reproductioninprogress = !!active;
        window.VoiceMode.reproductioninprogress = reproductioninprogress;
        if (reproductioninprogress) stopAllVoiceRecordings();
    }

    function stopAllVoiceRecordings() {
        try {
            document.querySelectorAll('audio.voice-recording').forEach(a => {
                try { if (!a.paused) a.pause(); a.currentTime = 0; } catch {}
            });
        } catch {}
        currentPlayingHtmlAudio = null;
    }

    function attachHandlersToVoiceRecording(audioEl) {
        if (!audioEl || audioEl.__voiceHandlersAttached) return;
        audioEl.__voiceHandlersAttached = true;
        audioEl.addEventListener('play', () => {
            if (reproductioninprogress) {
                try { audioEl.pause(); audioEl.currentTime = 0; } catch {}
                return;
            }
            // Enforce single playback
            try {
                document.querySelectorAll('audio.voice-recording').forEach(other => {
                    if (other !== audioEl && !other.paused) try { other.pause(); } catch {}
                });
            } catch {}
            currentPlayingHtmlAudio = audioEl;
        });
        audioEl.addEventListener('ended', () => { if (currentPlayingHtmlAudio === audioEl) currentPlayingHtmlAudio = null; });
        audioEl.addEventListener('pause', () => { if (currentPlayingHtmlAudio === audioEl) currentPlayingHtmlAudio = null; });
    }

    function stopAudioPlayback() {
        try {
            if (window.VoiceMode.audioContext) {
                window.VoiceMode.nextStartTime = window.VoiceMode.audioContext.currentTime;
                if (window.VoiceMode.outputGain) {
                    window.VoiceMode.outputGain.gain.setTargetAtTime(
                        volumeMuted ? 0.0 : 1.0,
                        window.VoiceMode.audioContext.currentTime,
                        0.01
                    );
                }
            }
        } catch (e) {}
    }

    // ═══════════════════════════════════════════════════════════════════
    //  VOLUME CONTROL
    // ═══════════════════════════════════════════════════════════════════

    function toggleMute(mute) {
        try {
            volumeMuted = !!mute;
            const volUpIcon  = document.getElementById('volumeUpIcon');
            const volOffIcon = document.getElementById('volumeOffIcon');
            if (volUpIcon && volOffIcon) {
                volUpIcon.classList.toggle('d-none', volumeMuted);
                volOffIcon.classList.toggle('d-none', !volumeMuted);
            }
            const toggleBtn = document.getElementById('voiceSoundToggle');
            if (toggleBtn) {
                toggleBtn.classList.toggle('btn-outline-secondary', volumeMuted);
                toggleBtn.classList.toggle('btn-primary', !volumeMuted);
                toggleBtn.classList.toggle('text-white', !volumeMuted);
                toggleBtn.setAttribute('aria-pressed', (!volumeMuted).toString());
            }
            if (window.VoiceMode && window.VoiceMode.outputGain) {
                window.VoiceMode.outputGain.gain.setTargetAtTime(
                    volumeMuted ? 0.0 : 1.0,
                    window.VoiceMode.audioContext?.currentTime || 0,
                    0.01
                );
            }
            try {
                document.querySelectorAll('audio.voice-recording').forEach(a => { a.muted = volumeMuted; });
            } catch {}
        } catch (e) {}
    }

    // ═══════════════════════════════════════════════════════════════════
    //  PARAGRAPH STYLE PROCESSING
    // ═══════════════════════════════════════════════════════════════════

    function normalizeParagraphStylesMap(styleMap) {
        if (!styleMap || typeof styleMap !== 'object') return styleMap || {};
        if (Array.isArray(styleMap)) return styleMap;

        const keys = Object.keys(styleMap);
        if (!keys.length) return {};

        const numericKeys = keys.map(k => Number.parseInt(k, 10)).filter(Number.isFinite);
        if (numericKeys.length !== keys.length) return styleMap;

        const minIndex = Math.min(...numericKeys);
        if (minIndex === 0) return styleMap;

        const normalized = {};
        numericKeys.sort((a, b) => a - b).forEach((key, index) => {
            normalized[index] = styleMap[key] ?? styleMap[String(key)] ?? '';
        });
        return normalized;
    }

    function parseIncomingParagraphStyles(payload) {
        if (!payload) return {};
        if (typeof payload === 'object') return normalizeParagraphStylesMap(payload);
        if (typeof payload === 'string') {
            try { return normalizeParagraphStylesMap(JSON.parse(payload)); }
            catch { return normalizeParagraphStylesMap(payload); }
        }
        return {};
    }

    // ═══════════════════════════════════════════════════════════════════
    //  HTML TOKENIZATION & PARTIAL RENDERING
    //  (module-level so every function can use them)
    // ═══════════════════════════════════════════════════════════════════

    const VOID_ELEMENTS = new Set([
        'area','base','br','col','embed','hr','img','input',
        'link','meta','param','source','track','wbr'
    ]);

    function tokenizeHtml(html) {
        const container = document.createElement('div');
        container.innerHTML = html;
        const tokens = [];
        traverseNode(container, tokens);
        return tokens;
    }

    function traverseNode(node, tokens) {
        for (let child = node.firstChild; child; child = child.nextSibling) {
            if (child.nodeType === Node.ELEMENT_NODE) {
                const tag   = child.tagName.toLowerCase();
                const attrs = [...child.attributes].map(a => `${a.name}="${a.value}"`).join(' ');
                if (child.childNodes.length === 0 || VOID_ELEMENTS.has(tag)) {
                    tokens.push({ type: 'tag', html: child.outerHTML || `<${tag}${attrs ? ' ' + attrs : ''}>`, isOpen: false });
                    continue;
                }
                tokens.push({ type: 'tag', html: `<${tag}${attrs ? ' ' + attrs : ''}>`, isOpen: true, tag });
                traverseNode(child, tokens);
                tokens.push({ type: 'tag', html: `</${tag}>`, isOpen: false, tag });
            } else if (child.nodeType === Node.TEXT_NODE) {
                addTextTokens(child.nodeValue, tokens);
            }
        }
    }

    function addTextTokens(text, tokens) {
        const parts = text.split(/(\s+)/);
        for (let i = 0; i < parts.length; i++) {
            const p = parts[i];
            if (!p.length) continue;
            tokens.push(/^\s+$/.test(p)
                ? { type: 'space', text: p }
                : { type: 'word',  text: p });
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
                if (wordsAdded < wordLimit) { out.push(t.text); wordsAdded++; }
                else break;
            } else if (t.type === 'space') {
                if (wordsAdded <= wordLimit) out.push(t.text);
            }
        }

        // Close any open tags
        for (let i = openStack.length - 1; i >= 0; i--) {
            if (openStack[i]) out.push(`</${openStack[i]}>`);
        }
        return out.join('');
    }

    /**
     * Apply paragraph classes from the styles map to <p> tags by order.
     * Works on HTML strings that already contain <p> elements.
     */
    function applyParagraphStyles(html, stylesMap) {
        if (!stylesMap || typeof stylesMap !== 'object' || !Object.keys(stylesMap).length) return html;

        let pIndex = 0;
        return html.replace(/<p(\s[^>]*)?>|<p>/gi, (match) => {
            const cls = stylesMap[pIndex] ?? stylesMap[String(pIndex)] ?? '';
            pIndex++;
            if (!cls) return match;
            if (/class\s*=/i.test(match)) {
                return match.replace(/class\s*=\s*["']([^"']*)["']/i, (m, existing) => {
                    return `class="${existing} ${cls}"`;
                });
            }
            return `<p class="${cls}"` + match.slice(2);
        });
    }

    // ═══════════════════════════════════════════════════════════════════
    //  AUDIO ELEMENT FACTORY
    //  (reusable helper – creates an <audio> element for a given jsrid)
    // ═══════════════════════════════════════════════════════════════════

    function createAudioElement(jsrid) {
        const audio  = document.createElement('audio');
        audio.controls  = true;
        audio.className = 'mt-2 voice-recording';
        try { audio.muted = !!volumeMuted; } catch {}

        const source = document.createElement('source');
        source.src  = `/journeys/aivoice/${jsrid}`;
        source.type = 'audio/mpeg';
        source.setAttribute('controlsList', 'nodownload noplaybackrate');

        audio.appendChild(source);
        audio.appendChild(document.createTextNode('Your browser does not support the audio element.'));
        attachHandlersToVoiceRecording(audio);
        return audio;
    }

    // ═══════════════════════════════════════════════════════════════════
    //  RESPONSE QUEUING
    //  Ensures step-info and the next response wait until the current
    //  response has finished its throttled word-by-word rendering.
    // ═══════════════════════════════════════════════════════════════════

    /**
     * Returns true when the current response still has words being
     * revealed by the throttled renderer.
     */
    function isThrottlingInProgress() {
        const ts = window.VoiceMode.throttlingState;
        if (!ts) return false;
        return ts.displayedWordCount < ts.totalWordCount;
    }

    /**
     * Drain the pending-response queue once the current response has
     * been fully rendered.  Re-processes each queued event through
     * the normal handler; stops if a new queuing cycle begins.
     */
    function drainPendingResponseQueue() {
        if (pendingResponseQueue.length === 0) return;
        queueingForNextResponse = false;
        const events = pendingResponseQueue.slice();
        pendingResponseQueue = [];
        for (const event of events) {
            handleSentPacket(event);
            if (queueingForNextResponse) break;   // nested response → re-queue
        }
    }

    // ═══════════════════════════════════════════════════════════════════
    //  STREAMING PIPELINE
    // ═══════════════════════════════════════════════════════════════════

    /**
     * Flush the current (previous) stream before switching to a new step.
     * - Forces any remaining throttled text to render immediately.
     * - Attaches the audio element to the previous AI div.
     */
    function flushCurrentStream(chatContainer) {
        // Stop the throttling interval
        if (window.VoiceMode.throttlingInterval) {
            clearInterval(window.VoiceMode.throttlingInterval);
            window.VoiceMode.throttlingInterval = null;
        }

        const ts = window.VoiceMode.throttlingState;
        const prevDiv = chatContainer.querySelector('.message.ai-message:last-child');

        // Flush remaining words
        if (ts && ts.displayedWordCount < ts.totalWordCount && prevDiv) {
            ts.displayedWordCount = ts.totalWordCount;
            try { prevDiv.innerHTML = buildPartialHtml(ts.tokens, ts.totalWordCount); }
            catch {}
        }

        // Attach audio to the previous stream's div (only if it had actual content)
        if (prevDiv && prevDiv.textContent.trim() !== '') {
            const prevJsrid = prevDiv.getAttribute('data-jsrid');
            if (prevJsrid && !prevDiv.querySelector('.voice-recording')) {
                prevDiv.appendChild(createAudioElement(prevJsrid));
            }
        }
    }

    /**
     * Reset all streaming state for a fresh AI stream.
     */
    function resetStreamingState() {
        window.VoiceMode.textBuffer        = '';
        window.VoiceMode.startedAt         = null;
        window.VoiceMode.throttlingState   = null;
        window.VoiceMode.streamingComplete = false;
        outputComplete = false;
        window.VoiceMode.outputComplete = false;
    }

    /**
     * Main throttled text renderer + WebAudio audio consumer.
     * Called whenever new text or audio data arrives, or from the
     * ensureThrottlingCompletes interval.
     */
    function startStreamedAudioPlayback() {
        if (window.VoiceMode.startedAt === null) {
            window.VoiceMode.startedAt = Date.now();
            startStreamScrollSession();
        }

        // ── Text throttling ─────────────────────────────────────────
        const chatContainer = document.getElementById('chatContainer');
        const lastAiMessage = chatContainer ? chatContainer.querySelector('.message.ai-message:last-child') : null;

        if (lastAiMessage && window.VoiceMode.textBuffer) {
            if (!window.VoiceMode.throttlingState) {
                window.VoiceMode.throttlingState = {
                    lastRawBuffer:        '',
                    lastStylesKey:        '',
                    latestRawContent:   '',
                    tokens:             [],
                    displayedWordCount: 0,
                    totalWordCount:     0,
                    fractionalWordsCarry: 0,
                };
            }

            const ts = window.VoiceMode.throttlingState;

            // Re-tokenize when raw content OR styles change
            const currentStylesKey = JSON.stringify(window.VoiceMode.paragraphStyles || {});
            if (window.VoiceMode.textBuffer !== ts.lastRawBuffer || currentStylesKey !== ts.lastStylesKey) {
                ts.lastRawBuffer = window.VoiceMode.textBuffer;
                ts.lastStylesKey = currentStylesKey;
                try {
                    // Parse markdown and apply paragraph styles via StreamingUtils
                    let processed = window.VoiceMode.textBuffer;
                    const styles = window.VoiceMode.paragraphStyles;
                    if (window.StreamingUtils && typeof window.StreamingUtils.formatStreamingContent === 'function') {
                        processed = window.StreamingUtils.formatStreamingContent(processed, styles);
                    } else if (styles && Object.keys(styles).length) {
                        processed = applyParagraphStyles(processed, styles);
                    }
                    ts.latestRawContent = processed;
                } catch { ts.latestRawContent = window.VoiceMode.textBuffer; }

                try {
                    ts.tokens         = tokenizeHtml(ts.latestRawContent);
                    ts.totalWordCount = ts.tokens.filter(t => t.type === 'word').length;
                } catch {}
            }

            // Calculate how many words to show based on elapsed time × wps
            if (ts.totalWordCount > 0) {
                const elapsed = (Date.now() - window.VoiceMode.startedAt) / 1000;
                const target  = Math.min(ts.totalWordCount, Math.floor(elapsed * window.VoiceMode.wps));

                if (target > ts.displayedWordCount) {
                    ts.displayedWordCount = target;
                    const partialHtml = buildPartialHtml(ts.tokens, ts.displayedWordCount);

                    try {
                        if (window.StreamingUtils && lastAiMessage.querySelector('video,iframe') && /<\/(video|iframe)>/i.test(partialHtml)) {
                            window.StreamingUtils.preserveVideoWhileUpdating(lastAiMessage, partialHtml);
                        } else {
                            lastAiMessage.innerHTML = partialHtml;
                        }
                        scrollChatToBottomIfAllowed('auto');
                    } catch {}
                }
            }
        }

        // ── Audio playback ──────────────────────────────────────────
        if (!window.VoiceMode.audioBuffer || window.VoiceMode.audioBuffer.length === 0) return;

        // Lazy-init AudioContext
        if (!window.VoiceMode.audioContext) {
            try {
                window.VoiceMode.audioContext = new (window.AudioContext || window.webkitAudioContext)();
                try {
                    window.VoiceMode.outputGain = window.VoiceMode.audioContext.createGain();
                    window.VoiceMode.outputGain.gain.value = volumeMuted ? 0.0 : 1.0;
                    window.VoiceMode.outputGain.connect(window.VoiceMode.audioContext.destination);
                } catch {}
            } catch { return; }
        }

        const audioContext = window.VoiceMode.audioContext;

        // Ensure gain node exists
        if (audioContext && !window.VoiceMode.outputGain) {
            try {
                window.VoiceMode.outputGain = audioContext.createGain();
                window.VoiceMode.outputGain.gain.value = volumeMuted ? 0.0 : 1.0;
                window.VoiceMode.outputGain.connect(audioContext.destination);
            } catch {}
        }

        const sampleRate = 24000; // OpenAI Realtime API

        if (audioContext.state === 'suspended') {
            audioContext.resume().catch(() => {});
        }

        if (!window.VoiceMode.nextStartTime) {
            window.VoiceMode.nextStartTime = audioContext.currentTime;
        }

        while (window.VoiceMode.audioBuffer.length > 0) {
            const audioData = window.VoiceMode.audioBuffer.shift();
            try {
                const binaryString = atob(audioData);
                const bytes = new Uint8Array(binaryString.length);
                for (let i = 0; i < binaryString.length; i++) bytes[i] = binaryString.charCodeAt(i);

                const pcm16    = new Int16Array(bytes.buffer);
                const abuf     = audioContext.createBuffer(1, pcm16.length, sampleRate);
                const channel  = abuf.getChannelData(0);
                for (let i = 0; i < pcm16.length; i++) channel[i] = pcm16[i] / 32768.0;

                const src = audioContext.createBufferSource();
                src.buffer = abuf;
                try { src.connect(window.VoiceMode.outputGain || audioContext.destination); }
                catch { src.connect(audioContext.destination); }

                const now       = audioContext.currentTime;
                const startTime = Math.max(now, window.VoiceMode.nextStartTime);
                src.start(startTime);

                activeStreamSources++;
                setReproductionInProgress(true);
                window.VoiceMode.nextStartTime = startTime + abuf.duration;

                src.onended = () => {
                    activeStreamSources = Math.max(0, activeStreamSources - 1);
                    if (activeStreamSources === 0) setReproductionInProgress(false);
                    checkOutputComplete();
                };
            } catch {}
        }
    }

    function handleAudioChunk(e) {
        if (e.type === 'audio' && e.message) {
            window.VoiceMode.audioBuffer.push(e.message);
        }
        startStreamedAudioPlayback();
    }

    /**
     * After backend signals 'complete', keep polling until throttling catches up.
     * Guards against orphaned intervals when state has been reset by a new step.
     */
    function ensureThrottlingCompletes() {
        if (window.VoiceMode.throttlingInterval) return; // already running

        window.VoiceMode.throttlingInterval = setInterval(() => {
            const ts = window.VoiceMode.throttlingState;

            if (!ts) {
                // State was reset (new step flushed); nothing to do
                clearInterval(window.VoiceMode.throttlingInterval);
                window.VoiceMode.throttlingInterval = null;
                // Safety: drain any queued events
                if (pendingResponseQueue.length > 0) drainPendingResponseQueue();
                return;
            }

            if (ts.displayedWordCount < ts.totalWordCount) {
                startStreamedAudioPlayback();
            } else {
                clearInterval(window.VoiceMode.throttlingInterval);
                window.VoiceMode.throttlingInterval = null;
                checkOutputComplete();
            }
        }, 100);
    }

    /**
     * When both text and audio are fully consumed and streaming has ended,
     * finalise the AI message (attach audio, show feedback, re-enable inputs).
     */
    function checkOutputComplete() {
        const ts = window.VoiceMode.throttlingState;
        const textComplete      = ts && ts.displayedWordCount >= ts.totalWordCount;
        const audioComplete     = window.VoiceMode.audioBuffer && window.VoiceMode.audioBuffer.length === 0;
        const streamingComplete = window.VoiceMode.streamingComplete;

        if (!(textComplete && audioComplete && streamingComplete)) return;

        outputComplete = true;
        window.VoiceMode.outputComplete = true;
        tryRevealFeedbackForm();

        // Attach audio element to the last AI message
        const chatContainer = document.getElementById('chatContainer');
        const lastAiMessage = chatContainer ? chatContainer.querySelector('.message.ai-message:last-child') : null;
        if (lastAiMessage) {
            const jsrid = lastAiMessage.getAttribute('data-jsrid');
            if (jsrid && !lastAiMessage.querySelector('.voice-recording')) {
                lastAiMessage.appendChild(createAudioElement(jsrid));
                scrollChatToBottomIfAllowed('smooth');
            }
        }

        // Journey-complete post-processing
        if (window.VoiceMode.journeyCompleted) {
            if (chatContainer) {
                const sysMsg = document.createElement('div');
                sysMsg.className = 'message system-message text-muted small mt-2';
                sysMsg.textContent = 'This journey is complete. You may close this window or navigate away.';
                chatContainer.appendChild(sysMsg);

                const inputGroup = document.getElementById('inputGroup');
                if (inputGroup) inputGroup.style.display = 'none';
                try { const w = document.querySelector('.chat-input-wrapper'); if (w) w.style.display = 'none'; } catch {}

                scrollChatToBottomIfAllowed('smooth');

                if (window.VoiceMode.awaitingFeedback) tryRevealFeedbackForm();
                else scheduleFinalReportRender();
            }
            window.VoiceMode.journeyCompleted = false;
        }

        // Re-enable inputs unless journey is locked or next response queued
        const voiceElement = document.getElementById('journey-data-voice');
        const statusAttr   = voiceElement?.getAttribute('data-status');
        if (statusAttr !== 'completed' && statusAttr !== 'awaiting_feedback') {
            // Only re-enable if there is no queued next response waiting
            if (pendingResponseQueue.length === 0) {
                enableInputs();
            }
        }

        // Drain any events that were queued while this response was streaming
        if (pendingResponseQueue.length > 0) {
            drainPendingResponseQueue();
        }
    }

    // ═══════════════════════════════════════════════════════════════════
    //  EVENT HANDLER  (WebSocket packets)
    // ═══════════════════════════════════════════════════════════════════

    function handleSentPacket(e) {

        // ── Response queuing gate ───────────────────────────────────
        // If we are already queuing events for the next response,
        // stash this event and bail.  Progress events pass through
        // so the progress bar stays responsive.
        if (queueingForNextResponse && e.type !== 'progress') {
            pendingResponseQueue.push(e);
            return;
        }

        // ── styles ──────────────────────────────────────────────────
        if (e.type === 'styles' && e.message) {
            window.VoiceMode.paragraphStyles = parseIncomingParagraphStyles(e.message);
            return;
        }

        // ── stepinfo ────────────────────────────────────────────────
        if (e.type === 'stepinfo' && e.message) {
            try {
                const chatContainer = document.getElementById('chatContainer');
                if (!chatContainer) return;

                let stepTitle  = '';
                let stepAction = 'step_start';
                try {
                    const parsed = JSON.parse(e.message);
                    stepTitle  = (parsed.title  || '').trim();
                    stepAction = (parsed.action || 'step_start').trim();
                } catch {
                    stepTitle = String(e.message).trim();
                }

                // ── Queue if current response is still streaming ────
                // step_start / step_finish_journey create new divs.
                // If the previous response is still being word-throttled,
                // defer this event (and all subsequent ones) until it
                // finishes rendering naturally.
                if ((stepAction === 'step_start' || stepAction === 'step_finish_journey') && isThrottlingInProgress()) {
                    queueingForNextResponse = true;
                    pendingResponseQueue.push(e);
                    return;
                }

                // step_retry / step_followup / step_complete stay in the current div
                if (stepAction === 'step_retry' || stepAction === 'step_followup' || stepAction === 'step_complete') {
                    return;
                }

                // Only step_start / step_finish_journey create new divs
                const hasExisting = chatContainer.querySelectorAll('.message').length > 0;

                // Duplicate guard
                const headings = chatContainer.querySelectorAll('.step-info h4');
                if (headings.length) {
                    const last = headings[headings.length - 1];
                    if (last && last.textContent.trim() === stepTitle) return;
                }

                // ── Flush the previous stream ───────────────────────
                flushCurrentStream(chatContainer);

                // ── Step header ─────────────────────────────────────
                const stepDiv = document.createElement('div');
                stepDiv.className = 'step-info';
                stepDiv.setAttribute('data-step-action', stepAction);
                if (stepAction === 'step_finish_journey') stepDiv.classList.add('step-info-finish');

                const heading = document.createElement('h4');
                heading.textContent = stepTitle;
                stepDiv.appendChild(heading);

                // Reuse existing empty ai-message div (created by handleSubmitClick / startNewJourney)
                // instead of appending a duplicate that stays empty.
                const existingEmpty = chatContainer.querySelector('.message.ai-message:last-child');
                const reuseDiv = existingEmpty && existingEmpty.innerHTML.trim() === '';

                if (reuseDiv) {
                    // Insert step header before the empty div
                    chatContainer.insertBefore(stepDiv, existingEmpty);
                    // Assign queued jsrid if the div doesn't have one yet
                    if (jsridQueue.length > 0 && !existingEmpty.getAttribute('data-jsrid')) {
                        existingEmpty.setAttribute('data-jsrid', jsridQueue.shift());
                    }
                } else {
                    chatContainer.appendChild(stepDiv);
                    const newDiv = document.createElement('div');
                    newDiv.className = 'message ai-message';
                    if (jsridQueue.length > 0) {
                        newDiv.setAttribute('data-jsrid', jsridQueue.shift());
                    }
                    chatContainer.appendChild(newDiv);
                }

                // Reset streaming state for the new AI stream
                resetStreamingState();

                requestAnimationFrame(() => { scrollChatToBottom('smooth'); });
            } catch {}
            return;
        }

        // ── jsrid ───────────────────────────────────────────────────
        if (e.type === 'jsrid' && e.message) {
            jsridQueue.push(e.message);
            window.VoiceMode.latestJsrid = e.message;

            // Assign to current div if it has no jsrid yet
            const chatContainer = document.getElementById('chatContainer');
            if (chatContainer) {
                const lastAi = chatContainer.querySelector('.message.ai-message:last-child');
                if (lastAi && !lastAi.getAttribute('data-jsrid')) {
                    lastAi.setAttribute('data-jsrid', jsridQueue.shift());
                }
            }
        }

        // ── text streaming ──────────────────────────────────────────
        if ((e.type === 'text' || e.type === 'response_text') && e.message) {
            window.VoiceMode.textBuffer += e.message;
            startStreamedAudioPlayback();
        }

        // ── audio chunks ────────────────────────────────────────────
        if ((e.type === 'audio' || e.type === 'response_audio') && e.message) {
            handleAudioChunk(e);
        }

        // ── progress ────────────────────────────────────────────────
        if (e.type === 'progress' && e.message != null) {
            updateProgressBar(e.message);
        }

        // ── stream complete ─────────────────────────────────────────
        if (e.type === 'complete') {
            window.VoiceMode.streamingComplete = true;
            ensureThrottlingCompletes();
        }
    }

    // ═══════════════════════════════════════════════════════════════════
    //  PROGRESS BAR
    // ═══════════════════════════════════════════════════════════════════

    function updateProgressBar(value) {
        const bar = document.getElementById('progress-bar');
        if (!bar) return;
        const str    = typeof value === 'string' ? value.trim() : String(value || '');
        const parsed = parseFloat(str.replace('%', ''));
        if (awaitingFeedback && Number.isFinite(parsed) && parsed >= 100 && !window.VoiceMode.feedbackSubmitted) {
            gateProgressBar();
            return;
        }
        const width = Number.isFinite(parsed) ? parsed : 0;
        bar.style.width = `${width}%`;
        bar.classList.toggle('progress-awaiting-feedback', awaitingFeedback && !window.VoiceMode.feedbackSubmitted);
    }

    function gateProgressBar() {
        const bar = document.getElementById('progress-bar');
        if (!bar) return;
        bar.classList.add('progress-awaiting-feedback');
        const n = parseFloat(bar.style.width);
        if (!Number.isFinite(n) || n > 95) bar.style.width = '95%';
    }

    // ═══════════════════════════════════════════════════════════════════
    //  INPUT CONTROLS
    // ═══════════════════════════════════════════════════════════════════

    function disableInputs(mictoo = true) {
        const inputEl = document.getElementById('voiceMessageInput') || document.getElementById('messageInput');
        const micEl   = document.getElementById('micButton');
        const sendEl  = document.getElementById('sendButton');
        if (inputEl) inputEl.disabled = true;
        if (mictoo && micEl) micEl.disabled = true;
        if (sendEl)  sendEl.disabled  = true;
    }

    function enableInputs() {
        const inputEl = document.getElementById('voiceMessageInput') || document.getElementById('messageInput');
        const micEl   = document.getElementById('micButton');
        const sendEl  = document.getElementById('sendButton');
        if (inputEl) inputEl.disabled = false;
        if (micEl)   micEl.disabled   = false;
        if (sendEl)  sendEl.disabled  = false;
    }

    function hideInputZone() {
        const zone    = document.querySelector('.journey-input-zone');
        const group   = document.getElementById('inputGroup');
        const wrapper = document.querySelector('.chat-input-wrapper');
        if (zone)    zone.classList.add('d-none');
        if (group)   group.classList.add('d-none');
        if (wrapper) wrapper.classList.add('d-none');
    }

    function resetTextareaToSingleLine() {
        try {
            const ta = document.getElementById('voiceMessageInput') || document.getElementById('messageInput');
            if (!ta) return;
            ta.rows = 1;
            ta.style.height    = '';
            ta.style.overflowY = 'hidden';
            try { ta.dispatchEvent(new Event('input', { bubbles: true })); } catch {}
        } catch {}
    }

    // ═══════════════════════════════════════════════════════════════════
    //  FEEDBACK FORM
    // ═══════════════════════════════════════════════════════════════════

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

        const form          = event.currentTarget;
        const ratingInput   = form.querySelector('input[name="journey_rating"]:checked');
        const feedbackInput = document.getElementById('journeyFeedbackText');
        const rating        = ratingInput ? parseInt(ratingInput.value, 10) : null;
        const feedbackText  = feedbackInput ? feedbackInput.value.trim() : '';

        if (!rating || rating < 1 || rating > 5) { showFeedbackError('Select a rating between 1 and 5.'); return; }
        if (!feedbackText)                         { showFeedbackError('Please share your thoughts in the feedback box.'); return; }

        submitFeedbackPayload(rating, feedbackText);
    }

    function submitFeedbackPayload(rating, feedbackText) {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        if (!csrfToken) { showFeedbackError('Missing CSRF token. Reload and try again.'); return; }

        const endpoint = window.VoiceMode.feedbackEndpoint || '/journeys/voice/feedback';
        toggleFeedbackSpinner(true);
        setFeedbackFormDisabled(true);
        feedbackSubmitting = true;

        fetch(endpoint, {
            method: 'POST',
            headers: {
                'Content-Type':     'application/json',
                'Accept':           'application/json',
                'X-CSRF-TOKEN':     csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                attemptid: parseInt(window.VoiceMode.attemptId, 10),
                rating,
                feedback: feedbackText,
            }),
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
                voiceEl.setAttribute('data-has-feedback',  '1');
                voiceEl.setAttribute('data-needs-feedback', '0');
                voiceEl.setAttribute('data-status', 'completed');
            }
            window.VoiceMode.status = 'completed';
        })
        .catch((error) => {
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
        if (wrapper) wrapper.classList.add('d-none');
    }

    function toggleFeedbackSpinner(active) {
        const spinner = document.getElementById('feedbackSubmitSpinner');
        const label   = document.querySelector('#feedbackSubmitButton .feedback-submit-label');
        if (spinner) spinner.classList.toggle('d-none', !active);
        if (label)   label.textContent = active ? 'Submitting…' : 'Submit feedback';
    }

    function showFeedbackError(msg) {
        const el = document.getElementById('feedbackError');
        if (el) { el.textContent = msg; el.classList.remove('d-none'); }
    }

    function showFeedbackSuccess(msg) {
        const el = document.getElementById('feedbackSuccess');
        if (el) { el.textContent = msg; el.classList.remove('d-none'); }
        const err = document.getElementById('feedbackError');
        if (err) err.classList.add('d-none');
    }

    function clearFeedbackAlerts() {
        const err = document.getElementById('feedbackError');
        const suc = document.getElementById('feedbackSuccess');
        if (err) err.classList.add('d-none');
        if (suc) suc.classList.add('d-none');
    }

    function setFeedbackFormDisabled(disabled) {
        const form = document.getElementById('journeyFeedbackForm');
        if (!form) return;
        Array.from(form.elements || []).forEach(el => {
            if (typeof el.disabled !== 'undefined') el.disabled = disabled;
        });
    }

    function appendFeedbackSummary(rating, feedbackText) {
        const cc = document.getElementById('chatContainer');
        if (!cc) return;
        let summary = cc.querySelector('.user-feedback-summary');
        if (!summary) {
            summary = document.createElement('div');
            summary.className = 'message system-message user-feedback-summary mt-2';
            cc.appendChild(summary);
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
            const p = document.createElement('p');
            p.className = 'mb-0';
            p.textContent = feedbackText;
            summary.appendChild(p);
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
            const bar = document.getElementById('progress-bar');
            if (bar) bar.classList.remove('progress-awaiting-feedback');
        }
    }

    function tryRevealFeedbackForm() {
        if (!awaitingFeedback || !outputComplete) return;
        showFeedbackForm();
    }

    function scheduleFinalReportRender() {
        if (awaitingFeedback || reportRenderTimer) return;
        const html = typeof window.VoiceMode.finalReport === 'string' ? window.VoiceMode.finalReport.trim() : '';
        if (!html) return;
        reportRenderTimer = setTimeout(() => {
            try { renderFinalReport(html); }
            finally { reportRenderTimer = null; window.VoiceMode.finalReport = null; }
        }, REPORT_RENDER_DELAY_MS);
    }

    function renderFinalReport(html) {
        if (!html) return;
        const cc = document.getElementById('chatContainer');
        if (!cc) return;
        let wrapper = cc.querySelector('.report-message');
        if (!wrapper) {
            wrapper = document.createElement('div');
            wrapper.className = 'message system-message report-message mt-2';
            cc.appendChild(wrapper);
        }
        wrapper.innerHTML = html;
        requestAnimationFrame(() => { scrollBodyToBottom('smooth'); });
    }

    // ═══════════════════════════════════════════════════════════════════
    //  SCROLL LOCK  (redirect page-level scroll → chatContainer)
    // ═══════════════════════════════════════════════════════════════════

    function enableVoiceScrollLock() {
        try {
            if (window.VoiceMode._scrollLockActive) return;

            const chatContainer = document.getElementById('chatContainer');
            if (!chatContainer) return;

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
                if (!chatContainer || allowNativeScroll(event.target)) return;
                event.preventDefault();
                chatContainer.scrollTop += event.deltaY;
            };
            window.addEventListener('wheel', wheelHandler, wheelOptions);

            let touchProxyActive = false;
            let lastTouchY = 0;
            const touchStartHandler = (event) => {
                if (!chatContainer) return;
                if (allowNativeScroll(event.target)) { touchProxyActive = false; return; }
                touchProxyActive = true;
                lastTouchY = event.touches[0]?.clientY || 0;
            };
            const touchMoveHandler = (event) => {
                if (!touchProxyActive || !chatContainer) return;
                const y = event.touches[0]?.clientY;
                if (typeof y !== 'number') return;
                const delta = lastTouchY - y;
                if (delta === 0) return;
                event.preventDefault();
                chatContainer.scrollTop += delta;
                lastTouchY = y;
            };
            const touchEndHandler = () => { touchProxyActive = false; };

            window.addEventListener('touchstart', touchStartHandler, false);
            window.addEventListener('touchmove',  touchMoveHandler, { passive: false });
            window.addEventListener('touchend',   touchEndHandler,  false);
            window.addEventListener('touchcancel', touchEndHandler, false);

            const keyHandler = (event) => {
                if (!chatContainer || allowNativeScroll(event.target)) return;
                const step = chatContainer.clientHeight * 0.9 || 200;
                let delta = 0;
                switch (event.key) {
                    case 'PageDown': case ' ':  delta = step; break;
                    case 'PageUp':              delta = -step; break;
                    case 'ArrowDown':           delta = 40; break;
                    case 'ArrowUp':             delta = -40; break;
                    case 'Home':                delta = -chatContainer.scrollTop; break;
                    case 'End':                 delta = chatContainer.scrollHeight; break;
                    default: return;
                }
                event.preventDefault();
                chatContainer.scrollTop += delta;
            };
            window.addEventListener('keydown', keyHandler, false);

            const cleanup = () => {
                window.removeEventListener('wheel',       wheelHandler, wheelOptions);
                window.removeEventListener('touchstart',  touchStartHandler, false);
                window.removeEventListener('touchmove',   touchMoveHandler, { passive: false });
                window.removeEventListener('touchend',    touchEndHandler, false);
                window.removeEventListener('touchcancel', touchEndHandler, false);
                window.removeEventListener('keydown',     keyHandler, false);
                htmlEl.classList.remove('voice-scroll-locked');
                bodyEl.classList.remove('voice-scroll-locked');
                window.VoiceMode._scrollLockActive = false;
                if (window.VoiceMode._scrollLockCleanup === cleanup) window.VoiceMode._scrollLockCleanup = null;
            };

            window.VoiceMode._scrollLockActive  = true;
            window.VoiceMode._scrollLockCleanup = cleanup;
        } catch {}
    }

    function disableVoiceScrollLock() {
        try { if (typeof window.VoiceMode._scrollLockCleanup === 'function') window.VoiceMode._scrollLockCleanup(); } catch {}
    }

    // ═══════════════════════════════════════════════════════════════════
    //  RECORDING
    // ═══════════════════════════════════════════════════════════════════

    async function startVoiceRecording() {
        if (isRecording) return;
        if (!window.VoiceMode.attemptId) return;

        disableInputs(false);
        try { const sendEl = document.getElementById('sendButton'); if (sendEl) sendEl.disabled = false; } catch {}

        recordedBlob = null;
        recChunks    = [];
        showRecordingUI();

        const maxRecordTime = (window.VoiceMode.recordtime || 30) * 1000;

        try {
            stopAudioPlayback();
            stopAllVoiceRecordings();

            const ok = await initAudioRecording();
            if (!ok || !mediaRecorder) return;

            recordingSessionId = 'audio_' + Date.now() + '_' + Math.random().toString(36).slice(2, 9);

            const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            if (!csrf) throw new Error('CSRF token missing');

            const res = await fetch('/audio/start-recording', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                body: JSON.stringify({ journey_attempt_id: window.VoiceMode.attemptId, session_id: recordingSessionId }),
            });
            if (!res.ok) {
                const err = await res.json().catch(() => ({}));
                throw new Error(err.error || `HTTP ${res.status} ${res.statusText}`);
            }

            mediaRecorder.start(250);
            isRecording       = true;
            recordingStartTime = Date.now();

            updateMicButtonRecording();
            startVisualizer(recordingStream);

            recordingTimeout = setTimeout(() => { stopVoiceRecording(); }, maxRecordTime);
        } catch (e) {
            resetRecordingState();
        }
    }

    function stopVoiceRecording() {
        if (!isRecording || !mediaRecorder) return;
        try {
            isRecording = false;
            if (recordingTimeout) { clearTimeout(recordingTimeout); recordingTimeout = null; }
            if (mediaRecorder.state === 'recording') mediaRecorder.stop();
            if (recordingStream) { recordingStream.getTracks().forEach(t => t.stop()); recordingStream = null; }

            updateMicButtonNormal();
            stopVisualizer();

            window.VoiceMode.textBuffer        = '';
            window.VoiceMode.audioBuffer       = [];
            window.VoiceMode.startedAt         = null;
            window.VoiceMode.throttlingState   = null;
            window.VoiceMode.streamingComplete = false;
        } catch {}
    }

    async function initAudioRecording() {
        try {
            if (mediaRecorder && mediaRecorder.stream) {
                mediaRecorder.stream.getTracks().forEach(t => t.stop());
            }

            recordingStream = await navigator.mediaDevices.getUserMedia({
                audio: { sampleRate: 16000, channelCount: 1, echoCancellation: true, noiseSuppression: true, autoGainControl: true },
            });

            mediaRecorder = new MediaRecorder(recordingStream, { mimeType: 'audio/webm;codecs=opus' });
            recChunks = [];

            mediaRecorder.ondataavailable = (event) => {
                if (event.data.size > 0) recChunks.push(event.data);
            };

            mediaRecorder.onstop = async () => {
                try {
                    recordedBlob = new Blob(recChunks, { type: mediaRecorder.mimeType });
                    if (sendWhenStopped) {
                        showRecordingPreview(recordedBlob);
                        await sendRecordedAudio();
                    } else {
                        showRecordingPreview(recordedBlob);
                    }
                } catch {} finally { sendWhenStopped = false; }
            };

            return true;
        } catch { return false; }
    }

    async function sendAudioChunk(audioBlob, chunkNumber, isFinal = false) {
        if (!recordingSessionId) return;
        try {
            const buf   = await audioBlob.arrayBuffer();
            const bytes = new Uint8Array(buf);
            let binary  = '';
            const chunkSize = 0x8000;
            for (let i = 0; i < bytes.length; i += chunkSize) {
                binary += String.fromCharCode.apply(null, bytes.subarray(i, i + chunkSize));
            }
            const base64 = btoa(binary);

            const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            await fetch('/audio/process-chunk', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                body: JSON.stringify({ session_id: recordingSessionId, audio_data: base64, chunk_number: chunkNumber, is_final: isFinal }),
            });
        } catch {}
    }

    async function completeAudioRecording() {
        if (!recordingSessionId) return;
        try {
            const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            const response = await fetch('/audio/complete', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                body: JSON.stringify({ session_id: recordingSessionId }),
            });
            if (!response.ok) throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            await pollForTranscription(recordingSessionId);
        } catch {} finally {
            recordingSessionId = null;
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
                    headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                });
                if (!response.ok) {
                    const err = await response.json().catch(() => ({}));
                    throw new Error(err.error || `HTTP ${response.status}: ${response.statusText}`);
                }
                const data = await response.json();

                if (data.status === 'completed' && data.transcription) {
                    const inputEl = document.getElementById('messageInput');
                    if (inputEl) {
                        const cur = (inputEl.value || '').trim();
                        inputEl.value = cur ? cur + ' ' + data.transcription : data.transcription;
                    }
                    enableInputs();
                    if (inputEl && inputEl.value.trim()) {
                        setTimeout(() => {
                            const sendBtn = document.getElementById('sendButton');
                            if (sendBtn && !sendBtn.disabled) sendBtn.click();
                        }, 700);
                    }
                    return;
                }

                if (data.status === 'failed') { enableInputs(); return; }

                attempts++;
                if (attempts < maxAttempts) setTimeout(poll, 1000);
                else enableInputs();
            } catch { enableInputs(); }
        };

        poll();
    }

    function updateMicButtonRecording() {
        const mic  = document.getElementById('micButton');
        const icon = document.getElementById('recordingIcon');
        const text = document.getElementById('recordingText');
        if (mic)  { mic.classList.add('btn-recording'); mic.classList.remove('btn-secondary'); }
        if (icon) icon.className = 'bi bi-stop-fill';
        if (text) text.textContent = 'Stop Recording';
    }

    function updateMicButtonNormal() {
        const mic  = document.getElementById('micButton');
        const icon = document.getElementById('recordingIcon');
        const text = document.getElementById('recordingText');
        if (mic)  { mic.classList.remove('btn-recording'); mic.classList.add('btn-secondary'); }
        if (icon) icon.className = 'bi bi-mic-fill';
        if (text) text.textContent = 'Record Audio';
    }

    function resetRecordingState() {
        isRecording        = false;
        recordingSessionId = null;
        recordingStartTime = null;
        if (recordingTimeout) { clearTimeout(recordingTimeout); recordingTimeout = null; }
        updateMicButtonNormal();
        stopVisualizer();
        clearCountdown();
        recordedBlob = null;
        removeRecordingPreview();
        resetRecordingUI();
    }

    // ── Recording UI helpers ────────────────────────────────────────

    function showRecordingUI() {
        const inputEl    = document.getElementById('messageInput');
        const inputGroup = document.getElementById('inputGroup');
        if (!inputGroup) return;
        if (inputEl) inputEl.style.display = 'none';

        recordingUIEl = document.getElementById('recordingUI');
        const html = `
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
            recordingUIEl.innerHTML = html;
            const micBtn = document.getElementById('micButton');
            inputGroup.insertBefore(recordingUIEl, micBtn);
        } else {
            recordingUIEl.className = 'flex-grow-1 d-flex flex-column align-items-stretch p-2 border rounded';
            recordingUIEl.innerHTML = html;
        }

        visualizerCanvas = recordingUIEl.querySelector('#recordingCanvas');
        visualizerCtx    = visualizerCanvas.getContext('2d');
        startCountdown();
    }

    function resetRecordingUI() {
        const inputEl = document.getElementById('messageInput');
        if (inputEl) inputEl.style.display = '';
        if (recordingUIEl) { recordingUIEl.remove(); recordingUIEl = null; visualizerCanvas = null; visualizerCtx = null; }
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
            const dataArray    = new Uint8Array(bufferLength);

            const draw = () => {
                animationFrameId = requestAnimationFrame(draw);
                if (!visualizerCtx || !visualizerCanvas) return;
                analyser.getByteTimeDomainData(dataArray);
                const w = visualizerCanvas.width = visualizerCanvas.clientWidth;
                const h = visualizerCanvas.height;
                visualizerCtx.clearRect(0, 0, w, h);
                visualizerCtx.lineWidth   = 2;
                visualizerCtx.strokeStyle = '#10a37f';
                visualizerCtx.beginPath();
                const slice = w / bufferLength;
                let x = 0;
                for (let i = 0; i < bufferLength; i++) {
                    const v = dataArray[i] / 128.0;
                    const y = v * h / 2;
                    i === 0 ? visualizerCtx.moveTo(x, y) : visualizerCtx.lineTo(x, y);
                    x += slice;
                }
                visualizerCtx.lineTo(w, h / 2);
                visualizerCtx.stroke();
            };
            draw();
        } catch {}
    }

    function stopVisualizer() {
        try {
            if (animationFrameId) cancelAnimationFrame(animationFrameId);
            animationFrameId = null;
            if (recordingAudioContext) { recordingAudioContext.close().catch(() => {}); recordingAudioContext = null; }
            analyser = null;
        } catch {}
    }

    function startCountdown() {
        const el = document.getElementById('recordingCountdown');
        if (!el) return;
        const maxSec = Math.ceil(window.VoiceMode.recordtime || 30);
        const start  = Date.now();
        const fmt = (s) => `${String(Math.floor(s / 60)).padStart(2, '0')}:${String(s % 60).padStart(2, '0')}`;
        countdownInterval = setInterval(() => {
            const elapsed   = Math.floor((Date.now() - start) / 1000);
            const remaining = Math.max(0, maxSec - elapsed);
            el.textContent  = `${fmt(remaining)} / ${fmt(maxSec)}`;
            if (remaining <= 0) { clearInterval(countdownInterval); countdownInterval = null; }
        }, 200);
    }

    function clearCountdown() {
        if (countdownInterval) { clearInterval(countdownInterval); countdownInterval = null; }
    }

    function showRecordingPreview(blob) {
        if (!recordingUIEl) showRecordingUI();
        if (!recordingUIEl) return;
        const url  = URL.createObjectURL(blob);
        const wrap = document.createElement('div');
        wrap.className = 'd-flex flex-column gap-2 w-100';
        wrap.innerHTML = `<audio id="recordingPreviewAudio" controls src="${url}"></audio>`;
        recordingUIEl.innerHTML = '';
        recordingUIEl.appendChild(wrap);
        const sendEl = document.getElementById('sendButton');
        if (sendEl) sendEl.disabled = false;
    }

    function removeRecordingPreview() {
        if (recordingUIEl) recordingUIEl.innerHTML = '';
    }

    async function sendRecordedAudio() {
        try {
            disableInputs();
            if (!recordingSessionId) {
                const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                const res = await fetch('/audio/start-recording', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                    body: JSON.stringify({
                        journey_attempt_id: window.VoiceMode.attemptId,
                        session_id: 'audio_' + Date.now() + '_' + Math.random().toString(36).slice(2, 9),
                    }),
                });
                if (!res.ok) throw new Error('Failed to create recording session');
                recordingSessionId = (await res.json().catch(() => ({}))).session_id || recordingSessionId;
            }

            let uploadBlob = recordedBlob;
            if (!uploadBlob && recChunks && recChunks.length) {
                const mime = (mediaRecorder && mediaRecorder.mimeType) ? mediaRecorder.mimeType : 'audio/webm';
                uploadBlob = new Blob(recChunks, { type: mime });
            }
            if (!uploadBlob) { enableInputs(); return; }

            await sendAudioChunk(uploadBlob, 0, true);
            await completeAudioRecording();
            recordedBlob = null;
            recChunks    = [];
        } catch { enableInputs(); }
    }

    // ═══════════════════════════════════════════════════════════════════
    //  NETWORK ACTIONS
    // ═══════════════════════════════════════════════════════════════════

    function handleMicClick(e) {
        e.preventDefault();
        isRecording ? stopVoiceRecording() : startVoiceRecording();
    }

    function handleSubmitClick(e) {
        e.preventDefault();

        // If recording: stop and send immediately
        if (isRecording) {
            sendWhenStopped = true;
            try {
                const micBtn = document.getElementById('micButton');
                if (micBtn) micBtn.disabled = true;
                const textEl    = document.getElementById('sendButtonText');
                const spinnerEl = document.getElementById('sendSpinner');
                if (textEl)    textEl.textContent = 'Sending recording…';
                if (spinnerEl) spinnerEl.classList.remove('d-none');
                if (recordingUIEl) {
                    recordingUIEl.innerHTML = `
                        <div class="d-flex align-items-center gap-2 small text-muted">
                            <span class="processing-spinner"></span>
                            <span>Finalizing recording…</span>
                        </div>`;
                }
            } catch {}
            stopVoiceRecording();
            return;
        }

        // If preview blob exists, send it
        if (recordedBlob) { sendRecordedAudio(); return; }

        disableInputs();

        const inputEl   = document.getElementById('voiceMessageInput') || document.getElementById('messageInput');
        const textEl    = document.getElementById('sendButtonText');
        const spinnerEl = document.getElementById('sendSpinner');
        const message   = inputEl ? inputEl.value.trim() : '';

        if (!message) { enableInputs(); return; }

        if (textEl)    textEl.textContent = 'Sending...';
        if (spinnerEl) spinnerEl.classList.remove('d-none');

        let csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        if (!csrfToken) {
            const csrfInput = document.querySelector('input[name="_token"]');
            if (csrfInput) csrfToken = csrfInput.value;
        }
        if (!csrfToken) { enableInputs(); return; }

        // Add user message + empty AI div to chat
        const chatContainer = document.getElementById('chatContainer');
        if (chatContainer) {
            const userDiv = document.createElement('div');
            userDiv.className = 'message user-message';
            userDiv.textContent = message;
            chatContainer.appendChild(userDiv);

            const aiDiv = document.createElement('div');
            aiDiv.className = 'message ai-message';
            chatContainer.appendChild(aiDiv);

            startStreamScrollSession();
            requestAnimationFrame(() => { scrollChatToBottom('smooth'); });
        }

        if (inputEl) { inputEl.value = ''; resetTextareaToSingleLine(); }

        // Reset streaming state & jsrid queue & response queue
        resetStreamingState();
        jsridQueue = [];
        pendingResponseQueue = [];
        queueingForNextResponse = false;

        fetch('/journeys/voice/submit', {
            method: 'POST',
            headers: {
                'Accept':           'application/json',
                'Content-Type':     'application/json',
                'X-CSRF-TOKEN':     csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            body: JSON.stringify({ attemptid: parseInt(window.VoiceMode.attemptId, 10), input: message }),
        })
        .then(response => {
            if (!response.ok) throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            const ct = response.headers.get('content-type');
            if (ct && ct.includes('application/json')) return response.json();
            return response.text().then(t => { throw new Error('Expected JSON but got: ' + ct); });
        })
        .then(data => {
            const journeyStatus = ((data?.journey_status ?? data?.joruney_status ?? data?.action) || '').toString().trim();

            if (journeyStatus === 'finish_journey') {
                const awaiting   = !!data?.awaiting_feedback;
                const nextStatus = awaiting ? 'awaiting_feedback' : 'completed';
                const voiceEl    = document.getElementById('journey-data-voice');
                if (voiceEl) {
                    voiceEl.setAttribute('data-status', nextStatus);
                    if (awaiting) voiceEl.setAttribute('data-needs-feedback', '1');
                }
                window.VoiceMode.status = nextStatus;
                window.VoiceMode.journeyCompleted = true;
                if (typeof data?.report === 'string' && data.report.trim()) {
                    window.VoiceMode.finalReport = data.report;
                }
                if (awaiting) setAwaitingFeedback(true);
                else scheduleFinalReportRender();
            } else {
                if (textEl)    textEl.textContent = 'Send';
                if (spinnerEl) spinnerEl.classList.add('d-none');
            }
        })
        .catch(error => {
            if (textEl)    textEl.textContent = 'Send';
            if (spinnerEl) spinnerEl.classList.add('d-none');
            const voiceEl  = document.getElementById('journey-data-voice');
            const statusAt = voiceEl?.getAttribute('data-status');
            if (statusAt !== 'completed' && statusAt !== 'awaiting_feedback') enableInputs();
        });
    }

    function handleStartContinueClick(e) {
        e.preventDefault();
        disableInputs();

        const voiceOverlay = document.getElementById('voiceOverlay');
        if (voiceOverlay) { voiceOverlay.classList.add('hidden'); voiceOverlay.style.display = 'none'; }
        const mobileBottomNav = document.querySelector('.mobile-bottom-nav');
        if (mobileBottomNav) { mobileBottomNav.classList.add('d-none'); mobileBottomNav.style.setProperty('display', 'none', 'important'); }
        const mobileTopBar = document.querySelector('.mobile-topbar');
        if (mobileTopBar) { mobileTopBar.classList.add('d-none'); mobileTopBar.style.setProperty('display', 'none', 'important'); }

        const btn = e.currentTarget || e.target;
        const isStart    = btn && btn.classList.contains('voice-start');
        const isContinue = btn && btn.classList.contains('voice-continue');
        if (!isStart && !isContinue) return;

        if (isStart)    startNewJourney();
        if (isContinue) enableInputs();
    }

    function startNewJourney() {
        const voiceElement = document.getElementById('journey-data-voice');
        if (voiceElement) voiceElement.setAttribute('data-has-started', '1');
        window.VoiceMode.hasStarted = true;

        disableInputs();
        jsridQueue = [];
        pendingResponseQueue = [];
        queueingForNextResponse = false;

        const chatContainer = document.getElementById('chatContainer');
        if (chatContainer) {
            const aiDiv = document.createElement('div');
            aiDiv.className = 'message ai-message';
            chatContainer.appendChild(aiDiv);
            startStreamScrollSession();
            requestAnimationFrame(() => { scrollChatToBottom('smooth'); });
        }

        fetch('/journeys/voice/start', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
            },
            body: JSON.stringify({ attemptid: window.VoiceMode.attemptId, input: 'Start' }),
        })
        .then(response => {
            if (!response.ok) throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            const ct = response.headers.get('content-type');
            if (ct && ct.includes('application/json')) return response.json();
            return response.text().then(t => { throw new Error('Expected JSON but got: ' + ct); });
        })
        .then(data => {})
        .catch(error => {});
    }

    // ═══════════════════════════════════════════════════════════════════
    //  INIT
    // ═══════════════════════════════════════════════════════════════════

    function init() {
        const voiceElement = document.getElementById('journey-data-voice');

        const attemptId   = voiceElement.getAttribute('data-attempt-id');
        const journeyId   = voiceElement.getAttribute('data-journey-id');
        const currentStep = voiceElement.getAttribute('data-current-step');
        const totalSteps  = voiceElement.getAttribute('data-total-steps');
        const mode        = voiceElement.getAttribute('data-mode');
        const status      = voiceElement.getAttribute('data-status');
        const recordtime  = voiceElement.getAttribute('data-recordtime');
        const hasStarted  = voiceElement.getAttribute('data-has-started') === '1';

        // Expose on module
        window.VoiceMode.voiceStream           = voiceStream;
        window.VoiceMode.reproductioninprogress = reproductioninprogress;
        window.VoiceMode.attemptId              = attemptId;
        window.VoiceMode.journeyId              = journeyId;
        window.VoiceMode.currentStep            = currentStep;
        window.VoiceMode.totalSteps             = totalSteps;
        window.VoiceMode.mode                   = mode;
        window.VoiceMode.status                 = status;
        window.VoiceMode.hasStarted             = hasStarted;
        window.VoiceMode.textBuffer             = textBuffer;
        window.VoiceMode.audioChunks            = audioChunks;
        window.VoiceMode.audioBuffer            = audioBuffer;
        window.VoiceMode.paragraphStyles        = paragraphStyles;
        window.VoiceMode.wps                    = wps;
        window.VoiceMode.startedAt              = startedAt;
        window.VoiceMode.recordtime             = recordtime ? parseFloat(recordtime) : 0;
        window.VoiceMode.feedbackEndpoint       = voiceElement.getAttribute('data-feedback-url') || '/journeys/voice/feedback';
        outputComplete = status === 'completed' || status === 'awaiting_feedback';
        window.VoiceMode.outputComplete = outputComplete;

        const needsFeedback = voiceElement.getAttribute('data-needs-feedback') === '1';
        const hasFeedback   = voiceElement.getAttribute('data-has-feedback') === '1';
        setAwaitingFeedback(needsFeedback);
        window.VoiceMode.feedbackSubmitted = hasFeedback;
        if (needsFeedback) scrollBodyToBottom('smooth');

        // WebSocket channel
        const channelName  = `voice.mode.${attemptId}`;
        const voiceChannel = window.VoiceEcho.private(channelName);
        window.VoiceMode.channelName  = channelName;
        window.VoiceMode.voiceChannel = voiceChannel;

        voiceChannel.subscribed(() => {});
        voiceChannel.error((error) => {});
        voiceChannel.listen('.voice.chunk.sent', handleSentPacket);

        // Cleanup on page leave
        if (!window.VoiceMode._cleanupBound) {
            const leave = () => {
                try {
                    const ch = window.VoiceMode?.voiceChannel;
                    const cn = window.VoiceMode?.channelName;
                    if (ch) { try { ch.stopListening?.('.voice.chunk.sent'); } catch {} try { ch.unsubscribe?.(); } catch {} }
                    if (cn && window.VoiceEcho && typeof window.VoiceEcho.leave === 'function') window.VoiceEcho.leave(cn);
                    else if (ch && typeof ch.cancel === 'function') try { ch.cancel(); } catch {}
                } catch {} finally { disableVoiceScrollLock(); }
            };
            window.addEventListener('beforeunload', leave, { once: true });
            window.addEventListener('unload', leave, { once: true });
            window.VoiceMode._cleanupBound = true;
        }

        // UI bindings
        const startBtn = document.getElementById('startContinueButton');
        if (startBtn) startBtn.addEventListener('click', handleStartContinueClick, { once: false });

        const submitBtn = document.getElementById('sendButton');
        if (submitBtn) submitBtn.addEventListener('click', handleSubmitClick, { once: false });

        const chatContainer = document.getElementById('chatContainer');
        bindChatScrollWatcher(chatContainer);
        requestAnimationFrame(() => { scrollChatToBottom('smooth'); });
        enableVoiceScrollLock();

        const micBtn = document.getElementById('micButton');
        if (micBtn) micBtn.addEventListener('click', handleMicClick, { once: false });

        // Volume toggle
        const voiceSoundToggle = document.getElementById('voiceSoundToggle');
        if (voiceSoundToggle) {
            voiceSoundToggle.addEventListener('click', () => toggleMute(!volumeMuted));
        } else {
            const volUp  = document.getElementById('volumeUpIcon');
            const volOff = document.getElementById('volumeOffIcon');
            if (volUp)  volUp.addEventListener('click',  () => toggleMute(true));
            if (volOff) volOff.addEventListener('click', () => toggleMute(false));
        }
        toggleMute(volumeMuted);

        // Attach handlers to pre-rendered recordings
        try { document.querySelectorAll('audio.voice-recording').forEach(attachHandlersToVoiceRecording); } catch {}

        setupFeedbackForm();
    }

    // ═══════════════════════════════════════════════════════════════════
    //  PUBLIC API
    // ═══════════════════════════════════════════════════════════════════

    return {
        init: init,
    };

}());
