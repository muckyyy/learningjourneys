<?php

namespace App\Services;

use WebSocket\Client;
use App\Events\ChatChunk;
use App\Models\JourneyStep;
use App\Models\JourneyStepResponse;
use App\Services\PromptBuilderService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Models\JourneyPromptLog;

class OpenAIChatService
{
    protected Client $ws;
    public $attemptid;
    protected string $textBuffer = ''; // Buffer for collecting text
    protected string $input;
    protected string $prompt;
    protected string $jsrid;
    protected ?int $currentLogId = null;           // Active JourneyPromptLog row id for current user message
    protected float $currentLogStartAt = 0.0;      // Timestamp to compute processing_time_ms
    // track tokens for current exchange
    protected ?int $requestTokens = null;
    protected ?int $responseTokens = null;

    public function __construct($attemptid)
    {
        $this->attemptid = $attemptid;
        $this->textBuffer = ''; // Initialize buffer
    }

    public function makeStreamingRequest($jsrid){
        $journeyStepResponse = JourneyStepResponse::findOrFail($jsrid);
        // Do not save here; persist only once when the stream is completed
        $journeyStep = JourneyStep::findOrFail($journeyStepResponse->journey_step_id);
        $pbs = new PromptBuilderService();
        
        if (empty($journeyStepResponse->user_input)){
            $messages = [
                ['role' => 'system', 'content' => $pbs->getFullChatPrompt($this->attemptid)],
            ];
        }else {
            $messages = [
                ['role' => 'system', 'content' => $pbs->getFullChatPrompt($this->attemptid)],
                ['role' => 'user', 'content' => $journeyStepResponse->user_input],
            ];
        }

        $payload = [
            'model' => env('OPENAI_CHAT_MODEL', 'gpt-4o'),
            'messages' => $messages,
            'stream' => true,
            // Include token usage in the final stream event if supported by your account/model.
            'stream_options' => ['include_usage' => true],
        ];

        $url = 'https://api.openai.com/v1/chat/completions';
        $headers = [
            'Authorization: Bearer ' . env('OPENAI_API_KEY'),
            'Content-Type: application/json',
            'Accept: text/event-stream',
        ];

        $this->textBuffer = '';
        $buffer = '';
        $streamCompleted = false;
        $config = json_decode($journeyStep->config,true);
        $config = json_encode($config['paragraphclassesinit']);
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, false); // stream to callback
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 300);
        // Disable SSL verification (development only; re-enable for production)
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_WRITEFUNCTION, function ($ch, $data) use (&$buffer, &$streamCompleted, $journeyStepResponse, $config) {
            // 2) Handle streamed packages (SSE frames) here.
            //    TODO: Handle streamed packages and update buffers/UI as needed.
            $buffer .= $data;

            while (($pos = strpos($buffer, "\n")) !== false) {
                $line = substr($buffer, 0, $pos);
                $buffer = substr($buffer, $pos + 1);
                

                if ($line === '' || stripos($line, 'event:') === 0) {
                    continue;
                }

                if (stripos($line, 'data: ') === 0) {
                    $json = substr($line, 6);
                    if ($json === '[DONE]') {
                        $streamCompleted = true; // mark completion
                        continue;
                    }

                    $obj = json_decode($json, true);
                    if (!is_array($obj) || empty($obj['choices'][0])) {
                        continue;
                    }

                    $delta = $obj['choices'][0]['delta']['content'] ?? '';
                    if ($delta !== '') {
                        $this->textBuffer .= $delta;
                        Log::info('Broadcasting partial text: ' . $delta);
                        // Broadcast partial text if needed
                        try {

                            $classes = $config;
                            broadcast(new ChatChunk($this->attemptid, 'aireply', $this->textBuffer, $journeyStepResponse->id, $classes));
                        } catch (\Throwable $e) {
                            Log::warning('Broadcast failed: ' . $e->getMessage());
                        }
                    }
                }
            }

            return strlen($data);
        });

        try {
            curl_exec($ch);
            $err = curl_error($ch);

            if (!$err && $streamCompleted) {
                // Persist once when the response is completed successfully
                try {
                    $journeyStepResponse->ai_response = $this->textBuffer;
                    $journeyStepResponse->save();
                } catch (\Throwable $e) {
                    Log::warning('Persist ai_response (on complete) failed: ' . $e->getMessage());
                }
            }

            if ($err) {
                Log::error('OpenAI stream error: ' . $err);
                broadcast(new ChatChunk($this->attemptid, 'error', 'Stream error: ' . $err));
            } else {
                broadcast(new ChatChunk($this->attemptid, 'complete', 'Streaming complete'));
            }
        } catch (\Throwable $e) {
            Log::error('Streaming request failed: ' . $e->getMessage());
            // Do not persist on exception; only save on successful completion
            broadcast(new ChatChunk($this->attemptid, 'error', 'Streaming failed: ' . $e->getMessage()));
        } finally {
            if (is_resource($ch)) {
                curl_close($ch);
            }
        }

        // Optionally return the final text (caller can ignore)
        return $this->textBuffer;
    }
}
          