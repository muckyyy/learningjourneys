
<style>
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
					<small class="text-muted">Test the start_chat and chat_submit API endpoints</small>
				</div>
                
				<div class="card-body">
					<div class="row mb-3">
						<div class="col-md-6">
							<label for="journeyId" class="form-label">Journey</label>
							<select class="form-select" id="journeyId" style="width: 100%"></select>
						</div>
						<div class="col-md-6" id="profileFieldsContainer">
							<!-- Dynamic profile fields will be inserted here -->
						</div>
					</div>
					<div class="row mb-3">
						<div class="col-md-12 d-flex justify-content-end">
							<button class="btn btn-primary me-2" onclick="startChat()">Start Chat</button>
							<button class="btn btn-secondary" onclick="clearChat()">Clear</button>
						</div>
					</div>
                    
					<div id="chatContainer" class="border p-3 mb-3" style="height: 400px; overflow-y: auto; background-color: #f8f9fa;">
						<p class="text-muted">Click "Start Chat" to begin...</p>
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
							3. Fill in any profile fields to simulate different user environments<br>
							4. Click "Start Chat" to initialize the conversation<br>
							5. Type messages and press Enter or click Send<br>

@push('scripts')
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
@endpush

// ...existing code...


<script>
let currentAttemptId = null;
let currentStepId = null;
let isProcessing = false;

// Automatic Token Management
async function initializeToken() {
	const tokenInput = document.getElementById('apiToken');
	const tokenStatus = document.getElementById('tokenStatus');
    
	try {
		tokenStatus.textContent = 'Checking...';
		tokenStatus.className = 'badge bg-warning';
        
		// Check for existing valid token
		const existingToken = await getExistingToken();
        
		if (existingToken) {
			tokenInput.value = existingToken;
			tokenStatus.textContent = 'Reused';
			tokenStatus.className = 'badge bg-success';
			addMessage('✅ Using existing API token', 'system');
		} else {
			// Generate new token only if none exist or are valid
			tokenStatus.textContent = 'Generating...';
			tokenStatus.className = 'badge bg-info';
            
			const newToken = await generateNewToken();
			if (newToken) {
				tokenInput.value = newToken;
				tokenStatus.textContent = 'Generated';
				tokenStatus.className = 'badge bg-success';
				addMessage('✅ Generated new API token (will be reused on future visits)', 'system');
			} else {
				throw new Error('Failed to generate token');
			}
		}
	} catch (error) {
		tokenStatus.textContent = 'Manual';
		tokenStatus.className = 'badge bg-warning';
		tokenInput.placeholder = 'Please enter token manually or click Generate New';
		addMessage('⚠️ Auto-token failed: ' + error.message, 'error');
		addMessage('📝 You can manually paste a token above or use the Generate New button', 'system');
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
		const response = await fetch('/api/start_chat', {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
				'Accept': 'application/json',
				'Authorization': 'Bearer ' + token
			},
			body: JSON.stringify({
				journey_id: 1
			})
		});
        
		// If we get 401, token is invalid
		// If we get other errors (like validation), token might still be valid
		return response.status !== 401;
	} catch (error) {
		return false;
	}
}

async function generateNewToken() {
	return await generateNewTokenInternal();
}

// Initialize token when page loads
document.addEventListener('DOMContentLoaded', function() {
	// First check if user is authenticated
	checkAuthentication().then(isAuth => {
		if (isAuth) {
			initializeToken();
		} else {
			const tokenStatus = document.getElementById('tokenStatus');
			const tokenInput = document.getElementById('apiToken');
			tokenStatus.textContent = 'Not Logged In';
			tokenStatus.className = 'badge bg-danger';
			tokenInput.placeholder = 'Please log in first';
			addMessage('❌ You must be logged in to use this feature', 'error');
			addMessage('🔐 Please log in and refresh this page', 'system');
		}
	});
});

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
	return document.getElementById('apiToken').value.trim();
}

async function startChat() {
	const journeyId = document.getElementById('journeyId').value;
	const attemptId = document.getElementById('attemptId').value;
	const token = getApiToken();

	if (!journeyId) {
		alert('Please provide Journey ID');
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

	isProcessing = true;
	document.getElementById('sendButton').disabled = true;
	document.getElementById('userInput').disabled = true;

	try {
		addMessage('Starting chat session...', 'system');

		const payload = { journey_id: parseInt(journeyId) };
		if (attemptId) {
			payload.attempt_id = parseInt(attemptId);
		}

		const response = await fetch('/api/chat/start', {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
				'Authorization': `Bearer ${token}`,
				'Accept': 'text/event-stream',
				'X-Requested-With': 'XMLHttpRequest',
				'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
			},
			body: JSON.stringify(payload)
		});

		if (!response.ok) {
			const errorText = await response.text();
			throw new Error(`HTTP ${response.status}: ${errorText}`);
		}

		await handleStreamResponse(response);

	} catch (error) {
		console.error('Start chat error:', error);
		addMessage(`Error starting chat: ${error.message}`, 'error');
	} finally {
		isProcessing = false;
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

	while (true) {
		const { done, value } = await reader.read();
		if (done) break;

		const chunk = decoder.decode(value);
		const lines = chunk.split('\n');

		for (const line of lines) {
			if (line.trim() === '') continue;
            
			if (line.startsWith('event: ')) {
				const event = line.substring(7);
				console.log('Event:', event);
				continue;
			}
            
			if (line.startsWith('data: ')) {
				const data = line.substring(6);
                
				if (data === '[DONE]') {
					console.log('Stream completed');
					continue;
				}

				try {
					const parsed = JSON.parse(data);
					console.log('Parsed data:', parsed);

					// Handle metadata
					if (parsed.step_id && !parsed.text && !parsed.error) {
						currentStepId = parsed.step_id;
						if (parsed.attempt_id) {
							currentAttemptId = parsed.attempt_id;
						}
						console.log('Metadata updated:', { currentStepId, currentAttemptId });
						continue;
					}

					// Handle text chunks
					if (parsed.text) {
						if (!aiMessageDiv) {
							aiMessageDiv = document.createElement('div');
							aiMessageDiv.className = 'message ai-message';
							aiMessageDiv.innerHTML = '';
							document.getElementById('chatContainer').appendChild(aiMessageDiv);
						}
						aiMessageDiv.innerHTML += parsed.text;
						document.getElementById('chatContainer').scrollTop = 
							document.getElementById('chatContainer').scrollHeight;
					}

					// Handle errors
					if (parsed.error) {
						addMessage(`Error: ${parsed.error.message || parsed.error}`, 'error');
					}

				} catch (e) {
					console.error('Error parsing SSE data:', e, 'Raw data:', data);
				}
			}
		}
	}
}

function clearChat() {
	document.getElementById('chatContainer').innerHTML = '<p class="text-muted">Chat cleared. Click "Start Chat" to begin...</p>';
	currentAttemptId = null;
	currentStepId = null;
}

// Standalone function for the "Generate New" button
async function generateNewTokenManual() {
	const tokenStatus = document.getElementById('tokenStatus');
	const tokenInput = document.getElementById('apiToken');
    
	try {
		tokenStatus.textContent = 'Generating...';
		tokenStatus.className = 'badge bg-info';
        
		const newToken = await generateNewTokenInternal();
        
		if (newToken) {
			tokenInput.value = newToken;
			tokenStatus.textContent = 'Generated';
			tokenStatus.className = 'badge bg-success';
			addMessage('✅ Generated new API token (previous token replaced)', 'system');
		} else {
			throw new Error('Failed to generate token');
		}
	} catch (error) {
		tokenStatus.textContent = 'Manual';
		tokenStatus.className = 'badge bg-warning';
		tokenInput.placeholder = 'Please enter token manually';
		addMessage('❌ Failed to generate new token: ' + error.message, 'error');
		addMessage('📝 Please visit the API Tokens page to generate manually', 'system');
		console.error('Token generation error:', error);
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

// Handle manual token input
function handleTokenInput() {
	const tokenInput = document.getElementById('apiToken');
	const token = tokenInput.value.trim();
    
	if (token && token.length > 10) { // Basic validation
		// Store manually entered token
		localStorage.setItem('chat_test_api_token', token);
		const tokenStatus = document.getElementById('tokenStatus');
		tokenStatus.textContent = 'Manual';
		tokenStatus.className = 'badge bg-info';
		addMessage('📝 Manual token saved for reuse', 'system');
	}
}

// Clear stored token
function clearStoredToken() {
	localStorage.removeItem('chat_test_api_token');
	const tokenInput = document.getElementById('apiToken');
	const tokenStatus = document.getElementById('tokenStatus');
    
	tokenInput.value = '';
	tokenStatus.textContent = 'Cleared';
	tokenStatus.className = 'badge bg-secondary';
	addMessage('🗑️ Stored token cleared. Will generate new token on next refresh.', 'system');
}
</script>
@endsection
