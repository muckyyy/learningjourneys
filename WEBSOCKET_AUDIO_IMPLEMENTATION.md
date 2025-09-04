# Laravel WebSocket Audio Recording Implementation

## Overview
This implementation replaces the standalone Node.js WebSocket server with a Laravel-based solution for real-time audio recording and transcription in the preview chat interface.

## Components Implemented

### 1. Database Schema
- **AudioRecording Model**: Stores audio recordings with metadata
- **Migration**: Creates `audio_recordings` table with fields for:
  - User ID and Journey Attempt tracking
  - Session ID for WebSocket correlation
  - File path, transcription, tokens used, costs
  - Processing status and metadata

### 2. Backend API (AudioWebSocketController)
- **POST /api/audio/start-recording**: Initialize recording session
- **POST /api/audio/process-chunk**: Handle audio chunks during recording
- **POST /api/audio/complete**: Finalize recording and trigger processing
- **GET /api/audio/transcription/{sessionId}**: Poll for transcription results

### 3. WebSocket Integration
- Laravel WebSockets package configured for real-time communication
- Pusher JS client for frontend WebSocket connectivity
- Private channels for secure audio session communication

### 4. Frontend Implementation (preview-chat.blade.php)
- **Audio Recording Functions**:
  - `initAudioRecording()`: Set up MediaRecorder with optimal settings
  - `startAudioRecording()`: Begin recording session
  - `stopAudioRecording()`: End recording and trigger processing
  - `sendAudioChunk()`: Send audio data to server in real-time
  - `pollForTranscription()`: Check for transcription completion

### 5. User Interface Features
- **Microphone Button**: 🎤 icon that changes to 🔴 when recording
- **Recording Limits**: 30-second maximum with auto-stop
- **Real-time Feedback**: System messages showing recording status
- **Transcription Integration**: Results automatically populate input field

## How It Works

1. **User clicks mic button** → Requests microphone permission and starts recording
2. **Audio captured in 1-second chunks** → Sent to Laravel API as base64 data
3. **Server stores chunks** → Combines them into complete WebM audio file
4. **OpenAI Whisper transcription** → Processes complete audio file
5. **Results polled and displayed** → Transcription appears in chat input

## Key Features

### Security
- Sanctum authentication for all API endpoints
- CSRF protection on all requests
- User ownership validation for recording sessions

### Error Handling
- Comprehensive try-catch blocks
- User-friendly error messages
- Automatic cleanup on failures
- Graceful degradation when APIs fail

### Performance Optimizations
- Chunked audio processing for real-time feel
- Automatic cleanup of temporary files
- Efficient polling with timeout limits
- Cost tracking for OpenAI API usage

### Audio Quality
- 16kHz sample rate for optimal Whisper performance
- Opus codec in WebM container
- Echo cancellation and noise suppression
- Auto gain control for consistent volume

## Configuration Files Updated

- `.env`: Added Pusher WebSocket configuration
- `broadcasting.php`: Set default driver to pusher
- `websockets.php`: Laravel WebSockets configuration
- `routes/api.php`: Added audio recording endpoints
- `routes/channels.php`: Added private audio session channels

## Testing the Implementation

1. **Start WebSocket Server**: `php artisan websockets:serve`
2. **Access Preview Chat**: Navigate to `/preview-chat`
3. **Start Journey Session**: Begin a chat to get `currentAttemptId`
4. **Click Microphone**: Test recording functionality
5. **Verify Transcription**: Check that audio is converted to text

## Dependencies Added

- `beyondcode/laravel-websockets`: WebSocket server
- `pusher/pusher-php-server`: Pusher PHP SDK
- Pusher JS client library via CDN

## File Storage

Audio files are stored in `storage/app/audio_recordings/YYYY/MM/DD/` structure with automatic cleanup of chunk files after processing.

## Cost Tracking

The system tracks OpenAI Whisper API costs at $0.006 per minute of audio processed, stored in the `processing_cost` field for monitoring usage.

## Next Steps

1. Test with actual audio recording
2. Verify transcription accuracy
3. Add audio quality indicators
4. Implement recording visualization
5. Add support for different languages
6. Consider adding speaker diarization
