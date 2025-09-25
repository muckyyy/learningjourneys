// Utility functions and shared modules
// Contains StreamingUtils and JourneyStartModal (JourneyStep moved to journeystep.js)

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

// JourneyStep removed from this file.

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
