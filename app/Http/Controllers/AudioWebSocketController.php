<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\AudioRecording;
use App\Models\JourneyAttempt;
use App\Models\JourneyStep;
use App\Events\AudioChunkReceived;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use OpenAI;

class AudioWebSocketController extends Controller
{
    /**
     * Start a new audio recording session
     */
    public function startRecording(Request $request)
    {
        $request->validate([
            'journey_attempt_id' => 'required|exists:journey_attempts,id',
            'journey_step_id' => 'nullable|exists:journey_steps,id',
            'session_id' => 'required|string'
        ]);

        $journeyAttempt = JourneyAttempt::findOrFail($request->journey_attempt_id);
        
        // Check if user owns this attempt
        if ($journeyAttempt->user_id !== Auth::id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $recording = AudioRecording::create([
            'user_id' => Auth::id(),
            'journey_attempt_id' => $request->journey_attempt_id,
            'journey_step_id' => $request->journey_step_id,
            'session_id' => $request->session_id,
            'status' => 'recording'
        ]);

        return response()->json([
            'success' => true,
            'recording_id' => $recording->id,
            'session_id' => $recording->session_id
        ]);
    }

    /**
     * Process uploaded audio chunk
     */
    public function processAudioChunk(Request $request)
    {
        $request->validate([
            'session_id' => 'required|string',
            'audio_data' => 'required|string', // base64 encoded audio
            'chunk_number' => 'integer|min:0',
            'is_final' => 'boolean'
        ]);

        $recording = AudioRecording::where('session_id', $request->session_id)
            ->where('user_id', Auth::id())
            ->first();

        if (!$recording) {
            return response()->json(['error' => 'Recording session not found'], 404);
        }

        try {
            // Decode base64 audio data
            $audioData = base64_decode($request->audio_data);
            
            if (!$audioData) {
                return response()->json(['error' => 'Invalid audio data'], 400);
            }

            Log::info('Processing audio chunk', [
                'session_id' => $request->session_id,
                'chunk_number' => $request->chunk_number,
                'data_size' => strlen($audioData),
                'is_final' => $request->is_final
            ]);

            // Create directory if it doesn't exist
            $stepId = $recording->journey_step_id ?? 'general';
            $audioDir = 'audio_recordings/' . $stepId;
            Storage::makeDirectory($audioDir);

            // Save audio chunk
            $filename = $recording->session_id . '_chunk_' . ($request->chunk_number ?? 0) . '.webm';
            $filepath = $audioDir . '/' . $filename;
            
            Storage::put($filepath, $audioData);

            // Update recording metadata
            $metadata = $recording->metadata ?? [];
            $metadata['chunks'][] = [
                'filepath' => $filepath,
                'chunk_number' => $request->chunk_number ?? 0,
                'size' => strlen($audioData),
                'timestamp' => now()->toISOString()
            ];
            
            $recording->update([
                'metadata' => $metadata,
                'status' => $request->is_final ? 'processing' : 'recording'
            ]);

            // Broadcast audio chunk received event to WebSocket
            broadcast(new AudioChunkReceived($request->session_id, [
                'chunk_number' => $request->chunk_number ?? 0,
                'size' => strlen($audioData),
                'status' => $request->is_final ? 'processing' : 'recording',
                'is_final' => $request->is_final ?? false,
                'timestamp' => now()->toISOString()
            ]));

            // If this is the final chunk, just mark it but don't process yet
            // Processing will be handled by completeRecording method
            if ($request->is_final) {
                Log::info('Final chunk received, will process in completeRecording', [
                    'recording_id' => $recording->id,
                    'session_id' => $request->session_id
                ]);
            }

            return response()->json([
                'success' => true,
                'chunk_saved' => true,
                'recording_id' => $recording->id
            ]);

        } catch (\Exception $e) {
            Log::error('Audio chunk processing error: ' . $e->getMessage());
            
            $recording->update(['status' => 'failed']);
            
            return response()->json([
                'error' => 'Failed to process audio chunk',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Complete recording and get transcription
     */
    public function completeRecording(Request $request)
    {
        $request->validate([
            'session_id' => 'required|string'
        ]);

        $recording = AudioRecording::where('session_id', $request->session_id)
            ->where('user_id', Auth::id())
            ->first();

        if (!$recording) {
            Log::error('Recording session not found', ['session_id' => $request->session_id, 'user_id' => Auth::id()]);
            return response()->json(['error' => 'Recording session not found'], 404);
        }

        Log::info('Completing recording', ['recording_id' => $recording->id, 'session_id' => $request->session_id]);

        try {
            $this->processCompleteAudio($recording);
            
            return response()->json([
                'success' => true,
                'recording_id' => $recording->id,
                'status' => $recording->fresh()->status
            ]);

        } catch (\Exception $e) {
            Log::error('Recording completion error: ' . $e->getMessage(), [
                'recording_id' => $recording->id,
                'session_id' => $request->session_id,
                'exception' => $e
            ]);
            
            $recording->update(['status' => 'failed']);
            
            return response()->json([
                'error' => 'Failed to complete recording',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Simple audio transcription - upload complete audio file
     */
    public function transcribeAudio(Request $request)
    {
        $request->validate([
            'audio' => 'required|file|mimes:webm,wav,mp3,m4a|max:25600', // 25MB max
            'session_id' => 'required|string',
            'journey_attempt_id' => 'required|exists:journey_attempts,id',
            'journey_step_id' => 'nullable|exists:journey_steps,id'
        ]);

        $journeyAttempt = JourneyAttempt::findOrFail($request->journey_attempt_id);
        
        // Check if user owns this attempt
        if ($journeyAttempt->user_id !== Auth::id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        try {
            // Create audio recording record
            $recording = AudioRecording::create([
                'user_id' => Auth::id(),
                'journey_attempt_id' => $request->journey_attempt_id,
                'journey_step_id' => $request->journey_step_id,
                'session_id' => $request->session_id,
                'status' => 'processing'
            ]);

            // Store uploaded file
            $audioFile = $request->file('audio');
            $stepId = $request->journey_step_id ?? 'general';
            $audioDir = 'audio_recordings/' . $stepId;
            $filename = $recording->session_id . '_complete.' . $audioFile->getClientOriginalExtension();
            $filepath = $audioFile->storeAs($audioDir, $filename);

            Log::info('Audio file uploaded', [
                'recording_id' => $recording->id,
                'file_path' => $filepath,
                'file_size' => $audioFile->getSize()
            ]);

            // Get transcription from OpenAI
            $transcription = $this->transcribeWithOpenAI($filepath);

            // Update recording with results
            $fileSizeBytes = $audioFile->getSize();
            $estimatedDurationSeconds = max(1, $fileSizeBytes / 4000);
            $estimatedCost = ($estimatedDurationSeconds / 60) * 0.006;

            $recording->update([
                'file_path' => $filepath,
                'transcription' => $transcription,
                'tokens_used' => 0,
                'processing_cost' => $estimatedCost,
                'duration_seconds' => (int) $estimatedDurationSeconds,
                'status' => 'completed'
            ]);

            Log::info('Simple transcription completed', [
                'recording_id' => $recording->id,
                'transcription_length' => strlen($transcription),
                'duration_seconds' => $estimatedDurationSeconds
            ]);

            return response()->json([
                'success' => true,
                'transcription' => $transcription,
                'recording_id' => $recording->id,
                'session_id' => $recording->session_id
            ]);

        } catch (\Exception $e) {
            Log::error('Simple transcription error: ' . $e->getMessage(), [
                'session_id' => $request->session_id,
                'user_id' => Auth::id(),
                'exception' => $e
            ]);

            if (isset($recording)) {
                $recording->update(['status' => 'failed']);
            }

            return response()->json([
                'success' => false,
                'error' => 'Failed to transcribe audio: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Transcribe audio file using OpenAI Whisper
     */
    private function transcribeWithOpenAI($filepath)
    {
        $fullPath = storage_path('app/' . $filepath);
        
        if (!file_exists($fullPath)) {
            throw new \Exception('Audio file not found');
        }

        Log::info('Sending to OpenAI Whisper', [
            'file_path' => $fullPath,
            'file_size' => filesize($fullPath)
        ]);

        try {
            $httpClient = Http::withOptions([
                'verify' => config('openai.http_options.verify', false),
                'timeout' => 60,
            ]);

            $response = $httpClient->withHeaders([
                'Authorization' => 'Bearer ' . config('openai.api_key'),
            ])->attach(
                'file', file_get_contents($fullPath), basename($fullPath)
            )->post('https://api.openai.com/v1/audio/transcriptions', [
                'model' => 'whisper-1',
                'response_format' => 'json',
                'temperature' => 0.0,
                'language' => 'en',
            ]);

            if (!$response->successful()) {
                Log::error('OpenAI API error', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                throw new \Exception('OpenAI API request failed: ' . $response->body());
            }

            $result = $response->json();
            $transcription = $result['text'] ?? '';
            
            // Basic validation
            if (strlen(trim($transcription)) < 3) {
                throw new \Exception('Transcription too short or invalid');
            }

            return $transcription;

        } catch (\Exception $e) {
            Log::error('OpenAI transcription error', [
                'file_path' => $fullPath,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Get transcription result
     */
    public function getTranscription(Request $request, $sessionId)
    {
        $recording = AudioRecording::where('session_id', $sessionId)
            ->where('user_id', Auth::id())
            ->first();

        if (!$recording) {
            return response()->json(['error' => 'Recording not found'], 404);
        }

        return response()->json([
            'recording_id' => $recording->id,
            'session_id' => $recording->session_id,
            'status' => $recording->status,
            'transcription' => $recording->transcription,
            'tokens_used' => $recording->tokens_used,
            'duration_seconds' => $recording->duration_seconds
        ]);
    }

    /**
     * Process complete audio file and get transcription
     */
    private function processCompleteAudio(AudioRecording $recording)
    {
        Log::info('Starting audio processing', ['recording_id' => $recording->id]);
        
        $metadata = $recording->metadata ?? [];
        $chunks = $metadata['chunks'] ?? [];

        Log::info('Audio chunks found', ['recording_id' => $recording->id, 'chunk_count' => count($chunks)]);

        if (empty($chunks)) {
            throw new \Exception('No audio chunks found');
        }

        // Combine all chunks into a single file
        $combinedAudio = '';
        foreach ($chunks as $chunk) {
            Log::info('Checking chunk file', [
                'recording_id' => $recording->id,
                'filepath' => $chunk['filepath'],
                'exists' => Storage::exists($chunk['filepath']),
                'full_path' => storage_path('app/' . $chunk['filepath'])
            ]);
            
            if (Storage::exists($chunk['filepath'])) {
                $chunkData = Storage::get($chunk['filepath']);
                $combinedAudio .= $chunkData;
                Log::info('Combined chunk', [
                    'recording_id' => $recording->id, 
                    'chunk_number' => $chunk['chunk_number'],
                    'chunk_size' => strlen($chunkData)
                ]);
            } else {
                Log::warning('Chunk file not found', [
                    'recording_id' => $recording->id,
                    'filepath' => $chunk['filepath'],
                    'full_path' => storage_path('app/' . $chunk['filepath'])
                ]);
            }
        }

        if (empty($combinedAudio)) {
            throw new \Exception('No audio data to process');
        }

        Log::info('Combined audio ready', [
            'recording_id' => $recording->id,
            'total_size' => strlen($combinedAudio)
        ]);

        // Save combined audio file
        $stepId = $recording->journey_step_id ?? 'general';
        $audioDir = 'audio_recordings/' . $stepId;
        $combinedFilename = $recording->session_id . '_complete.webm';
        $combinedFilepath = $audioDir . '/' . $combinedFilename;
        
        Storage::put($combinedFilepath, $combinedAudio);

        // Get the full file path for OpenAI
        $tempPath = storage_path('app/' . $combinedFilepath);
        
        Log::info('Sending to OpenAI', [
            'recording_id' => $recording->id,
            'file_path' => $tempPath,
            'file_exists' => file_exists($tempPath)
        ]);

        try {
            // Use HTTP client for audio transcription with SSL verification disabled for local development
            $httpClient = Http::withOptions([
                'verify' => config('openai.http_options.verify', false),
                'timeout' => 60, // Longer timeout for audio processing
            ]);

            $response = $httpClient->withHeaders([
                'Authorization' => 'Bearer ' . config('openai.api_key'),
            ])->attach(
                'file', file_get_contents($tempPath), basename($tempPath)
            )->post('https://api.openai.com/v1/audio/transcriptions', [
                'model' => 'whisper-1',
                'response_format' => 'json',
                'temperature' => 0.0,
                'language' => 'en',
            ]);

            if (!$response->successful()) {
                Log::error('OpenAI API error', [
                    'recording_id' => $recording->id,
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                throw new \Exception('OpenAI API request failed: ' . $response->body());
            }

            $result = $response->json();
            $transcription = $result['text'] ?? '';
            
            Log::info('OpenAI response received', [
                'recording_id' => $recording->id,
                'transcription_length' => strlen($transcription),
                'raw_result' => $result
            ]);
            
            // Basic validation - reject very short transcriptions as likely hallucinations
            if (strlen(trim($transcription)) < 3) {
                throw new \Exception('Transcription too short or invalid');
            }

            // Calculate approximate duration and cost
            $fileSizeBytes = strlen($combinedAudio);
            $estimatedDurationSeconds = max(1, $fileSizeBytes / 4000); // Rough estimate, minimum 1 second
            $estimatedCost = ($estimatedDurationSeconds / 60) * 0.006; // $0.006 per minute for Whisper

            $recording->update([
                'file_path' => $combinedFilepath,
                'transcription' => $transcription,
                'tokens_used' => 0, // Whisper doesn't use tokens like chat models
                'processing_cost' => $estimatedCost,
                'duration_seconds' => (int) $estimatedDurationSeconds,
                'status' => 'completed'
            ]);

            // Clean up chunk files to save storage
            foreach ($chunks as $chunk) {
                if (Storage::exists($chunk['filepath'])) {
                    Storage::delete($chunk['filepath']);
                }
            }

            Log::info('Audio transcription completed', [
                'recording_id' => $recording->id,
                'transcription_length' => strlen($transcription),
                'duration_seconds' => $estimatedDurationSeconds,
                'cost' => $estimatedCost
            ]);

        } catch (\Exception $e) {
            Log::error('OpenAI transcription error', [
                'recording_id' => $recording->id,
                'error' => $e->getMessage(),
                'file_size' => strlen($combinedAudio)
            ]);
            
            $recording->update(['status' => 'failed']);
            throw $e;
        }
    }
}
