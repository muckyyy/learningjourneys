<?php

namespace App\Jobs;

use App\Events\VoiceChunk;
use App\Services\OpenAIRealtimeService;
use App\Services\PromptBuilderService;
use App\Models\JourneyPromptLog;
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
    protected int $jsrid;

    public $timeout = 120; // 2 minutes timeout
    public $tries = 1; // Don't retry this job

    public function __construct(string $prompt, int $attemptid,string $input,int $jsrid)
    {
        $this->prompt = $prompt;
        $this->attemptid = $attemptid;
        $this->input = $input;
        $this->jsrid = $jsrid;
        broadcast(new VoiceChunk('Job initialized', 'text', $this->attemptid, 0));
    }

    public function handle()
    {
        try {
            broadcast(new VoiceChunk('Starting OpenAI realtime chat...', 'text', $this->attemptid, 0));
            
            $service = new OpenAIRealtimeService($this->attemptid,$this->input,$this->prompt,$this->jsrid);
            
            // Send the actual prompt, not a hardcoded message
            $service->sendUserMessage($this->input);

            $service->streamResponse(
                function ($text) {
                    broadcast(new VoiceChunk($text, 'response_text', $this->attemptid, 0));
                },
                function ($audio) {
                    //broadcast(new VoiceChunk($audio, 'response_audio', $this->attemptid));
                }
            );

            broadcast(new VoiceChunk('Chat session completed', 'text', $this->attemptid, 0));

        } catch (\Exception $e) {
            Log::error('StartRealtimeChatWithOpenAI failed: ' . $e->getMessage(), [
                'attempt_id' => $this->attemptid,
                'error' => $e->getTraceAsString()
            ]);

            broadcast(new VoiceChunk('Chat failed: ' . $e->getMessage(), 'error', $this->attemptid, 0));
            throw $e; // Re-throw to mark job as failed
        }
    }

    public function failed(\Throwable $exception)
    {
        Log::error('StartRealtimeChatWithOpenAI job failed completely', [
            'attempt_id' => $this->attemptid,
            'error' => $exception->getMessage()
        ]);

        broadcast(new VoiceChunk('Job failed: ' . $exception->getMessage(), 'error', $this->attemptid, 0));
    }
}
