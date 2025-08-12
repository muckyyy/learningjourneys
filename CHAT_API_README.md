# Learning Journeys Chat API

This document describes the two main chat API endpoints for the Learning Journeys application.

## Overview

The Chat API provides streaming Server-Sent Events (SSE) responses for real-time AI interaction in learning journeys. Both endpoints require authentication via Laravel Sanctum.

## Authentication

All endpoints require Bearer token authentication using Laravel Sanctum:

```
Authorization: Bearer {your-sanctum-token}
```

To generate a token, users must access their profile settings and create a Personal Access Token.

## Endpoints

### 1. Start Chat Session

**Endpoint:** `POST /api/chat/start`

**Description:** Initializes a new chat session for a learning journey or continues an existing attempt.

**Headers:**
```
Content-Type: application/json
Authorization: Bearer {token}
Accept: text/event-stream
```

**Request Body:**
```json
{
    "journey_id": 1,
    "attempt_id": 123  // optional - for continuing existing attempt
}
```

**Response:** Server-Sent Events stream

**Response Events:**
- `metadata`: Initial session information
- `data`: AI response text chunks
- `error`: Error messages
- `done`: Stream completion indicator

**Example Response:**
```
event: metadata
data: {"step_id": 456, "timestamp": 1692123456, "formatted_date": "2024-08-12 10:30:56", "action": "start_chat"}

data: {"text": "Hello! Welcome to your learning journey. ", "step_id": 456}
data: {"text": "I'm here to guide you through this experience.", "step_id": 456}

event: done
data: [DONE]
```

### 2. Submit Chat Message

**Endpoint:** `POST /api/chat/submit`

**Description:** Submits user input and receives AI response for the current conversation.

**Headers:**
```
Content-Type: application/json
Authorization: Bearer {token}
Accept: text/event-stream
```

**Request Body:**
```json
{
    "attempt_id": 123,
    "user_input": "This is my question or response",
    "step_id": 456  // optional - specific step to respond to
}
```

**Response:** Server-Sent Events stream (same format as start_chat)

**Example Response:**
```
event: metadata
data: {"step_id": 456, "timestamp": 1692123456, "formatted_date": "2024-08-12 10:31:30", "action": "chat_submit"}

data: {"text": "That's a great question! ", "step_id": 456}
data: {"text": "Let me explain...", "step_id": 456}

event: done
data: [DONE]
```

## Error Handling

Both endpoints return appropriate HTTP status codes and error messages:

- `400 Bad Request`: Invalid request parameters
- `401 Unauthorized`: Missing or invalid authentication token
- `403 Forbidden`: User doesn't have access to the requested journey/attempt
- `404 Not Found`: Journey, attempt, or step not found
- `422 Unprocessable Entity`: Validation errors

**Error Response Example:**
```
event: error
data: {"message": "No steps found for this journey"}

event: done
data: [DONE]
```

## JavaScript Client Example

```javascript
async function startChat(journeyId, token) {
    const response = await fetch('/api/chat/start', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Authorization': `Bearer ${token}`,
            'Accept': 'text/event-stream'
        },
        body: JSON.stringify({ journey_id: journeyId })
    });

    const reader = response.body.getReader();
    const decoder = new TextDecoder();

    while (true) {
        const { done, value } = await reader.read();
        if (done) break;

        const chunk = decoder.decode(value);
        const lines = chunk.split('\n');

        for (const line of lines) {
            if (line.startsWith('data: ')) {
                const data = line.substring(6);
                if (data === '[DONE]') continue;
                
                try {
                    const parsed = JSON.parse(data);
                    if (parsed.text) {
                        console.log('AI Response:', parsed.text);
                    }
                } catch (e) {
                    console.error('Parse error:', e);
                }
            }
        }
    }
}
```

## Testing

A test interface is available at `/preview-chat` (in debug mode) that provides a web UI for testing both endpoints.

## Database Integration

Both endpoints create records in:
- `journey_attempts` - Track user's journey progress
- `journey_step_responses` - Store user inputs and AI responses
- `journey_debug` - Log detailed debugging information (if debug logging is enabled)

## AI Integration

The endpoints use OpenAI's streaming chat completions API to provide real-time responses. Configuration is managed through `config/openai.php`.

## Rate Limiting

API endpoints respect Laravel's default rate limiting configured in `config/sanctum.php` and route middleware.
