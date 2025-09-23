<?php

namespace App\Jobs;

use App\Events\VoiceChunk;
use App\Services\OpenAIRealtimeService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class StartRealtimeChatWithOpenAI implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected string $prompt;
    protected int $attemptid;
    protected string $input;

    public $timeout = 120; // 2 minutes timeout
    public $tries = 1; // Don't retry this job

    public function __construct(string $prompt, int $attemptid,string $input)
    {
        $this->prompt = $prompt;
        $this->attemptid = $attemptid;
        $this->input = $input;
        broadcast(new VoiceChunk('Job initialized', 'text', $this->attemptid));
    }

    public function handle()
    {
        try {
            broadcast(new VoiceChunk('Starting OpenAI realtime chat...', 'text', $this->attemptid));
            
            $service = new OpenAIRealtimeService($this->attemptid,$this->input,$this->prompt);
            
            // Send the actual prompt, not a hardcoded message
            $service->sendUserMessage($this->input);

            $service->streamResponse(
                function ($text) {
                    broadcast(new VoiceChunk($text, 'response_text', $this->attemptid));
                },
                function ($audio) {
                    //broadcast(new VoiceChunk($audio, 'response_audio', $this->attemptid));
                }
            );
            
            broadcast(new VoiceChunk('Chat session completed', 'text', $this->attemptid));
            
        } catch (\Exception $e) {
            Log::error('StartRealtimeChatWithOpenAI failed: ' . $e->getMessage(), [
                'attempt_id' => $this->attemptid,
                'error' => $e->getTraceAsString()
            ]);
            
            broadcast(new VoiceChunk('Chat failed: ' . $e->getMessage(), 'error', $this->attemptid));
            throw $e; // Re-throw to mark job as failed
        }
    }

    public function failed(\Throwable $exception)
    {
        Log::error('StartRealtimeChatWithOpenAI job failed completely', [
            'attempt_id' => $this->attemptid,
            'error' => $exception->getMessage()
        ]);
        
        broadcast(new VoiceChunk('Job failed: ' . $exception->getMessage(), 'error', $this->attemptid));
    }
}
