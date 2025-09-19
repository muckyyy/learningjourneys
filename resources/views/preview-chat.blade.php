
@extends('layouts.app')

@section('content')
<div class="container">
	<div class="row justify-content-center">
		<div class="col-md-12">
			<div class="card">
				<div class="card-header">
					<h4>Learning Journeys Chat API Test</h4>
					<small class="text-muted" id="pageSubtitle">
						@if($existingAttempt)
							Continuing preview session for "{{ $journey->title }}" ({{ $existingAttempt->created_at->format('M d, Y g:i A') }})
							@if($existingAttempt->status === 'completed')
								<span class="badge bg-success ms-2">Completed</span>
							@endif
						@else
							Test the start_chat and chat_submit API endpoints
						@endif
					</small>
				</div>
                
				<div class="card-body">
				<div id="preview-data" class="d-none"
					data-attempt-id="{{ $existingAttempt->id ?? '' }}"
					data-step-id="{{ $currentStepId ?? '' }}"
					data-step-order="{{ $currentStep->order ?? '' }}"
					data-step-title="{{ $currentStep->title ?? '' }}"
					data-total-steps="{{ $journey->steps()->count() ?? '' }}"
					data-attempt-count="{{ $attemptCount ?? '' }}"
					data-total-attempts="{{ $currentStep->maxattempts ?? 3 }}"
					data-attempt-status="{{ $existingAttempt->status ?? '' }}"
					data-is-started="{{ $existingAttempt ? '1' : '0' }}"
					data-is-completed="{{ $existingAttempt && $existingAttempt->status === 'completed' ? '1' : '0' }}">
				</div>
					<div class="row mb-3">
						<div class="col-md-6">
							<label for="journeyId" class="form-label">Journey</label>
							<select class="form-select" id="journeyId" name="journey_id" style="width: 100%" {{ $existingAttempt ? 'disabled' : '' }}>
								<option value="">Select a journey...</option>
								@foreach(($availableJourneys ?? []) as $j)
									<option value="{{ $j->id }}" {{ ($journey && $journey->id === $j->id) ? 'selected' : '' }}>{{ $j->title }}</option>
								@endforeach
								@if($journey && !($availableJourneys ?? collect())->contains('id', $journey->id))
									<option value="{{ $journey->id }}" selected>{{ $journey->title }}</option>
								@endif
							</select>
							@if($existingAttempt && $journey)
								<input type="hidden" name="journey_id" value="{{ $journey->id }}">
							@endif
						</div>
					</div>

					@php
						// Exclude unnecessary fields from preview-chat
						$excludedVars = [
							'journey_description', 'student_email', 'institution_name', 'journey_title',
							'current_step', 'previous_step', 'previous_steps', 'next_step'
						];
						$locked = (bool) $existingAttempt;
						// Prepare combined list: profile fields first, then master variables as pseudo-fields
						$allInputs = [];
						foreach(($profileFields ?? []) as $field){
							$allInputs[] = [
								'type' => $field->input_type ?: 'text',
								'label' => $field->name,
								'short' => $field->short_name,
								'required' => $field->required,
								'options' => is_array($field->options) ? $field->options : null,
								'value' => $attemptVariables[$field->short_name] ?? ($userProfileDefaults[$field->short_name] ?? ''),
								'source' => 'profile'
							];
						}
						if($journey && !empty($masterVariables)){
							foreach($masterVariables as $var){
								if(in_array($var, $excludedVars)) continue;
								$allInputs[] = [
									'type' => 'text',
									'label' => str_replace('_',' ', $var),
									'short' => $var,
									'required' => false,
									'options' => null,
									'value' => $attemptVariables[$var] ?? ($userProfileDefaults[$var] ?? ''),
									'source' => 'master'
								];
							}
						}
						// Split into two roughly equal columns
						$half = (int) ceil(count($allInputs) / 2);
						$leftInputs = array_slice($allInputs, 0, $half);
						$rightInputs = array_slice($allInputs, $half);
					@endphp

					<div class="row" id="profileFieldsContainer">
						<div class="col-md-6">
							@foreach($leftInputs as $inp)
								@if(in_array($inp['short'], $excludedVars))
									@continue
								@endif
								<div class="mb-2">
									<label class="form-label">{{ $inp['label'] }}</label>
									@if($inp['type'] === 'select' && is_array($inp['options']))
										<select class="form-select variable-input" id="{{ $inp['source'] === 'profile' ? 'profile_' : 'var_' }}{{ $inp['short'] }}" name="{{ $inp['source'] === 'profile' ? 'profile' : 'vars' }}[{{ $inp['short'] }}]" {{ $inp['required'] ? 'required' : '' }} {{ $locked ? 'disabled' : '' }}>
											@foreach($inp['options'] as $opt)
												<option value="{{ $opt }}" {{ $inp['value'] == $opt ? 'selected' : '' }}>{{ $opt }}</option>
											@endforeach
										</select>
									@else
										<input type="{{ $inp['type'] }}" class="form-control variable-input" id="{{ $inp['source'] === 'profile' ? 'profile_' : 'var_' }}{{ $inp['short'] }}" name="{{ $inp['source'] === 'profile' ? 'profile' : 'vars' }}[{{ $inp['short'] }}]" value="{{ $inp['value'] }}" placeholder="Enter {{ strtolower($inp['label']) }}" {{ $inp['required'] ? 'required' : '' }} {{ $locked ? 'disabled' : '' }}>
									@endif
								</div>
							@endforeach
						</div>
						<div class="col-md-6">
							@foreach($rightInputs as $inp)
								@if(in_array($inp['short'], $excludedVars))
									@continue
								@endif
								<div class="mb-2">
									<label class="form-label">{{ $inp['label'] }}</label>
									@if($inp['type'] === 'select' && is_array($inp['options']))
										<select class="form-select variable-input" id="{{ $inp['source'] === 'profile' ? 'profile_' : 'var_' }}{{ $inp['short'] }}" name="{{ $inp['source'] === 'profile' ? 'profile' : 'vars' }}[{{ $inp['short'] }}]" {{ $inp['required'] ? 'required' : '' }} {{ $locked ? 'disabled' : '' }}>
											@foreach($inp['options'] as $opt)
												<option value="{{ $opt }}" {{ $inp['value'] == $opt ? 'selected' : '' }}>{{ $opt }}</option>
											@endforeach
										</select>
									@else
										<input type="{{ $inp['type'] }}" class="form-control variable-input" id="{{ $inp['source'] === 'profile' ? 'profile_' : 'var_' }}{{ $inp['short'] }}" name="{{ $inp['source'] === 'profile' ? 'profile' : 'vars' }}[{{ $inp['short'] }}]" value="{{ $inp['value'] }}" placeholder="Enter {{ strtolower($inp['label']) }}" {{ $inp['required'] ? 'required' : '' }} {{ $locked ? 'disabled' : '' }}>
									@endif
								</div>
							@endforeach
							@if(!$journey)
								<div class="text-muted small">Select a journey to see journey-specific variables.</div>
							@endif
						</div>
					</div>
					<div class="row mb-3">
						<div class="col-md-12 d-flex justify-content-end">
							<button class="btn btn-primary me-2" onclick="(function(btn){btn.disabled=true; setTimeout(()=>{btn.disabled=false;}, 800); startChat();})(this)" {{ $existingAttempt ? 'disabled' : '' }}>Start Chat</button>
							<button class="btn btn-secondary" onclick="clearChat()">Clear</button>
						</div>
					</div>
                    
					<div id="chatContainer" class="border p-3 mb-3" style="height: 600px; overflow-y: auto; background-color: #f8f9fa;">
						@if(!empty($existingMessages))
							@foreach($existingMessages as $m)
								@if($m['type'] === 'step_info')
									{{-- Step information --}}
									<div class="step-info">
										<span class="badge bg-primary">Step {{ $m['step_order'] }}/{{ $m['total_steps'] }}</span>
										<span class="badge bg-info">Attempt {{ $m['step_attempt_count'] }}/{{ $m['step_max_attempts'] }}</span>
										@if($m['rating'])
											<span class="badge bg-warning">
												@for($i = 1; $i <= 5; $i++)
													@if($i <= $m['rating'])★@else☆@endif
												@endfor
												{{ $m['rating'] }}/5
											</span>
										@endif
										@if($m['step_title'])
											<strong>{{ $m['step_title'] }}</strong>
										@endif
									</div>
								@elseif($m['type'] === 'feedback_info')
									{{-- Feedback information --}}
									<div class="feedback-info action-{{ $m['action'] }}">
										@if($m['rating'])
											<strong>Rating:</strong> 
											@for($i = 1; $i <= 5; $i++)
												@if($i <= $m['rating'])★@else☆@endif
											@endfor
											({{ $m['rating'] }}/5)<br>
										@endif
										<strong>Attempt:</strong> {{ $m['step_attempt_count'] }}/{{ $m['step_max_attempts'] }}<br>
										<strong>Action:</strong> 
										@if($m['action'] === 'finish_journey')
											🎉 Journey Completed!
										@elseif($m['action'] === 'next_step')
											➡️ Moving to Next Step
										@elseif($m['action'] === 'retry_step')
											🔄 Retrying Current Step
										@else
											{{ $m['action'] }}
										@endif
									</div>
								@else
									{{-- Regular user/AI messages --}}
									<div class="message {{ $m['type'] === 'user' ? 'user-message' : 'ai-message' }}">
										@if(($m['type'] ?? '') === 'ai')
											{!! $m['content'] !!}
										@else
											{!! nl2br(e($m['content'])) !!}
										@endif
									</div>
								@endif
							@endforeach
							<div class="message system-message">💬 Continuing existing chat session...</div>
						@else
							<p class="text-muted">Click "Start Chat" to begin...</p>
						@endif
					</div>
                    
					<!-- WebSocket and Audio Status -->
					<div class="status-indicators mb-2">
						<small class="text-muted">
							<span id="websocket-status">🔌 WebSocket: <span class="status-text">Connecting...</span></span>
							<span class="mx-2">|</span>
							<span id="audio-status">🎤 Audio: <span class="status-text">Ready</span></span>
						</small>
					</div>
                    
					<div class="input-group">
						<input type="text" class="form-control" id="userInput" placeholder="{{ $existingAttempt && $existingAttempt->status === 'completed' ? 'This session is completed - no more messages allowed' : 'Type your message...' }}" 
							   onkeypress="handleKeyPress(event)" disabled {{ $existingAttempt && $existingAttempt->status === 'completed' ? 'readonly' : '' }}>
						<button class="btn btn-outline-secondary" id="micButton" type="button" title="Voice Input" 
								{{ $existingAttempt && $existingAttempt->status === 'completed' ? 'style=display:none' : '' }}>
							🎤
						</button>
						<button class="btn btn-success" id="sendButton" onclick="sendMessage()" disabled 
								{{ $existingAttempt && $existingAttempt->status === 'completed' ? 'style=display:none' : '' }}>Send</button>
						@if($existingAttempt && $existingAttempt->status === 'completed')
							<button class="btn btn-secondary" disabled>Session Completed</button>
						@endif
					</div>
                    

@push('scripts')
<script>

if (typeof Pusher === 'undefined') {
    console.error('Pusher is not available. Make sure app.js is loaded.');
} else {
    console.log('Pusher is available from compiled assets');
}

// Only allow authenticated users to connect to WebSocket
@auth
// Initialize WebSocket connection using the compiled configuration and global Pusher class
if (typeof window.webSocketConfig === 'undefined') {
    console.error('WebSocket configuration not available. Make sure app.js is loaded.');
} else {
    
    
    const pusher = new window.Pusher(window.webSocketConfig.app_key, {
        wsHost: window.webSocketConfig.host,
        wsPort: window.webSocketConfig.port,
        wssPort: window.webSocketConfig.port,
        forceTLS: window.webSocketConfig.forceTLS,
        encrypted: window.webSocketConfig.encrypted,
        disableStats: window.webSocketConfig.disableStats || true,
        enabledTransports: window.webSocketConfig.enabledTransports || ['ws', 'wss'],
        // For self-hosted Reverb, we don't use Pusher clusters
        cluster: '',
        // Use correct transport based on scheme
        useTLS: window.webSocketConfig.forceTLS
    });

    console.log('Pusher instance created with environment-aware configuration');
    
    // WebSocket connection status handlers with authentication checks
    pusher.connection.bind('connected', function() {
        console.log('WebSocket connected successfully');
        document.querySelector('#websocket-status .status-text').textContent = 'Connected (Authenticated)';
        document.querySelector('#websocket-status .status-text').style.color = 'green';
    });

    pusher.connection.bind('disconnected', function() {
        console.log('WebSocket disconnected');
        document.querySelector('#websocket-status .status-text').textContent = 'Disconnected';
        document.querySelector('#websocket-status .status-text').style.color = 'red';
    });

    pusher.connection.bind('error', function(err) {
        console.error('WebSocket connection error:', err);
        if (err.error && err.error.data && err.error.data.code === 4009) {
            document.querySelector('#websocket-status .status-text').textContent = 'Authentication Failed';
            document.querySelector('#websocket-status .status-text').style.color = 'red';
        }
    });
}
@else
// User is not authenticated - show message instead of connecting
console.warn('WebSocket connection requires authentication');
document.querySelector('#websocket-status .status-text').textContent = 'Authentication Required';
document.querySelector('#websocket-status .status-text').style.color = 'orange';
@endauth

// Subscribe to audio session channel if we have a recording session
function subscribeToAudioChannel(sessionId) {
    if (!sessionId) return;
    
    const audioChannel = pusher.subscribe('private-audio-session.' + sessionId);
    audioChannel.bind('App\\Events\\AudioChunkReceived', function(data) {
        console.log('Audio chunk received via WebSocket:', data);
        document.querySelector('#audio-status .status-text').textContent = 
            `Chunk #${data.chunk_number} received`;
    });
}
</script>
@endpush


<script>
// Read server-provided state from data attributes
const previewDataEl = document.getElementById('preview-data');
let currentAttemptId = previewDataEl?.dataset.attemptId || null;
let currentStepId = previewDataEl?.dataset.stepId || null;
let isProcessing = false;
let isChatStarted = (previewDataEl?.dataset.isStarted === '1');
let isSessionCompleted = (previewDataEl?.dataset.isCompleted === '1');
const isContinueMode = isChatStarted;

// Audio recording variables
let mediaRecorder = null;
let audioChunks = [];
let recordingSessionId = null;
let isRecording = false;
let recordingTimeout = null;

// Excluded variables that should never be sent from preview-chat (derived by API)
const EXCLUDED_VARS = new Set([
	'journey_description', 'student_email', 'institution_name', 'journey_title',
	'current_step', 'previous_step', 'next_step'
]);

// Check if we can send messages (not completed)
function canSendMessages() {
    return !isSessionCompleted;
}

// Enable/disable chat input based on completion status
function updateChatControls() {
    const sendButton = document.getElementById('sendButton');
    const userInput = document.getElementById('userInput');
    
    if (isSessionCompleted) {
        sendButton.disabled = true;
        userInput.disabled = true;
        userInput.readOnly = true;
        userInput.placeholder = 'This session is completed - no more messages allowed';
    } else if (isChatStarted) {
        sendButton.disabled = false;
        userInput.disabled = false;
        userInput.readOnly = false;
        userInput.placeholder = 'Type your message...';
    }
}

function collectVariables() {
    const variables = {};
    
    // Get all variable inputs
    const inputs = document.querySelectorAll('.variable-input');
    inputs.forEach(input => {
        let varName = '';
        if (input.id.startsWith('profile_')) {
            varName = input.id.replace('profile_', '');
        } else if (input.id.startsWith('var_')) {
            varName = input.id.replace('var_', '');
        }
        
	// Skip excluded vars just in case
	if (varName && !EXCLUDED_VARS.has(varName) && input.value.trim()) {
            variables[varName] = input.value.trim();
        }
    });
    
    return variables;
}

function disableVariableInputs() {
    const inputs = document.querySelectorAll('.variable-input');
    inputs.forEach(input => {
        input.disabled = true;
    });
    
    // Also disable journey selector
    document.getElementById('journeyId').disabled = true;
}

function enableVariableInputs() {
    const inputs = document.querySelectorAll('.variable-input');
    inputs.forEach(input => {
        input.disabled = false;
    });
    
    // Also enable journey selector
    document.getElementById('journeyId').disabled = false;
}

// Automatic Token Management (only generate on demand)
let apiToken = null;

async function initializeToken() {
	try {
		addMessage('🔑 Checking for API token...', 'system');
        
		// Check for existing valid token
		const existingToken = await getExistingToken();
        
		if (existingToken) {
			apiToken = existingToken;
			addMessage('✅ Using existing API token', 'system');
		} else {
			// Generate new token only if none exist or are valid
			addMessage('🔄 Generating new API token...', 'system');
            
			const newToken = await generateNewToken();
			if (newToken) {
				apiToken = newToken;
				addMessage('✅ Generated new API token (will be reused on future visits)', 'system');
			} else {
				throw new Error('Failed to generate token');
			}
		}
	} catch (error) {
		addMessage('⚠️ Auto-token failed: ' + error.message, 'error');
		addMessage('📝 You may need to generate a token manually from the API Tokens page', 'system');
		console.error('Token initialization error:', error);
	}
}

async function getExistingToken() {
	try {
		const response = await fetch('/user/api-tokens', {
			method: 'GET',
			headers: {
				'Accept': 'application/json',
				'X-Requested-With': 'XMLHttpRequest'
			},
			credentials: 'same-origin'
		});
        
		if (!response.ok) {
			return null;
		}
        
		const data = await response.json();
        
		// If we have tokens, we need to get one that we can actually use
		// Since we can't see the token values from the API, we'll check if any exist
		// and if so, try to use a stored token from localStorage or generate a new one
		if (data.tokens_count > 0) {
			// Check if we have a stored token from previous session
			const storedToken = localStorage.getItem('chat_test_api_token');
			if (storedToken) {
				// Validate the stored token by making a test API call
				const isValid = await validateToken(storedToken);
				if (isValid) {
					return storedToken;
				}
				// If invalid, remove it
				localStorage.removeItem('chat_test_api_token');
			}
		}
        
		return null;
	} catch (error) {
		console.error('Error checking existing tokens:', error);
		return null;
	}
}

async function validateToken(token) {
	try {
		// Validate token without side effects by calling /api/user
		const response = await fetch('/api/user', {
			method: 'GET',
			headers: {
				'Accept': 'application/json',
				'Authorization': 'Bearer ' + token
			}
		});
		// 200 means token is valid
		return response.status === 200;
	} catch (error) {
		return false;
	}
}

async function generateNewToken() {
	return await generateNewTokenInternal();
}

async function checkAuthentication() {
	try {
		const response = await fetch('/user/api-tokens', {
			method: 'GET',
			headers: {
				'Accept': 'application/json',
				'X-Requested-With': 'XMLHttpRequest'
			},
			credentials: 'same-origin'
		});
        
		// If we get redirected or HTML, user is not authenticated
		if (response.redirected || response.headers.get('content-type')?.includes('text/html')) {
			return false;
		}
        
		return response.ok;
	} catch (error) {
		console.error('Auth check error:', error);
		return false;
	}
}

function addMessage(content, type) {
	const chatContainer = document.getElementById('chatContainer');
	const messageDiv = document.createElement('div');
	messageDiv.className = `message ${type}-message`;
	messageDiv.innerHTML = content;
	chatContainer.appendChild(messageDiv);
	chatContainer.scrollTop = chatContainer.scrollHeight;
}

function addStepInfo(stepData) {
	const chatContainer = document.getElementById('chatContainer');
	const stepDiv = document.createElement('div');
	stepDiv.className = 'step-info';
	
	let stepContent = '';
	
	// Step progress
	if (stepData.order && stepData.total_steps) {
		stepContent += `<span class="badge bg-primary">Step ${stepData.order}/${stepData.total_steps}</span>`;
	} else if (stepData.order) {
		stepContent += `<span class="badge bg-primary">Step ${stepData.order}</span>`;
	}
	
	// Attempt progress (if available)
	if (stepData.attempt_count && stepData.total_attempts) {
		if (stepContent) stepContent += ' ';
		stepContent += `<span class="badge bg-info">Attempt ${stepData.attempt_count}/${stepData.total_attempts}</span>`;
	}
	
	// Rating (if available)
	if (stepData.rating) {
		if (stepContent) stepContent += ' ';
		const stars = '★'.repeat(Math.max(0, Math.min(5, stepData.rating))) + '☆'.repeat(Math.max(0, 5 - Math.max(0, stepData.rating)));
		stepContent += `<span class="badge bg-warning">${stars} ${stepData.rating}/5</span>`;
	}
	
	// Step title
	if (stepData.title) {
		if (stepContent) stepContent += ' ';
		stepContent += `<strong>${stepData.title}</strong>`;
	}
	
	// Fallback if no content was generated
	if (!stepContent.trim()) {
		stepContent = '<span class="badge bg-secondary">Current Step</span>';
	}
	
	stepDiv.innerHTML = stepContent;
	chatContainer.appendChild(stepDiv);
	chatContainer.scrollTop = chatContainer.scrollHeight;
}

function addFeedbackInfo(rating, action, extraData = {}) {
	const chatContainer = document.getElementById('chatContainer');
	const feedbackDiv = document.createElement('div');
	feedbackDiv.className = `feedback-info action-${action}`;
	
	let content = '';
	
	// Rating display
	if (rating !== null && rating !== undefined) {
		const stars = '★'.repeat(Math.max(0, Math.min(5, rating))) + '☆'.repeat(Math.max(0, 5 - Math.max(0, rating)));
		content += `<strong>Rating:</strong> ${stars} (${rating}/5)<br>`;
	}
	
	// Step attempt count display
	if (extraData.step_attempt_count && extraData.step_max_attempts) {
		content += `<strong>Attempt:</strong> ${extraData.step_attempt_count}/${extraData.step_max_attempts}<br>`;
	}
	
	// Action display
	const actionLabels = {
		'finish_journey': '🎉 Journey Completed!',
		'next_step': '➡️ Moving to Next Step',
		'retry_step': '🔄 Retrying Current Step'
	};
	
	content += `<strong>Action:</strong> ${actionLabels[action] || action}`;
	
	// Additional info for progression
	if (action === 'next_step' && extraData.next_step) {
		content += `<br><strong>Next:</strong> Step ${extraData.progressed_to_order} - ${extraData.next_step.title || 'Next Step'}`;
	}
	
	feedbackDiv.innerHTML = content;
	chatContainer.appendChild(feedbackDiv);
	chatContainer.scrollTop = chatContainer.scrollHeight;
}

function handleKeyPress(event) {
	if (event.key === 'Enter' && !isProcessing) {
		sendMessage();
	}
}

function getApiToken() {
	return apiToken || '';
}

async function startChat() {
	if (isProcessing || isChatStarted) {
		return;
	}
	const journeyId = document.getElementById('journeyId').value;

	if (!journeyId) {
		alert('Please select a Journey');
		return;
	}

	// If we already have an existing attempt, just enable chat input (if not completed)
	if (isContinueMode && currentAttemptId) {
		isChatStarted = true;
		updateChatControls();
		if (isSessionCompleted) {
			addMessage('💬 This chat session has been completed. No more messages can be sent.', 'system');
		} else {
			addMessage('💬 Chat session resumed. You can continue the conversation.', 'system');
		}
		return;
	}

	// Collect all variables from form inputs
	const variables = collectVariables();

	isProcessing = true;
	document.querySelector('button.btn.btn-primary.me-2')?.setAttribute('disabled', 'disabled');
	document.getElementById('sendButton').disabled = true;
	document.getElementById('userInput').disabled = true;
	
	// Disable all variable inputs and journey selector
	disableVariableInputs();

	try {
		addMessage('Starting chat session with variables...', 'system');
		
		if (Object.keys(variables).length > 0) {
			addMessage(`Variables: ${JSON.stringify(variables, null, 2)}`, 'system');
		}

		const payload = { 
			journey_id: parseInt(journeyId),
			variables: variables,
			variables_json: JSON.stringify(variables)
		};

		const response = await fetch('/api/chat/start-web', {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
				'Accept': 'text/event-stream, application/json',
				'X-Requested-With': 'XMLHttpRequest',
				'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
			},
			credentials: 'same-origin',
			body: JSON.stringify(payload)
		});

		// Ensure we actually got an SSE stream; otherwise show a meaningful error
		const ct = response.headers.get('content-type') || '';
		if (!ct.includes('text/event-stream')) {
			const preview = await response.text();
			console.error('Non-SSE response from start:', response.status, ct, preview.slice(0, 400));
			addMessage('❌ Unexpected response (not a stream). Check authentication and token. See console for details.', 'error');
			throw new Error(`Unexpected content-type: ${ct}`);
		}

		if (!response.ok) {
			const errorText = await response.text();
			throw new Error(`HTTP ${response.status}: ${errorText}`);
		}

	addMessage('✅ Chat session started successfully!', 'system');
	isChatStarted = true;
		await handleStreamResponse(response);

	} catch (error) {
		console.error('Start chat error:', error);
		addMessage(`Error starting chat: ${error.message}`, 'error');
		
		// Re-enable inputs on error
		isChatStarted = false;
		enableVariableInputs();
	} finally {
	isProcessing = false;
	document.querySelector('button.btn.btn-primary.me-2')?.removeAttribute('disabled');
		document.getElementById('sendButton').disabled = false;
		document.getElementById('userInput').disabled = false;
	}
}

async function sendMessage() {
	const userInput = document.getElementById('userInput').value.trim();

	if (!canSendMessages()) {
		alert('This chat session has been completed. No more messages can be sent.');
		return;
	}

	if (!userInput) {
		alert('Please enter a message');
		return;
	}

	if (!currentAttemptId) {
		alert('Please start a chat session first');
		return;
	}

	isProcessing = true;
	document.getElementById('sendButton').disabled = true;
	document.getElementById('userInput').disabled = true;

	try {
		addMessage(userInput, 'user');
		document.getElementById('userInput').value = '';

		const payload = {
			attempt_id: currentAttemptId,
			user_input: userInput
		};

		if (currentStepId) {
			payload.step_id = currentStepId;
		}

		const response = await fetch('/api/chat/submit-web', {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
				'Accept': 'text/event-stream',
				'X-Requested-With': 'XMLHttpRequest',
				'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
			},
			credentials: 'same-origin',
			body: JSON.stringify(payload)
		});

		if (!response.ok) {
			const errorText = await response.text();
			throw new Error(`HTTP ${response.status}: ${errorText}`);
		}

		await handleStreamResponse(response);

	} catch (error) {
		console.error('Send message error:', error);
		addMessage(`Error sending message: ${error.message}`, 'error');
	} finally {
		isProcessing = false;
		document.getElementById('sendButton').disabled = false;
		document.getElementById('userInput').disabled = false;
	}
}

async function handleStreamResponse(response) {
	const reader = response.body.getReader();
	const decoder = new TextDecoder();
	let aiMessageDiv = null;
	let hasReceivedContent = false;
	// Buffer text so we don't inject broken HTML while streaming
	let aiHtmlBuffer = '';
	// SSE buffer to handle partial frames across chunks
	let sseBuffer = '';

	addMessage('🔄 Processing response...', 'system');

	while (true) {
		const { done, value } = await reader.read();
		if (done) {
			// Flush any remaining buffered message
			if (sseBuffer.trim()) {
				// Check if it's a partial message that doesn't end with proper SSE format
				const trimmedBuffer = sseBuffer.trim();
				if (trimmedBuffer && !trimmedBuffer.endsWith('\n\n')) {
					// Add the missing line ending for processing
					processSseMessage(trimmedBuffer);
				} else {
					processSseMessage(trimmedBuffer);
				}
				sseBuffer = '';
			}
			if (!hasReceivedContent) addMessage('⚠️ No content received from AI', 'system');
			break;
		}
		// Append and process complete SSE messages separated by blank line
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
			if (trimmed.startsWith('event:')) {
				// Optional: handle named events
				continue;
			}
			if (trimmed.startsWith('data:')) {
				dataLines.push(trimmed.substring(5).trimStart());
			}
		}
		if (dataLines.length === 0) return;
		const data = dataLines.join('\n').trim();
		
		// Skip empty data
		if (!data) return;
		
		if (data === '[DONE]') {
			if (hasReceivedContent) addMessage('✅ Response completed', 'system');
			return;
		}
		try {
			const parsed = JSON.parse(data);
			// Completion
			if (parsed.type === 'done') {
				if (aiMessageDiv && aiHtmlBuffer) {
					aiMessageDiv.innerHTML = aiHtmlBuffer;
					document.getElementById('chatContainer').scrollTop = 
						document.getElementById('chatContainer').scrollHeight;
				}
				if (hasReceivedContent) addMessage('✅ Response completed', 'system');
				
				// Show rating and action feedback
				if (parsed.action && (parsed.rating !== null && parsed.rating !== undefined)) {
					addFeedbackInfo(parsed.rating, parsed.action, {
						next_step: parsed.next_step,
						progressed_to_order: parsed.progressed_to_order,
						current_step_order: parsed.current_step_order,
						total_steps: parsed.total_steps,
						step_attempt_count: parsed.step_attempt_count,
						step_max_attempts: parsed.step_max_attempts
					});
				}
				
				// Check if the journey is now complete
				if (parsed.is_complete) {
					const previewData = document.getElementById('preview-data');
					if (previewData) {
						previewData.setAttribute('data-is-completed', 'true');
						previewData.setAttribute('data-attempt-status', 'completed');
					}
					// Update global completion status
					isSessionCompleted = true;
					// Update chat controls to prevent further messaging
					updateChatControls();
					addMessage('🎉 Journey completed! No further messages can be sent.', 'system');
				}
				
				aiHtmlBuffer = '';
				aiMessageDiv = null;
				return;
			}
			// Metadata
			if (parsed.type === 'metadata' || (parsed.step_id && !parsed.text && !parsed.error)) {
				const oldStepId = currentStepId;
				currentStepId = parsed.step_id;
				if (parsed.attempt_id) currentAttemptId = parsed.attempt_id;
				
				// Show step information when step changes or is first set
				if (currentStepId && currentStepId !== oldStepId) {
					addStepInfo({
						order: parsed.step_order,
						title: parsed.step_title,
						total_steps: parsed.total_steps,
						attempt_count: parsed.attempt_count,
						total_attempts: parsed.total_attempts
					});
				}
				return;
			}
			// Chunk text
			if (parsed.text) {
				hasReceivedContent = true;
				if (!aiMessageDiv) {
					aiMessageDiv = document.createElement('div');
					aiMessageDiv.className = 'message ai-message';
					aiMessageDiv.innerHTML = '<em>...</em>';
					document.getElementById('chatContainer').appendChild(aiMessageDiv);
				}
				aiHtmlBuffer += parsed.text;
				// While streaming, render as text to avoid broken HTML tags
				aiMessageDiv.textContent = aiHtmlBuffer;
				document.getElementById('chatContainer').scrollTop = 
					document.getElementById('chatContainer').scrollHeight;
			}
			if (parsed.error) addMessage(`Error: ${parsed.error.message || parsed.error}`, 'error');
		} catch (e) {
			// Only log meaningful errors (skip empty data)
			if (data && data.length > 0) {
				console.error('Error parsing SSE data:', e, 'Raw:', data);
				// If this is a substantial piece of data that failed to parse, show an error
				if (data.length > 5) {
					addMessage(`⚠️ Received malformed data from server (${data.length} chars)`, 'error');
				}
			}
		}
	}
}

function loadExistingMessages() {
    const chatContainer = document.getElementById('chatContainer');
    chatContainer.innerHTML = '';
    
    if (presetMessages && presetMessages.length > 0) {
        presetMessages.forEach(message => {
            addMessage(message.content, message.type);
        });
        
        // Enable chat input for continuing the conversation
        document.getElementById('userInput').disabled = false;
        document.getElementById('sendButton').disabled = false;
        
        // Show continuation message
        addMessage('💬 Continuing existing chat session...', 'system');
    } else {
        chatContainer.innerHTML = '<p class="text-muted">Existing session loaded. Click "Start Chat" to continue...</p>';
    }
}

function clearChat() {
	// Get the current journey ID
	const journeyId = document.getElementById('journeyId').value;
	
	if (journeyId) {
		// Redirect to a fresh preview session for this journey
		window.location.href = `/preview-chat?journey=${journeyId}`;
	} else {
		// Fallback: just clear the current chat
		document.getElementById('chatContainer').innerHTML = '<p class="text-muted">Chat cleared. Click "Start Chat" to begin...</p>';
		currentAttemptId = null;
		currentStepId = null;
		isChatStarted = false;
		
		// Re-enable all inputs and update chat controls
		if (!isContinueMode) {
			enableVariableInputs();
		}
		updateChatControls();
	}
}

// Audio Recording Functions
async function initAudioRecording() {
	try {
		if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
			throw new Error('Audio recording not supported in this browser');
		}

		const stream = await navigator.mediaDevices.getUserMedia({ 
			audio: {
				sampleRate: 16000,
				channelCount: 1,
				echoCancellation: true,
				noiseSuppression: true,
				autoGainControl: true
			} 
		});

		mediaRecorder = new MediaRecorder(stream, {
			mimeType: 'audio/webm;codecs=opus'
		});

		audioChunks = [];

		mediaRecorder.ondataavailable = async (event) => {
			if (event.data.size > 0) {
				audioChunks.push(event.data);
			}
		};

		mediaRecorder.onstop = async () => {
			// Send all chunks, marking the last one as final
			for (let i = 0; i < audioChunks.length; i++) {
				const isLastChunk = (i === audioChunks.length - 1);
				await sendAudioChunk(audioChunks[i], i, isLastChunk);
			}
			
			// Complete the recording session
			await completeAudioRecording();
		};

		return true;
	} catch (error) {
		console.error('Error initializing audio recording:', error);
		addMessage('Error: Could not access microphone. Please check permissions.', 'error');
		return false;
	}
}

async function startAudioRecording() {
	if (isRecording) {
		stopAudioRecording();
		return;
	}

	if (!currentAttemptId) {
		addMessage('Error: No active journey session. Please start a chat first.', 'error');
		return;
	}

	try {
		// Initialize recording if not already done
		if (!mediaRecorder) {
			const success = await initAudioRecording();
			if (!success) return;
		}

		// Generate session ID
		recordingSessionId = 'audio_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);

		// Start recording session on server
		const response = await fetch('/audio/start-recording', {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
				'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
				'Accept': 'application/json'
			},
			body: JSON.stringify({
				journey_attempt_id: currentAttemptId,
				journey_step_id: currentStepId,
				session_id: recordingSessionId
			})
		});

		if (!response.ok) {
			const errorData = await response.json().catch(() => ({}));
			throw new Error(errorData.error || `HTTP ${response.status}: ${response.statusText}`);
		}

		// Start recording
		mediaRecorder.start(1000); // Capture in 1-second chunks
		isRecording = true;

		// Update UI
		const micButton = document.getElementById('micButton');
		micButton.innerHTML = '🔴';
		micButton.title = 'Stop Recording (Max 30s)';
		micButton.classList.add('btn-danger');
		micButton.classList.remove('btn-outline-secondary');
		
		// Update audio status
		document.querySelector('#audio-status .status-text').textContent = 'Recording...';
		document.querySelector('#audio-status .status-text').style.color = 'red';
		
		// Subscribe to WebSocket audio channel for this session
		subscribeToAudioChannel(recordingSessionId);
		
		addMessage('🎤 Recording started... (Maximum 30 seconds)', 'system');

		// Set 30-second timeout
		recordingTimeout = setTimeout(() => {
			stopAudioRecording();
			addMessage('⏰ Recording stopped - 30 second limit reached', 'system');
		}, 30000);

	} catch (error) {
		console.error('Error starting recording:', error);
		addMessage('Error: Failed to start recording - ' + error.message, 'error');
		
		// Reset state
		isRecording = false;
		recordingSessionId = null;
		
		// Reset UI
		const micButton = document.getElementById('micButton');
		micButton.innerHTML = '🎤';
		micButton.title = 'Voice Input';
		micButton.classList.remove('btn-danger');
		micButton.classList.add('btn-outline-secondary');
	}
}

async function stopAudioRecording() {
	if (!isRecording || !mediaRecorder) return;

	try {
		isRecording = false;
		
		// Clear timeout
		if (recordingTimeout) {
			clearTimeout(recordingTimeout);
			recordingTimeout = null;
		}

		// Stop recording
		mediaRecorder.stop();

		// Update UI
		const micButton = document.getElementById('micButton');
		micButton.innerHTML = '🎤';
		micButton.title = 'Voice Input';
		micButton.classList.remove('btn-danger');
		micButton.classList.add('btn-outline-secondary');
		
		// Update audio status
		document.querySelector('#audio-status .status-text').textContent = 'Processing...';
		document.querySelector('#audio-status .status-text').style.color = 'orange';
		
		addMessage('🎤 Recording stopped. Processing...', 'system');

	} catch (error) {
		console.error('Error stopping recording:', error);
		addMessage('Error: Failed to stop recording - ' + error.message, 'error');
		
		// Reset state anyway
		isRecording = false;
		const micButton = document.getElementById('micButton');
		micButton.innerHTML = '🎤';
		micButton.title = 'Voice Input';
		micButton.classList.remove('btn-danger');
		micButton.classList.add('btn-outline-secondary');
	}
}

async function sendAudioChunk(audioBlob, chunkNumber, isFinal = false) {
	if (!recordingSessionId) return;

	try {
		// Convert blob to base64
		const arrayBuffer = await audioBlob.arrayBuffer();
		const uint8Array = new Uint8Array(arrayBuffer);
		const base64 = btoa(String.fromCharCode.apply(null, uint8Array));

		const response = await fetch('/audio/process-chunk', {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
				'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
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
			console.error('Failed to send audio chunk:', response.statusText);
		}

	} catch (error) {
		console.error('Error sending audio chunk:', error);
	}
}

async function completeAudioRecording() {
	if (!recordingSessionId) return;

	try {
		const response = await fetch('/audio/complete', {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
				'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
				'Accept': 'application/json'
			},
			body: JSON.stringify({
				session_id: recordingSessionId
			})
		});

		if (!response.ok) {
			throw new Error(`HTTP ${response.status}: ${response.statusText}`);
		}

		// Poll for transcription result
		pollForTranscription();

	} catch (error) {
		console.error('Error completing recording:', error);
		addMessage('Error: Failed to complete recording - ' + error.message, 'error');
	}
}

async function pollForTranscription() {
	if (!recordingSessionId) return;

	const maxAttempts = 30; // 30 seconds max wait
	let attempts = 0;

	const poll = async () => {
		try {
			const response = await fetch(`/audio/transcription/${recordingSessionId}`, {
				headers: {
					'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
					'Accept': 'application/json'
				}
			});

			if (!response.ok) {
				const errorData = await response.json().catch(() => ({}));
				throw new Error(errorData.error || `HTTP ${response.status}: ${response.statusText}`);
			}

			const data = await response.json();

			if (data.status === 'completed' && data.transcription) {
				// Insert transcription into input field
				const userInput = document.getElementById('userInput');
				const currentValue = userInput.value.trim();
				const newValue = currentValue ? currentValue + ' ' + data.transcription : data.transcription;
				userInput.value = newValue;
				
				// Update audio status
				document.querySelector('#audio-status .status-text').textContent = 'Ready';
				document.querySelector('#audio-status .status-text').style.color = 'green';
				
				addMessage('✅ Transcription complete: "' + data.transcription + '"', 'system');
				
				// Clean up recording session
				recordingSessionId = null;
				
				// Automatically submit the transcribed message
				if (newValue.length > 0) {
					addMessage('🚀 Auto-submitting transcribed message...', 'system');
					
					// Small delay to let user see the transcription before submitting
					setTimeout(() => {
						sendMessage();
					}, 1000);
				}
				
				return;
			} 
			
			if (data.status === 'failed') {
				addMessage('❌ Transcription failed. Please try recording again.', 'error');
				recordingSessionId = null;
				return;
			}

			// Continue polling if still processing
			attempts++;
			if (attempts < maxAttempts) {
				setTimeout(poll, 1000);
			} else {
				addMessage('⏰ Transcription timeout. Please try again.', 'error');
				recordingSessionId = null;
			}

		} catch (error) {
			console.error('Error polling transcription:', error);
			addMessage('Error: Failed to get transcription - ' + error.message, 'error');
			recordingSessionId = null;
		}
	};

	// Start polling
	poll();
}

async function generateNewTokenInternal() {
	try {
		// Get CSRF token
		const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        
		if (!csrfToken) {
			throw new Error('CSRF token not found. Please refresh the page.');
		}
        
		const response = await fetch('/user/api-tokens', {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
				'Accept': 'application/json',
				'X-CSRF-TOKEN': csrfToken,
				'X-Requested-With': 'XMLHttpRequest'
			},
			credentials: 'same-origin', // Include session cookies
			body: JSON.stringify({
				name: 'Chat Test Token - ' + new Date().toISOString().slice(0, 19).replace('T', ' ')
			})
		});
        
		// Check if we got redirected (likely to login page)
		if (response.redirected) {
			throw new Error('Session expired. Please refresh the page and try again.');
		}
        
		// Check if response is HTML (likely an error page)
		const contentType = response.headers.get('content-type');
		if (contentType && contentType.includes('text/html')) {
			const htmlText = await response.text();
			if (htmlText.includes('login') || htmlText.includes('Login')) {
				throw new Error('Please log in first to generate API tokens.');
			}
			throw new Error('Received HTML response instead of JSON. Please check your session.');
		}
        
		if (!response.ok) {
			let errorMessage = 'Failed to create token';
			try {
				const errorData = await response.json();
				errorMessage = errorData.message || errorMessage;
			} catch (e) {
				errorMessage = `HTTP ${response.status}: ${response.statusText}`;
			}
			throw new Error(errorMessage);
		}
        
		const data = await response.json();
        
		if (!data.token) {
			throw new Error('Token not found in response');
		}
        
		// Store the token in localStorage for reuse
		localStorage.setItem('chat_test_api_token', data.token);
        
		return data.token;
	} catch (error) {
		console.error('Error generating token:', error);
		throw error;
	}
}

// Initialize everything when page loads
document.addEventListener('DOMContentLoaded', function() {
	// If continuing existing attempt, set up chat controls based on completion status
	if (isContinueMode) {
		updateChatControls();
		
		// Initialize API token for continuing sessions
		initializeToken();
		
		// Note: Step info is now shown directly in the Blade template for existing sessions
		// so it persists on refresh. Only add via JavaScript for new dynamic updates.
	} else {
		// For new sessions, show step info when journey is selected and chat starts
	}

	// Add mic button event listener
	const micButton = document.getElementById('micButton');
	if (micButton) {
		micButton.addEventListener('click', function() {
			if (!canSendMessages()) {
				addMessage('Error: This session is completed - no more input allowed', 'error');
				return;
			}

			if (!isChatStarted) {
				addMessage('Error: Please start a chat session first', 'error');
				return;
			}

			if (isRecording) {
				stopAudioRecording();
			} else {
				startAudioRecording();
			}
		});
	}
});
</script>
@endsection
