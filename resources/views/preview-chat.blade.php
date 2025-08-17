
<style>
.message {
    padding: 10px;
    margin: 8px 0;
    border-radius: 8px;
    max-width: 80%;
    word-wrap: break-word;
}
.user-message {
    background-color: #007bff;
    color: white;
    margin-left: auto;
    text-align: right;
}
.ai-message {
    background-color: #e9ecef;
    color: #333;
    margin-right: auto;
}
.error-message {
    background-color: #dc3545;
    color: white;
    margin-right: auto;
}
.system-message {
    background-color: #17a2b8;
    color: white;
    margin: 0 auto;
    text-align: center;
    font-style: italic;
    font-size: 0.9em;
}
/* Rendered AI node blocks */
.ainode-reflection {
	background: #fff8e1;
	border-left: 4px solid #f0ad4e;
	padding: 10px;
	border-radius: 6px;
	margin: 6px 0;
}
.ainode-teaching {
	background: #e7f3ff;
	border-left: 4px solid #0d6efd;
	padding: 10px;
	border-radius: 6px;
	margin: 6px 0;
}
.ainode-task {
	background: #e9f7ef;
	border-left: 4px solid #28a745;
	padding: 10px;
	border-radius: 6px;
	margin: 6px 0;
}
</style>

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
						@else
							Test the start_chat and chat_submit API endpoints
						@endif
					</small>
				</div>
                
				<div class="card-body">
				<div id="preview-data" class="d-none"
					data-attempt-id="{{ $existingAttempt->id ?? '' }}"
					data-step-id="{{ $currentStepId ?? '' }}"
					data-is-started="{{ $existingAttempt ? '1' : '0' }}">
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
                    
					<div id="chatContainer" class="border p-3 mb-3" style="height: 400px; overflow-y: auto; background-color: #f8f9fa;">
						@if(!empty($existingMessages))
							@foreach($existingMessages as $m)
								<div class="message {{ $m['type'] === 'user' ? 'user-message' : 'ai-message' }}">
									@if(($m['type'] ?? '') === 'ai')
										{!! $m['content'] !!}
									@else
										{!! nl2br(e($m['content'])) !!}
									@endif
								</div>
							@endforeach
							<div class="message system-message">💬 Continuing existing chat session...</div>
						@else
							<p class="text-muted">Click "Start Chat" to begin...</p>
						@endif
					</div>
                    
					<div class="input-group">
						<input type="text" class="form-control" id="userInput" placeholder="Type your message..." 
							   onkeypress="handleKeyPress(event)" disabled>
						<button class="btn btn-success" id="sendButton" onclick="sendMessage()" disabled>Send</button>
					</div>
                    
					<div class="mt-3">
						<small class="text-muted">
							<strong>Instructions:</strong><br>
							1. <a href="{{ route('api-tokens.index') }}" target="_blank">Generate an API token</a> from the API Tokens page<br>
							2. Select a Journey from the dropdown<br>
							3. Fill in all profile fields and variables to customize the conversation<br>
							4. Click "Start Chat" to initialize the conversation (fields will be locked)<br>
							5. Type messages and press Enter or click Send<br>
							6. Use "Clear" to reset and change variables again<br>

@push('scripts')
@endpush


<script>
// Read server-provided state from data attributes
const previewDataEl = document.getElementById('preview-data');
let currentAttemptId = previewDataEl?.dataset.attemptId || null;
let currentStepId = previewDataEl?.dataset.stepId || null;
let isProcessing = false;
let isChatStarted = (previewDataEl?.dataset.isStarted === '1');
const isContinueMode = isChatStarted;

// Excluded variables that should never be sent from preview-chat (derived by API)
const EXCLUDED_VARS = new Set([
	'journey_description', 'student_email', 'institution_name', 'journey_title',
	'current_step', 'previous_step', 'next_step'
]);

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
	const token = getApiToken();

	if (!journeyId) {
		alert('Please select a Journey');
		return;
	}

	if (!token) {
		addMessage('❌ No API token available. Trying to generate one...', 'error');
		await initializeToken();
		const newToken = getApiToken();
		if (!newToken) {
			alert('Failed to generate API token. Please check your authentication.');
			return;
		}
	}

	// If we already have an existing attempt, just enable chat input
	if (isContinueMode && currentAttemptId) {
		isChatStarted = true;
		document.getElementById('sendButton').disabled = false;
		document.getElementById('userInput').disabled = false;
		addMessage('💬 Chat session resumed. You can continue the conversation.', 'system');
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

		const response = await fetch('/api/chat/start', {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
				'Authorization': `Bearer ${token}`,
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
	const token = getApiToken();

	if (!userInput) {
		alert('Please enter a message');
		return;
	}

	if (!currentAttemptId) {
		alert('Please start a chat session first');
		return;
	}

	if (!token) {
		alert('API token is required');
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

		const response = await fetch('/api/chat/submit', {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
				'Authorization': `Bearer ${token}`,
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
				processSseMessage(sseBuffer);
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
		const data = dataLines.join('\n');
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
				aiHtmlBuffer = '';
				aiMessageDiv = null;
				return;
			}
			// Metadata
			if (parsed.type === 'metadata' || (parsed.step_id && !parsed.text && !parsed.error)) {
				currentStepId = parsed.step_id;
				if (parsed.attempt_id) currentAttemptId = parsed.attempt_id;
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
			console.error('Error parsing SSE data:', e, 'Raw:', data);
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
	document.getElementById('chatContainer').innerHTML = '<p class="text-muted">Chat cleared. Click "Start Chat" to begin...</p>';
	currentAttemptId = null;
	currentStepId = null;
	isChatStarted = false;
	
	// Re-enable all inputs
	if (!isContinueMode) {
		enableVariableInputs();
	}
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
	// If continuing existing attempt, enable chat input immediately
	if (isContinueMode) {
		document.getElementById('userInput').disabled = false;
		document.getElementById('sendButton').disabled = false;
	}
});
</script>
@endsection
