<?php

namespace App\Services;

use WebSocket\Client;
use App\Events\VoiceChunk;
use Illuminate\Support\Facades\Log;

class OpenAIRealtimeService
{
    protected Client $ws;
    public $attemptid;
    protected string $textBuffer = ''; // Buffer for collecting text
    protected string $input;
    protected string $prompt;
    public function __construct($attemptid,$input,$prompt)
    {
        $this->attemptid = $attemptid;
        $this->textBuffer = ''; // Initialize buffer
        $this->input = $input;
        $this->prompt = $prompt;

        try {
            broadcast(new VoiceChunk('Connecting to OpenAI...', 'text', $this->attemptid));
            
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
            Log::info($this->prompt);
            broadcast(new VoiceChunk('Connected to OpenAI successfully', 'text', $this->attemptid));
            
        } catch (\Exception $e) {
            Log::error('OpenAI WebSocket connection failed: ' . $e->getMessage());
            broadcast(new VoiceChunk('Failed to connect to OpenAI: ' . $e->getMessage(), 'error', $this->attemptid));
            throw $e;
        }
    }

    protected function initSession(): void
    {
        try {
            $sessionUpdate = [
                "type" => "session.update",
                "session" => [
                    "modalities" => ["text", "audio"],
                    "voice" => "alloy",
                    "instructions" => $this->prompt,
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
            broadcast(new VoiceChunk('Session initialized', 'text', $this->attemptid));
            
        } catch (\Exception $e) {
            Log::error('Session init failed: ' . $e->getMessage());
            broadcast(new VoiceChunk('Session init failed: ' . $e->getMessage(), 'error', $this->attemptid));
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
            broadcast(new VoiceChunk('Sending complete' , 'complete', $this->attemptid));
            
        } catch (\Exception $e) {
            Log::error('Send message failed: ' . $e->getMessage());
            broadcast(new VoiceChunk('Send message failed: ' . $e->getMessage(), 'error', $this->attemptid));
        }
    }

    public function streamResponse(callable $onText, callable $onAudio): void
    {
        try {
            $maxIterations = 100000;
            $iterations = 0;
            $this->textBuffer = ''; // Reset buffer at start
            $chunkindex = 0;
            $audiochunkindex = 0;
            while ($iterations < $maxIterations) {
                $iterations++;
                
                $response = $this->ws->receive();
                
                if (!$response) {
                    broadcast(new VoiceChunk('No response received, ending stream', 'text', $this->attemptid));
                    break;
                }
                
                $data = json_decode($response, true);
                
                if (!$data || !isset($data['type'])) {
                    broadcast(new VoiceChunk('Invalid response format', 'text', $this->attemptid));
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
                        //broadcast(new VoiceChunk('Response completed', 'text', $this->attemptid));
                        return;

                    case 'error':
                        $error = $data['error']['message'] ?? 'Unknown error';
                        broadcast(new VoiceChunk('OpenAI Error: ' . $error, 'text', $this->attemptid));
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
                broadcast(new VoiceChunk('Stream ended due to iteration limit', 'text', $this->attemptid));
                // Send any remaining buffered text
                if (!empty($this->textBuffer)) {
                    $onText($this->textBuffer);
                }
            }
          
        } catch (\Exception $e) {
            Log::error('Stream response failed: ' . $e->getMessage());
            broadcast(new VoiceChunk('Stream failed: ' . $e->getMessage(), 'text', $this->attemptid));
        } finally {
            
            $this->closeConnection();
        }
    }
    
    protected function closeConnection(): void
    {
        try {
            if ($this->ws) {
                $this->ws->close();
                broadcast(new VoiceChunk('Connection closed', 'text', $this->attemptid));
            }
        } catch (\Exception $e) {
            Log::error('Error closing WebSocket: ' . $e->getMessage());
        }
    }
}
