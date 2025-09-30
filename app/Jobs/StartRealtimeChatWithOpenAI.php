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
    }

    public function handle()
    {
        try {
            
            $service = new OpenAIRealtimeService($this->attemptid,$this->input,$this->prompt,$this->jsrid);
            // Broadcast styles config for the step associated with this jsrid if available
            try {
                $resp = \App\Models\JourneyStepResponse::find($this->jsrid);
                if ($resp) {
                    $step = \App\Models\JourneyStep::find($resp->journey_step_id);
                    if ($step) {
                        $cfg = json_decode($step->config, true);
                        $paraCfg = $cfg['paragraphclassesinit'] ?? null;
                        if ($paraCfg) {
                            broadcast(new VoiceChunk(json_encode($paraCfg), 'styles', $this->attemptid, 1));
                        }
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('StartRealtimeChatWithOpenAI: failed broadcasting styles config: ' . $e->getMessage());
            }
            
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
            // Optionally signal stream completion
            broadcast(new VoiceChunk('complete', 'complete', $this->attemptid, 1));

        } catch (\Exception $e) {
            Log::error('StartRealtimeChatWithOpenAI failed: ' . $e->getMessage(), [
                'attempt_id' => $this->attemptid,
                'error' => $e->getTraceAsString()
            ]);
            throw $e; // Re-throw to mark job as failed
        }
    }

    public function failed(\Throwable $exception)
    {
        Log::error('StartRealtimeChatWithOpenAI job failed completely', [
            'attempt_id' => $this->attemptid,
            'error' => $exception->getMessage()
        ]);

    }
}
