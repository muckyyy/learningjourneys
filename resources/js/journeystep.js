// Journey Step Module (extracted from utili.js)
// Depends on window.StreamingUtils defined in utili.js
window.ChatMode = (function() {
	// Placeholder for ChatMode specific functions if needed in the future
	let isProcessing = false;
	let mediaRecorder = null;
	let audioChunks = [];
	let isRecording = false;
	let recordingStartTime = null;
	let stream = null;
	let recordingTimeout = null;
	
	function init(){
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

	return {
		// Define any ChatMode specific functions here
		init: init
	};
})();
/*
window.JourneyStep = (function() {
	let isProcessing = false;
	let mediaRecorder = null;
	let audioChunks = [];
	let isRecording = false;
	let recordingStartTime = null;
	let stream = null;
	let recordingTimeout = null;

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

	function setSendAndMicButtonStates(disabled) {
		const sendButton = document.getElementById('sendButton');
		const micButton = document.getElementById('micButton');
		if (sendButton) sendButton.disabled = disabled;
		if (micButton) micButton.disabled = disabled;
	}

	function initializeButtonStates() {
		const data = initializeFromDataAttributes();
		if (!data) return;
		if (data.status === 'completed') {
			setSendAndMicButtonStates(true);
		} else {
			setSendAndMicButtonStates(false);
		}
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
		if (!newLine && container.children.length > 0) {
			const lastMessage = container.children[container.children.length - 1];
			if (lastMessage && lastMessage.classList.contains(`${type}-message`)) {
				lastMessage.innerHTML = content;
				container.scrollTop = container.scrollHeight;
				return lastMessage;
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
			const response = await fetch('/api/user', {
				method: 'GET',
				headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'X-Requested-With': 'XMLHttpRequest' },
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

	async function handleStreamResponse(response) {
		const reader = response.body.getReader();
		const decoder = new TextDecoder();
		let accumulatedText = '';
		let sseBuffer = '';
		let streamTimeout;
		let readerReleased = false;
		console.log('📡 Starting to handle stream response...');
		streamTimeout = setTimeout(() => {
			console.warn('⚠️ Stream timeout reached');
			if (!readerReleased) {
				try { reader.releaseLock(); readerReleased = true; } catch (e) { console.log('Reader already released'); }
			}
		}, 600000);
		const messageInput = document.getElementById('messageInput');
		if (messageInput) messageInput.disabled = true;
		setSendAndMicButtonStates(true);
		try {
			while (true) {
				const { done, value } = await reader.read();
				if (done) {
					if (sseBuffer.trim()) { processSseMessage(sseBuffer.trim()); }
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
					if (trimmed.startsWith('data:')) { dataLines.push(trimmed.substring(5).trimStart()); }
				}
				if (dataLines.length === 0) return;
				const data = dataLines.join('\n').trim();
				if (!data || data === '[DONE]') return;
				try {
					const parsed = JSON.parse(data);
					if (parsed.type === 'chunk' && parsed.text) {
						accumulatedText += parsed.text;
						updateStreamingMessage(accumulatedText, 'ai');
					}
					if (parsed.type === 'content' && parsed.delta) {
						accumulatedText += parsed.delta;
						updateStreamingMessage(accumulatedText, 'ai');
					}
					if (parsed.type === 'done') {
						if (streamTimeout) clearTimeout(streamTimeout);
						if (accumulatedText) { window.StreamingUtils.finalizeStreamingMessage(accumulatedText, 'chatContainer'); }
						if (parsed.progressed_to_order && parsed.total_steps) {
							updateProgressBar(parsed.progressed_to_order, parsed.total_steps);
							const journeyData = document.getElementById('journey-data');
							if (journeyData) journeyData.setAttribute('data-current-step', parsed.progressed_to_order);
						}
						if (parsed.is_complete) {
							addMessage('🎉 Congratulations! You have completed this journey!', 'system');
							if (messageInput) messageInput.disabled = true;
							setSendAndMicButtonStates(true);
							const journeyData = document.getElementById('journey-data');
							if (journeyData) journeyData.setAttribute('data-status', 'completed');
							setTimeout(() => window.location.reload(), 2000);
						} else {
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
			if (streamTimeout) clearTimeout(streamTimeout);
			if (!readerReleased) { try { reader.releaseLock(); } catch (e) { } }
			const data = initializeFromDataAttributes();
			if (data && data.status !== 'completed') {
				const messageInput = document.getElementById('messageInput');
				if (messageInput) messageInput.disabled = false;
				setSendAndMicButtonStates(false);
			}
		}
	}

	return {
		init: function() {
			const data = initializeFromDataAttributes();
			if (!data) return;
			checkAuthentication();
			if (data.currentStep && data.totalSteps) { updateProgressBar(parseInt(data.currentStep), parseInt(data.totalSteps)); }
			const container = document.getElementById('chatContainer');
			const hasPreloadedMessages = container && container.children.length > 0;
			if (container) { setTimeout(() => { container.scrollTop = container.scrollHeight; }, 100); }
			if (!hasPreloadedMessages) { this.loadExistingMessages(); }
			setTimeout(() => { if (container && container.children.length === 0) { this.startJourneyChat(); } }, 100);
			initializeButtonStates();
			const micButton = document.getElementById('micButton');
			if (micButton) {
				micButton.addEventListener('click', () => {
					const d = initializeFromDataAttributes();
					if (d?.status === 'completed') return;
					if (isRecording) { this.stopAudioRecording(); } else { this.startAudioRecording(); }
				});
			}
			const messageInput = document.getElementById('messageInput');
			if (messageInput) { messageInput.addEventListener('keypress', this.handleKeyPress); }
			const sendButton = document.getElementById('sendButton');
			if (sendButton) {
				sendButton.addEventListener('click', () => {
					const d = initializeFromDataAttributes();
					if (d?.status === 'completed') return;
					this.sendMessage();
				});
			}
		},
		loadExistingMessages: async function() {
			const data = initializeFromDataAttributes();
			if (!data) return;
			try {
				const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
				if (!csrfToken) return;
				const response = await fetch(`/api/journey-attempts/${data.attemptId}/messages`, { headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' });
				if (response.ok) {
					const responseData = await response.json();
					if (responseData.success && responseData.messages?.length > 0) {
						responseData.messages.forEach(message => { addMessage(message.content, message.type); });
					}
				}
			} catch (error) { console.error('Error loading existing messages:', error); }
		},
		startJourneyChat: async function() {
			if (isProcessing) return;
			const data = initializeFromDataAttributes();
			if (!data) return;
			if (data.status === 'completed') { addMessage('✅ This journey has been completed!', 'system'); return; }
			isProcessing = true; updateSendButton(true);
			try {
				const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
				if (!csrfToken) throw new Error('CSRF token not found. Please refresh the page.');
				const response = await fetch('/api/chat/start-web', { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'text/event-stream', 'X-CSRF-TOKEN': csrfToken, 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin', body: JSON.stringify({ journey_id: parseInt(data.journeyId), attempt_id: parseInt(data.attemptId) }) });
				if (!response.ok) { const errorText = await response.text(); throw new Error(`Failed to start journey chat: ${response.status} - ${errorText}`); }
				await handleStreamResponse(response);
			} catch (error) {
				console.error('Error starting journey chat:', error); addMessage(`❌ Error starting journey chat: ${error.message}`, 'error');
			} finally { isProcessing = false; updateSendButton(false); }
		},
		sendMessage: async function() {
			const messageInput = document.getElementById('messageInput');
			const data = initializeFromDataAttributes();
			if (!messageInput || !data || isProcessing) return;
			const message = messageInput.value.trim();
			if (!message) return;
			if (data.status === 'completed') return;
			isProcessing = true; updateSendButton(true); messageInput.disabled = true;
			try {
				addMessage(message, 'user');
				messageInput.value = '';
				const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
				if (!csrfToken) return;
				const response = await fetch('/api/chat/submit-web', { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'text/event-stream', 'X-CSRF-TOKEN': csrfToken, 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin', body: JSON.stringify({ attempt_id: parseInt(data.attemptId), user_input: message }) });
				if (!response.ok) return;
				await handleStreamResponse(response);
			} catch (error) { console.error('Send message error:', error); }
			finally { isProcessing = false; updateSendButton(false); messageInput.disabled = false; }
		},
		handleStreamResponse,
		handleKeyPress: function(event) { if (event.key === 'Enter' && !isProcessing) { window.JourneyStep.sendMessage(); } },
		startAudioRecording: async function() {
			const micButton = document.getElementById('micButton');
			const recordingIcon = document.getElementById('recordingIcon');
			const recordingText = document.getElementById('recordingText');
			const data = initializeFromDataAttributes();
			if (!data || data.status === 'completed' || isRecording || isProcessing) return;
			try {
				stream = await navigator.mediaDevices.getUserMedia({ audio: { echoCancellation: true, noiseSuppression: true, autoGainControl: true, sampleRate: 44100 } });
				const mimeTypes = ['audio/webm;codecs=opus', 'audio/webm', 'audio/mp4', 'audio/ogg', 'audio/wav'];
				let selectedMimeType = null;
				for (const mimeType of mimeTypes) { if (MediaRecorder.isTypeSupported(mimeType)) { selectedMimeType = mimeType; break; } }
				if (!selectedMimeType) throw new Error('No supported audio MIME type found');
				mediaRecorder = new MediaRecorder(stream, { mimeType: selectedMimeType, audioBitsPerSecond: 128000 });
				audioChunks = []; isRecording = true;
				if (micButton) { micButton.classList.add('btn-danger'); micButton.classList.remove('btn-secondary'); }
				if (recordingIcon) { recordingIcon.className = 'fas fa-stop'; }
				if (recordingText) { recordingText.textContent = 'Stop Recording'; }
				recordingStartTime = Date.now();
				mediaRecorder.start();
				recordingTimeout = setTimeout(() => { this.stopAudioRecording(); }, 60000);
				mediaRecorder.addEventListener('dataavailable', (event) => { if (event.data.size > 0) { audioChunks.push(event.data); } });
				mediaRecorder.addEventListener('stop', () => { this.processAudioRecording(); });
			} catch (error) { console.error('Failed to start recording:', error); addMessage(`❌ Recording failed: ${error.message}`, 'error'); this.resetRecordingState(); }
		},
		stopAudioRecording: function() {
			if (!isRecording || !mediaRecorder) return;
			if (recordingTimeout) { clearTimeout(recordingTimeout); recordingTimeout = null; }
			if (mediaRecorder.state === 'recording' || mediaRecorder.state === 'paused') mediaRecorder.stop();
			if (stream) { stream.getTracks().forEach(track => track.stop()); stream = null; }
			this.resetRecordingState();
		},
		resetRecordingState: function() {
			const micButton = document.getElementById('micButton');
			const recordingIcon = document.getElementById('recordingIcon');
			const recordingText = document.getElementById('recordingText');
			isRecording = false; recordingStartTime = null;
			if (micButton) { micButton.classList.remove('btn-danger'); micButton.classList.add('btn-secondary'); }
			if (recordingIcon) { recordingIcon.className = 'fas fa-microphone'; }
			if (recordingText) { recordingText.textContent = 'Record Audio'; }
		},
		processAudioRecording: async function() {
			if (audioChunks.length === 0) { addMessage('❌ No audio data recorded', 'error'); return; }
			try {
				const audioBlob = new Blob(audioChunks, { type: audioChunks[0].type });
				if (audioBlob.size === 0) throw new Error('Audio blob is empty');
				await this.sendAudioMessage(audioBlob);
			} catch (error) { console.error('Error processing audio:', error); addMessage(`❌ Audio processing failed: ${error.message}`, 'error'); }
			finally { audioChunks = []; mediaRecorder = null; }
		},
		sendAudioMessage: async function(audioBlob) {
			const data = initializeFromDataAttributes();
			if (!data || data.status === 'completed') return;
			isProcessing = true; updateSendButton(true);
			try {
				let csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
				if (!csrfToken) { const csrfInput = document.querySelector('input[name="_token"]'); if (csrfInput) csrfToken = csrfInput.value; }
				if (!csrfToken) return;
				const formData = new FormData();
				formData.append('audio', audioBlob, 'recording.webm');
				formData.append('_token', csrfToken);
				formData.append('session_id', data.attemptId);
				formData.append('journey_attempt_id', data.attemptId);
				const response = await fetch('/audio/transcribe', { method: 'POST', headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin', body: formData });
				if (!response.ok) throw new Error(`Transcription failed: ${response.status}`);
				const transcriptionResult = await response.json();
				if (transcriptionResult.success && transcriptionResult.transcription) {
					const messageInput = document.getElementById('messageInput');
					if (messageInput) {
						messageInput.value = transcriptionResult.transcription;
						addMessage(transcriptionResult.transcription, 'user');
						messageInput.value = '';
						const submitResponse = await fetch('/api/chat/submit-web', { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'text/event-stream', 'X-CSRF-TOKEN': csrfToken, 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin', body: JSON.stringify({ attempt_id: parseInt(data.attemptId), user_input: transcriptionResult.transcription }) });
						if (submitResponse.ok) { await window.JourneyStep.handleStreamResponse(submitResponse); } else { addMessage('❌ Failed to send transcribed message', 'error'); }
					}
				}
			} catch (error) { console.error('Audio transcription error:', error); }
			finally { isProcessing = false; updateSendButton(false); }
		}
	};
})();
*/
