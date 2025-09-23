<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Events\VoiceChunk;
use App\Services\PromptBuilderService;
use App\Jobs\StartRealtimeChatWithOpenAI;
use Illuminate\Support\Facades\Log;

class VoiceModeController extends Controller
{
    protected $promptBuilderService;

    public function __construct(PromptBuilderService $promptBuilderService)
    {
        $this->promptBuilderService = $promptBuilderService;
    }

    /**
     * Receive a voice chunk and broadcast it.
     */
    public function start(Request $request)
    {
        try {
            $request->validate([
                'attemptid' => 'required|numeric',
                //'input' => 'optional|string',
            ]);

            $attemptid = (int) $request->input('attemptid');
            $input = $request->input('input');

            broadcast(new VoiceChunk('Controller: Getting prompt...', 'text', $attemptid, 0));

            $prompt = $this->promptBuilderService->getChatPrompt($attemptid);


            broadcast(new VoiceChunk('Controller: Dispatching job with prompt: ' . substr($prompt, 0, 100) . '...', 'text', $attemptid, 0));

            // For testing, you might want to dispatch synchronously
            if (config('app.debug')) {
                // Synchronous dispatch for development/debugging
                StartRealtimeChatWithOpenAI::dispatchSync($prompt, $attemptid, $input);
            } else {
                // Asynchronous dispatch for production
                StartRealtimeChatWithOpenAI::dispatchSync($prompt, $attemptid, $input);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Voice chat started successfully',
                'attempt_id' => $attemptid
            ]);
            
        } catch (\Exception $e) {
            Log::error('VoiceModeController start failed: ' . $e->getMessage(), [
                'request' => $request->all(),
                'error' => $e->getTraceAsString()
            ]);
            
            $attemptid = $request->input('attemptid', 1);
            broadcast(new VoiceChunk('Controller error: ' . $e->getMessage(), 'error', $attemptid, 0));
            
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
