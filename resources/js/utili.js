// Utility functions and shared modules
// Contains StreamingUtils and JourneyStartModal (JourneyStep moved to journeystep.js)

// Shared Rendering Utilities for both JourneyStep and PreviewChat modules
window.StreamingUtils = (function() {

    // Safely parse config that may not be valid JSON (e.g., {0 : "cls",1 : "cls2"})
    function safeParseConfig(config) {
        if (!config) return {};
        if (typeof config === 'object') return config;

        if (typeof config === 'string') {
            // First, try JSON.parse directly
            try { return JSON.parse(config); } catch (_) {}

            // Try to normalize to valid JSON:
            // - replace single quotes with double quotes
            // - quote numeric keys
            // - remove trailing commas
            let s = config.trim();
            s = s.replace(/'/g, '"')
                 .replace(/([{,]\s*)(\d+)\s*:/g, '$1"$2":')
                 .replace(/,(\s*[}\]])/g, '$1');
            try { return JSON.parse(s); } catch (_) {}

            // Very lenient fallback: parse "k : v" pairs
            try {
                const obj = {};
                const inner = s.replace(/^{|}$/g, '');
                inner.split(',').forEach(pair => {
                    const [k, v] = pair.split(':');
                    if (k && v) {
                        const key = k.trim().replace(/^"|"$/g, '');
                        const val = v.trim().replace(/^"|"$/g, '');
                        obj[key] = val;
                    }
                });
                return obj;
            } catch (_) {
                return {};
            }
        }
        return {};
    }

    function hasHtmlTags(str) {
        return /<\/?[a-z][\s\S]*>/i.test(str);
    }

    function escapeHTML(str) {
        return String(str).replace(/[&<>"']/g, c => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#39;'
        })[c]);
    }

    // Convert plain text to paragraphs with classes while preserving newlines.
    function formatStreamingContent(content, cfgMap) {
        // If it already looks like HTML (e.g., contains <p>, <video>, <iframe>), do not alter it.
        if (hasHtmlTags(content)) return content;

        const map = safeParseConfig(cfgMap);
        const paragraphs = String(content).split(/\r?\n\r?\n+/); // blank line => new paragraph
        const out = [];

        for (let i = 0; i < paragraphs.length; i++) {
            const p = paragraphs[i];
            // Preserve single newlines within a paragraph
            const html = p.split(/\r?\n/).map(escapeHTML).join('<br>');
            const cls = map[i] ?? map[String(i)] ?? '';
            out.push(`<p${cls ? ` class="${escapeHTML(cls)}"` : ''}>${html}</p>`);
        }

        return out.join('\n');
    }

    /**
     * Enhanced streaming message update with video preservation
     * @param {string} content - The text/HTML content to display
     * @param {string|number} jsrid - Journey step record id to associate the message
     * @param {object|string} config - Paragraph index -> CSS class mapping
     * @returns {HTMLElement|null} - The updated streaming message element
     */
    function updateaimessage(content, jsrid, config) {
        const container = document.getElementById('chatContainer');
        if (!container) {
            return null;
        }

        // Ensure formatting/newlines are preserved; apply per-paragraph classes from config
        const formattedContent = formatStreamingContent(content, config);

        // Select the AI message with this jsrid
        const existingStreamingMessages = container.querySelectorAll(`div.message.ai-message[data-jsrid="${jsrid}"]`);
        if (existingStreamingMessages.length > 1) {
            for (let i = 0; i < existingStreamingMessages.length - 1; i++) {
                existingStreamingMessages[i].remove();
            }
        }
        
        // Check if we have a streaming message in progress
        let streamingMessage = container.querySelector(`div.message.ai-message[data-jsrid="${jsrid}"]`);
        
        if (!streamingMessage) {
            // Create new streaming message
            streamingMessage = document.createElement('div');
            streamingMessage.className = 'message ai-message';
            streamingMessage.setAttribute('data-jsrid', jsrid);
            // Add a subtle animation/indicator for streaming
            streamingMessage.style.borderLeft = '3px solid #007bff';
            streamingMessage.style.backgroundColor = '#f8f9fa';
            streamingMessage.innerHTML = formattedContent;
            container.appendChild(streamingMessage);
        } else {
            // Update existing streaming message
            
            // Check if we already have video content loaded and the new content also has video
            const existingVideo = streamingMessage.querySelector('video, iframe');
            const hasCompleteVideo = formattedContent.includes('</video>') || formattedContent.includes('</iframe>');
            
            if (existingVideo && hasCompleteVideo) {
                // We have loaded video, use surgical updates to avoid flickering
                preserveVideoWhileUpdating(streamingMessage, formattedContent);
            } else if (!existingVideo && hasCompleteVideo) {
                // First time getting complete video, do full update
                streamingMessage.innerHTML = formattedContent;
                streamingMessage.setAttribute('data-has-video', 'true');
            } else {
                // No video yet or still building up content, do normal update
                streamingMessage.innerHTML = formattedContent;
            }
        }
        
        // Auto-scroll to show new content immediately
        requestAnimationFrame(() => {
            if (typeof container.scrollTo === 'function') {
                container.scrollTo({ top: container.scrollHeight, behavior: 'smooth' });
            } else {
                container.scrollTop = container.scrollHeight;
            }
        });
        
        return streamingMessage;
    }
    
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
            return null;
        }
        
        // Safety check: remove any duplicate streaming messages (should never happen, but just in case)
        const existingStreamingMessages = container.querySelectorAll('.streaming-message');
        if (existingStreamingMessages.length > 1) {
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
            
        } else {
            // Update existing streaming message
           
            
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
            // Remove streaming class and styling
            streamingMessage.classList.remove('streaming-message');
            streamingMessage.style.borderLeft = '';
            streamingMessage.style.backgroundColor = '';
            
            // Replace with the complete content for final rendering
            streamingMessage.innerHTML = finalContent;
            
            // Scroll to show the finalized content
            container.scrollTop = container.scrollHeight;
            return streamingMessage;
        } else if (!streamingMessage && finalContent) {
            // No streaming message found - this means streaming failed, so add a regular message
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
    function addMessage(content, type, containerId = 'chatContainer',jsrid = 0) {
        const container = document.getElementById(containerId);
        if (!container) {
            return null;
        }
        
        const messageDiv = document.createElement('div');
        messageDiv.className = `message ${type}-message`;
        messageDiv.innerHTML = content;
        if (jsrid !== undefined) {
            messageDiv.setAttribute('data-jsrid', jsrid);
        }
        
        container.appendChild(messageDiv);
        container.scrollTop = container.scrollHeight;
        
        return messageDiv;
    }

    return {
        updateStreamingMessage,
        finalizeStreamingMessage,
        addMessage,
        preserveVideoWhileUpdating,
        updateaimessage,
        // Expose for VoiceMode to format paragraphs consistently with Chat mode
        formatStreamingContent
    };
})();

// JourneyStep removed from this file.

// Journey Start Modal Module
// Shared functionality for starting journeys from different pages
window.JourneyStartModal = (function() {
    let selectedJourneyId = null;
    let selectedJourneyType = null;
    let selectedJourneyCost = 0;
    
    function showStartJourneyModal(journeyId, journeyTitle, type, tokenCost = 0) {
        selectedJourneyId = journeyId;
        selectedJourneyType = type;
        selectedJourneyCost = Number(tokenCost) || 0;
        
        const titleElement = document.getElementById('journeyTitleText');
        const typeElement = document.getElementById('journeyTypeText');
        const costElement = document.getElementById('journeyCostText');
        
        if (titleElement) titleElement.textContent = journeyTitle;
        if (typeElement) typeElement.textContent = type;
        if (costElement) {
            costElement.textContent = selectedJourneyCost > 0 ? `${selectedJourneyCost} tokens` : 'Free';
        }
        
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
            // Get CSRF token for session authentication
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            if (!csrfToken) {
                throw new Error('CSRF token not found. Please refresh the page.');
            }
            
            // Get current user ID from Laravel
            const currentUserId = window.Laravel?.user?.id || document.querySelector('body[data-user-id]')?.dataset.userId || document.body.dataset.userId;
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
            const rawPayload = await response.text();
            let payload = null;
            try {
                payload = rawPayload ? JSON.parse(rawPayload) : null;
            } catch (jsonError) {
            }

            if (!response.ok || !payload?.success) {
                if (payload?.requires_purchase && payload?.purchase_url) {
                    const message = payload.error || 'You need more tokens to start this journey.';
                    if (window.confirm(`${message}\nGo to the token store now?`)) {
                        window.location.href = payload.purchase_url;
                    }
                    return;
                }

                const fallbackMessage = payload?.error || `Failed to start journey (${response.status})`;
                throw new Error(fallbackMessage);
            }
            const modalElement = document.getElementById('startJourneyModal');
            if (modalElement && window.bootstrap) {
                const modal = bootstrap.Modal.getInstance(modalElement);
                if (modal) modal.hide();
            }

            window.location.href = payload.redirect_url;
        } catch (error) {
            alert('Failed to start journey: ' + error.message);
        } finally {
            // Reset button state
            if (spinner) spinner.classList.add('d-none');
            if (buttonText) buttonText.textContent = 'Yes, Start Journey';
            if (button) button.disabled = false;
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
