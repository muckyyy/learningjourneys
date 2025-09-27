<?php

namespace App\Services;

use WebSocket\Client;
use App\Events\VoiceChunk;
use App\Models\JourneyStepResponse;
use App\Services\PromptBuilderService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class OpenAIRealtimeService
{
    protected Client $ws;
    public $attemptid;
    protected string $textBuffer = ''; // Buffer for collecting text
    protected string $audioBuffer = ''; // Buffer for collecting audio chunks
    protected string $input;
    protected string $prompt;
    protected string $jsrid;
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
            broadcast(new VoiceChunk('Session initialized', 'text', $this->attemptid, 0));
            
        } catch (\Exception $e) {
            Log::error('Session init failed: ' . $e->getMessage());
            broadcast(new VoiceChunk('Session init failed: ' . $e->getMessage(), 'error', $this->attemptid, 0));
        }
    }

    public function sendUserMessage(string $text): void
    {
        try {
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

                //Log::info('OpenAI Response Type: ' . $data['type']);
                
                
                switch ($data['type']) {
                    
                    case 'response.audio_transcript.delta':
                        $text = $data['delta'] ?? '';
                        if ($text) {
                            // Add to buffer instead of sending immediately
                            $this->textBuffer .= $text;
                            broadcast(new VoiceChunk($this->textBuffer , 'text', $this->attemptid,$chunkindex++));
                            Log::info('Data type: '. $this->textBuffer);
                        }
                        //dd($data);
                        break;

                    case 'response.text.done':
                        // Send the complete buffered text when text is done
                        if (!empty($this->textBuffer)) {
                            //broadcast(new VoiceChunk('Sending buffered text (' . strlen($this->textBuffer) . ' chars)', 'text', $this->attemptid));
                            //broadcast(new VoiceChunk($this->textBuffer, 'text', $this->attemptid));
                            $onText($this->textBuffer);
                            //$this->textBuffer = ''; // Clear buffer after sending
                        }
                        break;

                    case 'response.audio.delta':
                        $audio = $data['delta'] ?? null;
                        if ($audio) {
                            // Accumulate audio chunks
                            $this->audioBuffer .= base64_decode($audio);
                            broadcast(new VoiceChunk($audio, 'audio', $this->attemptid,$audiochunkindex++));
                            $onAudio($audio);
                        }
                        break;

                    case 'response.done':
                        // Final cleanup - send any remaining buffered text
                        
                        if (!empty($this->textBuffer)) {
                            //broadcast(new VoiceChunk('Final text send: ' . $this->textBuffer, 'text', $this->attemptid));
                            //$onText($this->textBuffer);
                            //$this->textBuffer = '';
                        }
                        
                        // Save text and audio when streaming is complete
                        $this->saveTextResponse();
                        $this->saveAudioResponse();
                        
                        //broadcast(new VoiceChunk('Response completed', 'text', $this->attemptid));
                        return;

                    case 'error':
                        $error = $data['error']['message'] ?? 'Unknown error';
                        broadcast(new VoiceChunk('OpenAI Error: ' . $error, 'text', $this->attemptid, 0));
                        Log::error('OpenAI Error: ' . $error);
                        return;

                    default:
                        $eventInfo = "Event: {$data['type']}";
                        if (isset($data['event_id'])) {
                            $eventInfo .= " (ID: {$data['event_id']})";
                        }
                        //Log::info('Event: ',$data);
                        //broadcast(new VoiceChunk($eventInfo, 'text', $this->attemptid));
                        break;
                }
                
                usleep(10000);
            }
            Log::info('OpenAI full text response: ' . $this->textBuffer);
            if ($iterations >= $maxIterations) {
                broadcast(new VoiceChunk('Stream ended due to iteration limit', 'text', $this->attemptid, 0));
                // Send any remaining buffered text
                if (!empty($this->textBuffer)) {
                    $onText($this->textBuffer);
                }
                
                // Save text and audio when streaming ends due to iteration limit
                $this->saveTextResponse();
                $this->saveAudioResponse();
            }
          
        } catch (\Exception $e) {
            Log::error('Stream response failed: ' . $e->getMessage());
            broadcast(new VoiceChunk('Stream failed: ' . $e->getMessage(), 'text', $this->attemptid, 0));
        } finally {
            
            $this->closeConnection();
        }
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
