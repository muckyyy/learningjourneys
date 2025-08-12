
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
							3. Fill in all profile fields and variables to customize the conversation<br>
							4. Click "Start Chat" to initialize the conversation (fields will be locked)<br>
							5. Type messages and press Enter or click Send<br>
							6. Use "Clear" to reset and change variables again<br>

@push('scripts')
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
@endpush

// ...existing code...


<script>
let currentAttemptId = null;
let currentStepId = null;
let isProcessing = false;
let allProfileFields = [];
let allJourneys = [];
let userProfile = {};
let isChatStarted = false;

function renderProfileFieldsAndVariables(journeyId) {
    const journey = allJourneys.find(j => j.id == journeyId);
    const container = document.getElementById('profileFieldsContainer');
    container.innerHTML = '';
    let usedShortNames = [];
    
    // Render all profile fields (prefill with user values)
    allProfileFields.forEach(field => {
        let inputType = field.input_type || 'text';
        let placeholder = field.description || ('Enter ' + field.name);
        let required = field.required ? 'required' : '';
        let options = '';
        let value = userProfile[field.short_name] || '';
        
        if (inputType === 'select' && Array.isArray(field.options)) {
            options = field.options.map(opt => `<option value="${opt}"${value==opt?' selected':''}>${opt}</option>`).join('');
        }
        
        const div = document.createElement('div');
        div.className = 'mb-2';
        
        if (inputType === 'select') {
            div.innerHTML = `<label class="form-label">${field.name}</label><select class="form-select variable-input" id="profile_${field.short_name}" ${required}${isChatStarted?' disabled':''}>${options}</select>`;
        } else {
            div.innerHTML = `<label class="form-label">${field.name}</label><input type="${inputType}" class="form-control variable-input" id="profile_${field.short_name}" placeholder="${placeholder}" value="${value}" ${required}${isChatStarted?' disabled':''}>`;
        }
        
        container.appendChild(div);
        usedShortNames.push(field.short_name);
    });
    
    // Extract variables from master_prompt
    if (journey && journey.master_prompt) {
        const regex = /\{([a-zA-Z0-9_]+)\}/g;
        let match;
        let foundVars = [];
        
        while ((match = regex.exec(journey.master_prompt)) !== null) {
            const varName = match[1];
            if (!usedShortNames.includes(varName) && !foundVars.includes(varName)) {
                foundVars.push(varName);
                // Prefill with userProfile if available
                let value = userProfile[varName] || '';
                const div = document.createElement('div');
                div.className = 'mb-2';
                div.innerHTML = `<label class="form-label">${varName.replace(/_/g, ' ')}</label><input type="text" class="form-control variable-input" id="var_${varName}" placeholder="Enter ${varName.replace(/_/g, ' ')}" value="${value}"${isChatStarted?' disabled':''}>`;
                container.appendChild(div);
            }
        }
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
        
        if (varName && input.value.trim()) {
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

// Automatic Token Management
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
		const response = await fetch('/api/chat/start', {
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

	// Collect all variables from form inputs
	const variables = collectVariables();

	isProcessing = true;
	isChatStarted = true;
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
			variables: variables
		};

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
		
		// Re-enable inputs on error
		isChatStarted = false;
		enableVariableInputs();
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
	isChatStarted = false;
	
	// Re-enable all inputs
	enableVariableInputs();
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
    // First check if user is authenticated
    checkAuthentication().then(isAuth => {
        if (isAuth) {
            initializeToken();
        } else {
            addMessage('❌ You must be logged in to use this feature', 'error');
            addMessage('🔐 Please log in and refresh this page', 'system');
        }
    });

    // Fetch user profile (for defaults)
    fetch('/api/user', { 
        credentials: 'same-origin',
        headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
        .then(res => {
            if (!res.ok) {
                throw new Error(`HTTP ${res.status}: Failed to fetch user profile`);
            }
            return res.json();
        })
        .then(user => {
            userProfile = user || {};
            console.log('User profile loaded:', userProfile);
            // Fetch all profile fields
            fetch('/api/profile-fields', { 
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
                .then(res => {
                    if (!res.ok) {
                        throw new Error(`HTTP ${res.status}: Failed to fetch profile fields`);
                    }
                    return res.json();
                })
                .then(profileFields => {
                    allProfileFields = profileFields;
                    console.log('Profile fields loaded:', profileFields);
                    // Fetch available journeys for the user
                    fetch('/api/journeys-available', { 
                        credentials: 'same-origin',
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    })
                        .then(res => {
                            if (!res.ok) {
                                throw new Error(`HTTP ${res.status}: Failed to fetch journeys`);
                            }
                            return res.json();
                        })
                        .then(journeys => {
                            allJourneys = journeys;
                            console.log('Journeys loaded:', journeys);
                            const select = $('#journeyId');
                            select.empty();
                            select.append(new Option('Select a journey...', ''));
                            journeys.forEach(j => {
                                select.append(new Option(j.title, j.id));
                            });
                            select.select2({ placeholder: 'Select a journey', allowClear: true });
                            
                            // Check for journey_id in URL and preselect
                            const urlParams = new URLSearchParams(window.location.search);
                            const preselect = urlParams.get('journey_id');
                            if (preselect && journeys.find(j => j.id == preselect)) {
                                select.val(preselect).trigger('change');
                                renderProfileFieldsAndVariables(preselect);
                            }
                            
                            select.on('change', function() {
                                if (this.value) {
                                    renderProfileFieldsAndVariables(this.value);
                                } else {
                                    document.getElementById('profileFieldsContainer').innerHTML = '';
                                }
                            });
                        })
                        .catch(error => {
                            console.error('Error fetching journeys:', error);
                            addMessage('❌ Failed to load journeys: ' + error.message, 'error');
                        });
                })
                .catch(error => {
                    console.error('Error fetching profile fields:', error);
                    addMessage('❌ Failed to load profile fields: ' + error.message, 'error');
                });
        })
        .catch(error => {
            console.error('Error fetching user profile:', error);
            addMessage('❌ Failed to load user profile: ' + error.message, 'error');
        });
});
</script>
@endsection
