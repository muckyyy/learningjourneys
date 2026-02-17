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
    protected string $textBuffer = '';
    protected string $audioBuffer = '';
    protected string $input;
    protected string $prompt;
    protected string $jsrid;
    protected ?int $promptLogId = null;
    protected float $promptLogStartedAt = 0.0;

    public function __construct($attemptid, $input, $prompt, $jsrid)
    {
        $this->attemptid = $attemptid;
        $this->input = $input;
        $this->prompt = $prompt;
        $this->jsrid = $jsrid;
        $this->promptLogStartedAt = microtime(true);

        try {
            $this->ws = new Client(
                "wss://api.openai.com/v1/realtime?model=gpt-realtime",
                [
                    'headers' => [
                        "Authorization" => "Bearer " . config('services.openai.api_key'),
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
            $instructions = $this->prompt ?: $promptService->getChatPrompt($this->attemptid);
            $this->logPrompt($instructions);
            $sessionUpdate = [
                "type" => "session.update",
                "session" => [
                    "modalities" => ["text", "audio"],
                    "voice" => "alloy",
                    "instructions" => $instructions,
                    "temperature" => 0.8,
                    "turn_detection" => [
                        "type" => "server_vad",
                        "threshold" => 0.5,
                        "prefix_padding_ms" => 300,
                        "silence_duration_ms" => 600 
                    ],
                    "output_audio_format" => "pcm16"
                ]
            ];

            $this->ws->send(json_encode($sessionUpdate));
        } catch (\Exception $e) {
            Log::error('Session init failed: ' . $e->getMessage());
        }
    }

    /**
     * @param callable $onText  Used for DB saving. DO NOT broadcast inside this callback in your controller.
     * @param callable $onAudio Used for audio processing.
     */
    public function streamResponse(callable $onText, callable $onAudio): void
    {
        $streamError = null;
        try {
            $this->textBuffer = '';
            $this->audioBuffer = '';
            $chunkindex = 0;
            $audiochunkindex = 0;
            
            $responseStatus = 'in_progress';
            $outputItemStatus = 'in_progress';

            while (true) {
                $raw = $this->ws->receive();
                if (!$raw) break;

                $data = json_decode($raw, true);
                $type = $data['type'] ?? '';

                switch ($type) {
                    /**
                     * 1. PREVENT REAL-TIME DOUBLING:
                     * We ONLY listen to audio_transcript.delta. 
                     * We IGNORE response.text.delta because it contains the exact same text.
                     */
                    case 'response.audio_transcript.delta':
                        $delta = $data['delta'] ?? '';
                        $this->textBuffer .= $delta;
                        // Broadcast the delta to the frontend
                        broadcast(new VoiceChunk($delta, 'text', $this->attemptid, $chunkindex++));
                        break;

                    case 'response.audio.delta':
                        $base64 = $data['delta'] ?? '';
                        if ($base64) {
                            $decoded = base64_decode($base64);
                            $this->audioBuffer .= $decoded;
                            $onAudio($base64);
                            broadcast(new VoiceChunk($base64, 'audio', $this->attemptid, $audiochunkindex++));
                        }
                        break;

                    case 'response.output_item.done':
                        $outputItemStatus = 'done';
                        break;

                    case 'response.done':
                        $responseStatus = 'done';
                        break;

                    case 'error':
                        Log::error('OpenAI Error: ' . json_encode($data['error']));
                        break;
                }

                if ($responseStatus === 'done' && $outputItemStatus === 'done') {
                    usleep(100000); 
                    break;
                }
            }

            // 2. PREVENT FINAL DOUBLING:
            // This saves the full text to the database.
            $this->saveTextResponse();
            $this->saveAudioResponse();

            // Trigger the callback for the controller
            $onText($this->textBuffer);

        } catch (\Exception $e) {
            $streamError = $e->getMessage();
            Log::error('Stream failed: ' . $e->getMessage());
        } finally {
            $this->finalizePromptLog($streamError);
            $this->closeConnection();
        }
    }

    public function sendUserMessage(string $text): void
    {
        $this->ws->send(json_encode([
            "type" => "conversation.item.create",
            "item" => [
                "type" => "message",
                "role" => "user",
                "content" => [["type" => "input_text", "text" => $text]]
            ]
        ]));
        
        $this->ws->send(json_encode(["type" => "response.create"]));
    }

    protected function saveTextResponse(): void
    {
        if (!empty($this->textBuffer) && $this->jsrid) {
            $res = JourneyStepResponse::find($this->jsrid);
            if ($res) {
                $res->ai_response = $this->textBuffer;
                $res->save();
            }
        }
    }

    protected function saveAudioResponse(): void
    {
        if (empty($this->audioBuffer)) return;

        $directory = "ai_audios/{$this->attemptid}/{$this->jsrid}";
        $filepath = "{$directory}/ai_audio.mp3";
        Storage::makeDirectory($directory);

        $mp3Data = $this->createMp3File($this->audioBuffer);
        Storage::put($filepath, $mp3Data);
    }

    protected function createMp3File(string $pcmData): string
    {
        $tempWav = storage_path('app/temp_' . uniqid() . '.wav');
        $tempMp3 = storage_path('app/temp_' . uniqid() . '.mp3');

        file_put_contents($tempWav, $this->createWavHeader($pcmData));

        $cmd = "ffmpeg -y -i \"{$tempWav}\" -codec:a libmp3lame -q:a 2 \"{$tempMp3}\" 2>&1";
        exec($cmd);

        $data = file_exists($tempMp3) ? file_get_contents($tempMp3) : '';
        
        @unlink($tempWav);
        @unlink($tempMp3);
        
        return $data;
    }

    protected function createWavHeader(string $pcmData): string
    {
        $sampleRate = 24000;
        $dataSize = strlen($pcmData);
        $header = pack('V', 0x46464952) . pack('V', $dataSize + 36) . pack('V', 0x45564157);
        $header .= pack('V', 0x20746d66) . pack('V', 16) . pack('v', 1) . pack('v', 1);
        $header .= pack('V', $sampleRate) . pack('V', $sampleRate * 2) . pack('v', 2) . pack('v', 16);
        $header .= pack('V', 0x61746164) . pack('V', $dataSize);
        return $header . $pcmData;
    }

    protected function closeConnection(): void
    {
        if (isset($this->ws)) {
            try { $this->ws->close(); } catch (\Exception $e) {}
        }
    }

    protected function logPrompt(string $instructions): void
    {
        try {
            $logRow = JourneyPromptLog::create([
                'journey_attempt_id' => $this->attemptid,
                'journey_step_response_id' => $this->jsrid,
                'action_type' => 'generate_response',
                'prompt' => $instructions,
                'response' => 'pending',
                'ai_model' => 'gpt-realtime',
                'metadata' => [
                    'transport' => 'websocket',
                    'source' => 'OpenAIRealtimeService',
                ],
            ]);
            $this->promptLogId = $logRow->id;
        } catch (\Throwable $e) {
            Log::warning('OpenAIRealtimeService prompt logging failed: ' . $e->getMessage());
        }
    }

    protected function finalizePromptLog(?string $errorMessage = null): void
    {
        if (!$this->promptLogId) {
            return;
        }

        try {
            $logRow = JourneyPromptLog::find($this->promptLogId);
            if (!$logRow) {
                return;
            }

            $processingMs = round((microtime(true) - $this->promptLogStartedAt) * 1000, 2);
            $metadata = is_array($logRow->metadata) ? $logRow->metadata : [];
            $metadata['response_audio_saved'] = !empty($this->audioBuffer);
            if ($errorMessage) {
                $metadata['error'] = $errorMessage;
            }

            $logRow->update([
                'response' => $errorMessage ? '' : ($this->textBuffer ?? ''),
                'processing_time_ms' => $processingMs,
                'metadata' => $metadata,
            ]);
        } catch (\Throwable $e) {
            Log::warning('OpenAIRealtimeService prompt log finalize failed: ' . $e->getMessage());
        }
    }
}