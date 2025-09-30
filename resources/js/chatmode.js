// Journey Step Module (extracted from utili.js)
// Depends on window.StreamingUtils defined in utili.js
window.ChatMode = (function() {
	// Placeholder for ChatMode specific functions if needed in the future
	let isProcessing = true;
	let listenChannel = null;
	let mediaRecorder = null;
	let audioChunks = [];
	let isRecording = false;
	let recordingStartTime = null;
	let stream = null;
	let recordingTimeout = null;
	
	function init(){
		
		const journeyData = document.getElementById('journey-data-chat');
		if (!journeyData) {
			console.error('Journey data element not found');
			return null;
		}
		const attemptId = journeyData.dataset.attemptId;
		if (window.ChatEcho && attemptId) {
			const channelName = `chat.mode.${attemptId}`;
            const chatChannel = window.ChatEcho.private(channelName);
            
            // Add subscription debugging
            chatChannel.subscribed(() => {
				fetch('/api/chat/start-web', {
					method: 'POST',
					headers: {
						'Content-Type': 'application/json',
						'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
					},
					body: JSON.stringify({
						journey_id: journeyData.dataset.journeyId,
						attempt_id: journeyData.dataset.attemptId
					})
				}).then(async (response) => {
					try {
						const data = await response.json().catch(() => ({}));
						const status = (data?.status || '').trim();
						const inputEl = document.getElementById('messageInput');
						const micEl = document.getElementById('micButton');
						const sendEl = document.getElementById('sendButton');
						const spinner = document.getElementById('sendSpinner');

						if (status === 'chat_continue') {
							// enable inputs and ensure spinner hidden
							if (inputEl) inputEl.disabled = false;
							if (micEl) micEl.disabled = false;
							updateSendButton(false);
						} else {
							// completed or unknown state -> keep disabled
							if (spinner) spinner.classList.add('d-none');
							if (inputEl) inputEl.disabled = true;
							if (micEl) micEl.disabled = true;
							if (sendEl) sendEl.disabled = true;

							const progress = document.getElementById('progress-bar');
							if (progress) progress.style.width = '100%';
							window.StreamingUtils?.addMessage('This journey is complete. You may close this window or navigate away.', 'system', 'chatContainer');
						}
					} catch (e) {
						console.error('Failed to parse start-chat status:', e);
					}
				}).catch(error => {
					console.error('There was a problem with the fetch operation:', error);
				});
            });

            chatChannel.error((error) => {
                console.error('❌ ChatMode - Channel subscription error:', error);
            });

            chatChannel.listen('.chat.chunk.sent', (e) => {
                
                handleChatModeEvent(e);
                
            });

			// Remove unconditional enabling; keep only scroll behavior
			container = document.getElementById('chatContainer');
			requestAnimationFrame(() => {
				if (typeof container.scrollTo === 'function') {
					container.scrollTo({ top: container.scrollHeight, behavior: 'smooth' });
				} else {
					container.scrollTop = container.scrollHeight;
				}
			});
			
		}

		// Add minimal send button click handler
		const sendBtn = document.getElementById('sendButton');
		if (sendBtn && !sendBtn._bound) {
			sendBtn.addEventListener('click', async () => {
				const inputEl = document.getElementById('messageInput');
				const journeyData = document.getElementById('journey-data-chat');
				const attemptId = journeyData?.dataset.attemptId;
				const text = (inputEl?.value || '').trim();

				// Immediately reflect UI sending state
				updateSendButton(true);
				if (inputEl) inputEl.disabled = true;

				// Prepare UI + send user bubble
				if (inputEl) inputEl.value = '';
				window.StreamingUtils.addMessage(text, 'user', 'chatContainer');
				if (!attemptId || !text) {
					updateSendButton(false);
					if (inputEl) inputEl.disabled = false;
					return;
				}

				try {
					const res = await fetch('/api/chat/submit-web', {
						method: 'POST',
						headers: {
							'Content-Type': 'application/json',
							'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
						},
						body: JSON.stringify({
							attempt_id: parseInt(attemptId, 10),
							user_input: text
						})
					});
					if (!res.ok) throw new Error(`HTTP ${res.status}`);
					const data = await res.json();

					// Backward-compat: handle old misspelling if still present
					const journeyStatus = data?.journey_status ?? data?.joruney_status;

					if (journeyStatus !== 'finish_journey') {
						updateSendButton(false);
						if (inputEl) inputEl.disabled = false;
					} else {
						// Journey is finished, disable input and buttons
						updateSendButton(false); // reset spinner/text
						const progress = document.getElementById('progress-bar');
						if (progress) { progress.style.width =  '100%'; }
						if (inputEl) inputEl.disabled = true;
						const mic = document.getElementById('micButton');
						const send = document.getElementById('sendButton');
						if (mic) mic.disabled = true;
						if (send) send.disabled = true;
						window.StreamingUtils.addMessage('This journey is complete. You may close this window or navigate away.', 'system', 'chatContainer');
					}
				} catch (e) {
					console.error('Failed to submit chat:', e);
					updateSendButton(false);
					if (inputEl) inputEl.disabled = false;
				} finally {
					inputEl?.focus();
				}
			});
			sendBtn._bound = true;
		}

		// Bind mic button click handler (start/stop recording)
		const micBtn = document.getElementById('micButton');
		if (micBtn && !micBtn._bound) {
			micBtn.addEventListener('click', () => {
				// prevent recording if controls are disabled
				if (micBtn.disabled) return;
				if (isRecording) { stopAudioRecording(); } else { startAudioRecording(); }
			});
			micBtn._bound = true;
		}
		
		return {
			attemptId: journeyData.dataset.attemptId,
			journeyId: journeyData.dataset.journeyId,
			currentStep: journeyData.dataset.currentStep,
			totalSteps: journeyData.dataset.totalSteps,
			mode: journeyData.dataset.mode,
			status: journeyData.dataset.status,
			interactionCount: journeyData.dataset.interactionsCount
		};

	}
	function handleChatModeEvent(data) {
		// Process the event data as needed
		if (data.type == 'addaibubble'){
			window.StreamingUtils.addMessage('','ai', 'chatContainer', data.jsrid);
		}
		if (data.type == 'aireply'){
			window.StreamingUtils.updateaimessage(data.message, data.jsrid,data.config);
		}
		if (data.type == 'progress'){
			const progressBar = document.getElementById('progress-bar');
			if (progressBar) {
				progressBar.style.width = data.message + '%';
			}
		}
	}

	// Keep ChatMode's send button + spinner in sync with mic state (mirrors JourneyStep)
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

	// --- Audio recording + transcription flow (mirrors JourneyStep behavior) ---

	async function startAudioRecording() {
		if (isRecording) return;
		try {
			// Request mic
			stream = await navigator.mediaDevices.getUserMedia({
				audio: {
					echoCancellation: true,
					noiseSuppression: true,
					autoGainControl: true,
					sampleRate: 44100
				}
			});

			// Pick supported mime type
			const mimeTypes = [
				'audio/webm;codecs=opus',
				'audio/webm',
				'audio/mp4',
				'audio/ogg',
				'audio/wav'
			];
			let selectedMimeType = null;
			for (const mt of mimeTypes) {
				if (window.MediaRecorder && MediaRecorder.isTypeSupported(mt)) {
					selectedMimeType = mt; break;
				}
			}
			if (!selectedMimeType) throw new Error('No supported audio MIME type found');

			audioChunks = [];
			mediaRecorder = new MediaRecorder(stream, { mimeType: selectedMimeType, audioBitsPerSecond: 128000 });
			isRecording = true;
			recordingStartTime = Date.now();

			// Update UI
			const micBtn = document.getElementById('micButton');
			const recordingIcon = document.getElementById('recordingIcon');
			const recordingText = document.getElementById('recordingText');
			if (micBtn) { micBtn.classList.add('btn-danger'); micBtn.classList.remove('btn-secondary'); }
			if (recordingIcon) { recordingIcon.className = 'fas fa-stop'; }
			if (recordingText) { recordingText.textContent = 'Stop Recording'; }

			mediaRecorder.addEventListener('dataavailable', (evt) => {
				if (evt.data && evt.data.size > 0) audioChunks.push(evt.data);
			});
			mediaRecorder.addEventListener('stop', () => { processAudioRecording(); });

			mediaRecorder.start();

			// Auto-stop after 60s
			recordingTimeout = setTimeout(() => { stopAudioRecording(); }, 30000);
		} catch (err) {
			console.error('Failed to start recording:', err);
			window.StreamingUtils?.addMessage(`Recording failed: ${err.message}`, 'error', 'chatContainer');
			resetRecordingState();
		}
	}

	function stopAudioRecording() {
		if (!isRecording || !mediaRecorder) { resetRecordingState(); return; }
		if (recordingTimeout) { clearTimeout(recordingTimeout); recordingTimeout = null; }
		try {
			if (mediaRecorder.state === 'recording' || mediaRecorder.state === 'paused') {
				mediaRecorder.stop();
			}
		} catch (e) {
			console.warn('MediaRecorder stop error:', e);
		}
		if (stream) {
			try { stream.getTracks().forEach(t => t.stop()); } catch (_) {}
			stream = null;
		}
		// UI resets happen immediately; actual processing runs on 'stop' handler
		resetRecordingState();
	}

	function resetRecordingState() {
		isRecording = false;
		recordingStartTime = null;
		const micBtn = document.getElementById('micButton');
		const recordingIcon = document.getElementById('recordingIcon');
		const recordingText = document.getElementById('recordingText');
		if (micBtn) { micBtn.classList.remove('btn-danger'); micBtn.classList.add('btn-secondary'); }
		if (recordingIcon) { recordingIcon.className = 'fas fa-microphone'; }
		if (recordingText) { recordingText.textContent = 'Record Audio'; }
	}

	async function processAudioRecording() {
		try {
			if (!audioChunks.length) {
				window.StreamingUtils?.addMessage('No audio data recorded', 'error', 'chatContainer');
				return;
			}
			const blobType = audioChunks[0]?.type || 'audio/webm';
			const audioBlob = new Blob(audioChunks, { type: blobType });
			if (!audioBlob.size) throw new Error('Audio blob is empty');
			await sendAudioForTranscription(audioBlob);
		} catch (err) {
			console.error('Error processing audio:', err);
			window.StreamingUtils?.addMessage(`Audio processing failed: ${err.message}`, 'error', 'chatContainer');
		} finally {
			audioChunks = [];
			mediaRecorder = null;
		}
	}

	async function sendAudioForTranscription(audioBlob) {
		const journeyData = document.getElementById('journey-data-chat');
		const attemptId = journeyData?.dataset.attemptId;
		if (!attemptId) { console.error('No attempt id for transcription'); return; }

		// Disable mic while uploading/transcribing
		const micBtn = document.getElementById('micButton');
		if (micBtn) micBtn.disabled = true;

		try {
			let csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
			if (!csrfToken) {
				const csrfInput = document.querySelector('input[name="_token"]');
				if (csrfInput) csrfToken = csrfInput.value;
			}
			if (!csrfToken) throw new Error('CSRF token not found');

			const formData = new FormData();
			formData.append('audio', audioBlob, 'recording.webm');
			formData.append('_token', csrfToken);
			// Keep both names for backend compatibility
			formData.append('session_id', attemptId);
			formData.append('journey_attempt_id', attemptId);

			const resp = await fetch('/audio/transcribe', {
				method: 'POST',
				headers: {
					'Accept': 'application/json',
					'X-CSRF-TOKEN': csrfToken,
					'X-Requested-With': 'XMLHttpRequest'
				},
				credentials: 'same-origin',
				body: formData
			});

			if (!resp.ok) throw new Error(`Transcription failed: ${resp.status}`);
			const result = await resp.json();
			if (result?.success && result?.transcription) {
				// Put transcription into input and trigger the existing send flow
				const inputEl = document.getElementById('messageInput');
				const sendBtn = document.getElementById('sendButton');
				if (inputEl) inputEl.value = result.transcription;
				if (sendBtn && !sendBtn.disabled) {
					sendBtn.click(); // reuse existing handler (adds user bubble, disables, posts, etc.)
				} else if (sendBtn) {
					// If disabled, try to enable then click
					sendBtn.disabled = false;
					sendBtn.click();
				}
			} else {
				throw new Error(result?.message || 'Transcription returned no text');
			}
		} catch (err) {
			console.error('Audio transcription error:', err);
			window.StreamingUtils?.addMessage(`Transcription error: ${err.message}`, 'error', 'chatContainer');
		} finally {
			if (micBtn) micBtn.disabled = false;
		}
	}

	return {
		// Define any ChatMode specific functions here
		init: init
	};
})();

//console.log('WS required for chatmode.js',wsRequirements);