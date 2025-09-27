<?php

namespace App\Services;

use WebSocket\Client;
use App\Events\VoiceChunk;
use App\Models\JourneyStepResponse;
use App\Services\PromptBuilderService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Models\JourneyPromptLog;

class OpenAIRealtimeService
{
    protected Client $ws;
    public $attemptid;
    protected string $textBuffer = ''; // Buffer for collecting text
    protected string $audioBuffer = ''; // Buffer for collecting audio chunks
    protected string $input;
    protected string $prompt;
    protected string $jsrid;
    protected ?int $currentLogId = null;           // Active JourneyPromptLog row id for current user message
    protected float $currentLogStartAt = 0.0;      // Timestamp to compute processing_time_ms
    // track tokens for current exchange
    protected ?int $requestTokens = null;
    protected ?int $responseTokens = null;

    public function __construct($attemptid,$input,$prompt,$jsrid)
    {
        $this->attemptid = $attemptid;
        $this->textBuffer = ''; // Initialize buffer
        $this->input = $input;
        $this->prompt = $prompt;
        $this->jsrid = $jsrid;

        try {
            $this->ws = new Client(
                "wss://api.openai.com/v1/realtime?model=gpt-realtime",
                [
                    'headers' => [
                        "Authorization" => "Bearer " . env('OPENAI_API_KEY'),
                        "OpenAI-Beta" => "realtime=v1",
                    ],
                    'timeout' => 60,
                ]
            );
            $this->initSession();
            
            
        } catch (\Exception $e) {
            Log::error('OpenAI WebSocket connection failed: ' . $e->getMessage());
            
            throw $e;
        }
    }

    protected function initSession(): void
    {
        try {
            $promptService = new PromptBuilderService();
            $prompt = $promptService->getChatPrompt($this->attemptid);
            $history = $promptService->getMessagesHistory($this->attemptid,'chat',true);
            if ($history){
                $chatHistoryPrompt = '##Chat history: \n\n';
                foreach ($history as $item) {
                    $chatHistoryPrompt .= ($item['role'] == 'user' ? "User: " : "AI: ") . $item['content'] . "\n";
                }
            }
            $sessionUpdate = [
                "type" => "session.update",
                "session" => [
                    "modalities" => ["text", "audio"],
                    "voice" => "alloy",
                    "instructions" => $prompt,
                    "max_response_output_tokens" => 4096,
                    "turn_detection" => [
                        "type" => "server_vad",
                        "threshold" => 0.5,
                        "prefix_padding_ms" => 300,
                        "silence_duration_ms" => 200
                    ],
                    // Ensure proper PCM16 format with correct sample rate
                    "output_audio_format" => "pcm16"
                ]
            ];
            
            $this->ws->send(json_encode($sessionUpdate));

            // Log system instructions/session init prompt
            try {
                JourneyPromptLog::create([
                    'journey_attempt_id' => $this->attemptid,
                    'journey_step_response_id' => $this->jsrid,
                    'prompt' => $prompt . (isset($chatHistoryPrompt) ? "\n\n" . $chatHistoryPrompt : ''),
                    'response' => 'session.update sent',
                    'ai_model' => 'gpt-realtime',
                    'metadata' => [
                        'type' => 'session.update',
                        'modalities' => $sessionUpdate['session']['modalities'] ?? null,
                        'voice' => $sessionUpdate['session']['voice'] ?? null,
                        'turn_detection' => $sessionUpdate['session']['turn_detection'] ?? null,
                    ],
                ]);
            } catch (\Exception $e) {
                Log::warning('Prompt logging (session.init) failed: ' . $e->getMessage());
            }

            broadcast(new VoiceChunk('Session initialized', 'text', $this->attemptid, 0));
            
        } catch (\Exception $e) {
            Log::error('Session init failed: ' . $e->getMessage());
            broadcast(new VoiceChunk('Session init failed: ' . $e->getMessage(), 'error', $this->attemptid, 0));
        }
    }

    public function sendUserMessage(string $text): void
    {
        try {
            // Start timing for processing_time_ms
            $this->currentLogStartAt = microtime(true);
            // reset token counters for this turn
            $this->requestTokens = null;
            $this->responseTokens = null;

            // Create log row for this user message (will be finalized when response completes)
            try {
                $log = JourneyPromptLog::create([
                    'journey_attempt_id' => $this->attemptid,
                    'journey_step_response_id' => $this->jsrid,
                    'prompt' => $text,
                    'response' => 'pending',
                    'ai_model' => 'gpt-realtime',
                    'metadata' => ['type' => 'user.message'],
                ]);
                $this->currentLogId = $log->id;
            } catch (\Exception $e) {
                Log::warning('Prompt logging (user.message create) failed: ' . $e->getMessage());
            }

            // First create the conversation item
            $createItem = [
                "type" => "conversation.item.create",
                "item" => [
                    "type" => "message",
                    "role" => "user",
                    "content" => [
                        ["type" => "input_text", "text" => $text]
                    ]
                ]
            ];
            
            $this->ws->send(json_encode($createItem));
            
            // Then trigger response generation
            $createResponse = [
                "type" => "response.create"
            ];
            
            $this->ws->send(json_encode($createResponse));
            broadcast(new VoiceChunk('Sending complete' , 'complete', $this->attemptid, 0));
            
        } catch (\Exception $e) {
            Log::error('Send message failed: ' . $e->getMessage());
            broadcast(new VoiceChunk('Send message failed: ' . $e->getMessage(), 'error', $this->attemptid, 0));
        }
    }

    public function streamResponse(callable $onText, callable $onAudio): void
    {
        try {
            $maxIterations = 100000;
            $iterations = 0;
            $this->textBuffer = ''; // Reset buffer at start
            $this->audioBuffer = ''; // Reset audio buffer at start
            $chunkindex = 0;
            $audiochunkindex = 0;
            while ($iterations < $maxIterations) {
                $iterations++;
                
                $response = $this->ws->receive();
                
                if (!$response) {
                    broadcast(new VoiceChunk('No response received, ending stream', 'text', $this->attemptid, 0));
                    break;
                }
                
                $data = json_decode($response, true);
                
                if (!$data || !isset($data['type'])) {
                    broadcast(new VoiceChunk('Invalid response format', 'text', $this->attemptid, 0));
                    continue;
                }

                switch ($data['type']) {
                    case 'response.audio_transcript.delta':
                        $text = $data['delta'] ?? '';
                        if ($text) {
                            $this->textBuffer .= $text;
                            broadcast(new VoiceChunk($this->textBuffer , 'text', $this->attemptid,$chunkindex++));
                            Log::info('Data type: '. $this->textBuffer);
                        }
                        break;

                    case 'response.text.done':
                        if (!empty($this->textBuffer)) {
                            $onText($this->textBuffer);
                        }
                        break;

                    case 'response.audio.delta':
                        $audio = $data['delta'] ?? null;
                        if ($audio) {
                            $this->audioBuffer .= base64_decode($audio);
                            broadcast(new VoiceChunk($audio, 'audio', $this->attemptid,$audiochunkindex++));
                            $onAudio($audio);
                        }
                        break;

                    case 'response.done':
                        // extract tokens from the final event if present
                        $usage = $this->extractTokenUsage($data);
                        $this->requestTokens = $usage['request_tokens'];
                        $this->responseTokens = $usage['response_tokens'];

                        $this->finalizePromptLog();
                        $this->saveTextResponse();
                        $this->saveAudioResponse();
                        return;

                    case 'error':
                        $error = $data['error']['message'] ?? 'Unknown error';
                        $usage = $this->extractTokenUsage($data);
                        $this->requestTokens = $usage['request_tokens'];
                        $this->responseTokens = $usage['response_tokens'];

                        $this->finalizePromptLog($error);
                        broadcast(new VoiceChunk('OpenAI Error: ' . $error, 'text', $this->attemptid, 0));
                        Log::error('OpenAI Error: ' . $error);
                        return;

                    default:
                        // ...existing code...
                        break;
                }
                
                usleep(10000);
            }
            Log::info('OpenAI full text response: ' . $this->textBuffer);
            if ($iterations >= $maxIterations) {
                // keep any tokens previously captured (if any)
                $this->finalizePromptLog('iteration_limit_reached');
                // Save text and audio when streaming ends due to iteration limit
                $this->saveTextResponse();
                $this->saveAudioResponse();
            }
        } catch (\Exception $e) {
            $this->finalizePromptLog($e->getMessage());
            Log::error('Stream response failed: ' . $e->getMessage());
            broadcast(new VoiceChunk('Stream failed: ' . $e->getMessage(), 'text', $this->attemptid, 0));
        } finally {
            $this->closeConnection();
        }
    }

    // Store final response, optional error, and processing time into the active JourneyPromptLog
    protected function finalizePromptLog(?string $errorMessage = null): void
    {
        if (!$this->currentLogId) {
            return;
        }
        try {
            $log = JourneyPromptLog::find($this->currentLogId);
            if ($log) {
                $meta = is_array($log->metadata) ? $log->metadata : [];
                if ($errorMessage) {
                    $meta['error'] = $errorMessage;
                }
                // include token info in metadata for traceability
                if (!isset($meta['token_info'])) {
                    $meta['token_info'] = [];
                }
                $meta['token_info']['request_tokens'] = $this->requestTokens;
                $meta['token_info']['response_tokens'] = $this->responseTokens;

                $log->response = $errorMessage ? '' : ($this->textBuffer ?? '');
                if ($this->currentLogStartAt > 0) {
                    $log->processing_time_ms = round((microtime(true) - $this->currentLogStartAt) * 1000, 2);
                }
                // persist token columns
                $log->request_tokens = $this->requestTokens;
                $log->response_tokens = $this->responseTokens;
                $total = ($this->requestTokens ?? 0) + ($this->responseTokens ?? 0);
                $log->tokens_used = $total > 0 ? $total : null;

                $log->metadata = $meta;
                $log->save();
            }
        } catch (\Exception $e) {
            Log::warning('Failed to finalize JourneyPromptLog: ' . $e->getMessage());
        } finally {
            $this->currentLogId = null;
            $this->currentLogStartAt = 0.0;
            $this->requestTokens = null;
            $this->responseTokens = null;
        }
    }
    
    /**
     * Best-effort extraction of token usage from realtime payloads.
     * Looks for common shapes:
     * - response.usage.input_tokens/output_tokens
     * - response.usage.prompt_tokens/completion_tokens
     * - response.input_token_count/output_token_count
     * - usage.input_tokens/output_tokens (top-level)
     */
    protected function extractTokenUsage(array $data): array
    {
        $get = function (array $src, array $path) {
            $cur = $src;
            foreach ($path as $key) {
                if (!is_array($cur) || !array_key_exists($key, $cur)) {
                    return null;
                }
                $cur = $cur[$key];
            }
            return is_numeric($cur) ? (int)$cur : null;
        };

        $req = $get($data, ['response','usage','input_tokens'])
            ?? $get($data, ['response','usage','prompt_tokens'])
            ?? $get($data, ['usage','input_tokens'])
            ?? $get($data, ['usage','prompt_tokens'])
            ?? $get($data, ['response','input_token_count'])
            ?? $get($data, ['input_token_count'])
            ?? $get($data, ['response','metrics','input_token_count']);

        $resp = $get($data, ['response','usage','output_tokens'])
            ?? $get($data, ['response','usage','completion_tokens'])
            ?? $get($data, ['usage','output_tokens'])
            ?? $get($data, ['usage','completion_tokens'])
            ?? $get($data, ['response','output_token_count'])
            ?? $get($data, ['output_token_count'])
            ?? $get($data, ['response','metrics','output_token_count']);

        return [
            'request_tokens' => $req,
            'response_tokens' => $resp,
        ];
    }

    protected function closeConnection(): void
    {
        try {
            if ($this->ws) {
                $this->ws->close();
                broadcast(new VoiceChunk('Connection closed', 'text', $this->attemptid, 0));
            }
        } catch (\Exception $e) {
            Log::error('Error closing WebSocket: ' . $e->getMessage());
        }
    }
    
    protected function saveTextResponse(): void
    {
        try {
            if (!empty($this->textBuffer) && $this->jsrid) {
                $journeyStepResponse = JourneyStepResponse::find($this->jsrid);
                if ($journeyStepResponse) {
                    $journeyStepResponse->ai_response = $this->textBuffer;
                    $journeyStepResponse->save();
                    Log::info('Text response saved to journey_step_response ID: ' . $this->jsrid);
                } else {
                    Log::error('JourneyStepResponse not found with ID: ' . $this->jsrid);
                }
            }
        } catch (\Exception $e) {
            Log::error('Failed to save text response: ' . $e->getMessage());
        }
    }
    
    protected function saveAudioResponse(): void
    {
        try {
            if (!empty($this->audioBuffer) && $this->attemptid && $this->jsrid) {
                $directory = "ai_audios/{$this->attemptid}/{$this->jsrid}";
                $filename = "ai_audio.mp3";
                $filepath = "{$directory}/{$filename}";
                
                // Create directory if it doesn't exist
                Storage::makeDirectory($directory);
                
                // Create MP3 file from PCM data
                $mp3Data = $this->createMp3File($this->audioBuffer);
                
                // Save the MP3 file
                Storage::put($filepath, $mp3Data);
                
                Log::info('Audio response saved to: ' . $filepath);
            }
        } catch (\Exception $e) {
            Log::error('Failed to save audio response: ' . $e->getMessage());
        }
    }
    
    protected function createMp3File(string $pcmData): string
    {
        $sampleRate = 24000; // OpenAI Realtime uses 24kHz
        $bitsPerSample = 16; // PCM16
        $channels = 1; // Mono
        
        // Create a temporary WAV file first (FFmpeg needs a proper input format)
        $tempWavData = $this->createTempWavFile($pcmData, $sampleRate, $bitsPerSample, $channels);
        
        // Create temporary files
        $tempWavPath = storage_path('app/temp_audio_' . uniqid() . '.wav');
        $tempMp3Path = storage_path('app/temp_audio_' . uniqid() . '.mp3');
        
        try {
            // Write temporary WAV file
            file_put_contents($tempWavPath, $tempWavData);
            
            // Convert WAV to MP3 using FFmpeg
            $ffmpegCommand = "ffmpeg -y -i \"{$tempWavPath}\" -codec:a libmp3lame -b:a 128k \"{$tempMp3Path}\"";
            
            // Execute FFmpeg command
            $output = [];
            $returnCode = 0;
            exec($ffmpegCommand . ' 2>&1', $output, $returnCode);
            
            if ($returnCode !== 0) {
                // Fallback: if FFmpeg fails, return the WAV data instead
                Log::warning('FFmpeg conversion failed, falling back to WAV format. Output: ' . implode("\n", $output));
                return $tempWavData;
            }
            
            // Read the generated MP3 file
            if (!file_exists($tempMp3Path)) {
                Log::warning('MP3 file was not created, falling back to WAV format');
                return $tempWavData;
            }
            
            $mp3Data = file_get_contents($tempMp3Path);
            
            return $mp3Data;
            
        } catch (\Exception $e) {
            Log::error('MP3 conversion error: ' . $e->getMessage());
            // Fallback to WAV if MP3 conversion fails
            return $tempWavData;
            
        } finally {
            // Clean up temporary files
            if (file_exists($tempWavPath)) {
                unlink($tempWavPath);
            }
            if (file_exists($tempMp3Path)) {
                unlink($tempMp3Path);
            }
        }
    }
    
    protected function createTempWavFile(string $pcmData, int $sampleRate, int $bitsPerSample, int $channels): string
    {
        $dataSize = strlen($pcmData);
        $fileSize = $dataSize + 36;
        
        // WAV header
        $header = pack('V', 0x46464952); // "RIFF"
        $header .= pack('V', $fileSize); // File size - 8
        $header .= pack('V', 0x45564157); // "WAVE"
        
        // Format chunk
        $header .= pack('V', 0x20746d66); // "fmt "
        $header .= pack('V', 16); // Chunk size
        $header .= pack('v', 1); // Audio format (PCM)
        $header .= pack('v', $channels); // Number of channels
        $header .= pack('V', $sampleRate); // Sample rate
        $header .= pack('V', $sampleRate * $channels * $bitsPerSample / 8); // Byte rate
        $header .= pack('v', $channels * $bitsPerSample / 8); // Block align
        $header .= pack('v', $bitsPerSample); // Bits per sample
        
        // Data chunk
        $header .= pack('V', 0x61746164); // "data"
        $header .= pack('V', $dataSize); // Data size
        
        return $header . $pcmData;
    }
}
