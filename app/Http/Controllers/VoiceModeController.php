<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Events\VoiceChunk;
use App\Services\PromptBuilderService;
use App\Jobs\StartRealtimeChatWithOpenAI;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Response;
use App\Models\JourneyStep;
use App\Models\JourneyStepResponse;
use App\Models\JourneyAttempt;

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
            $journeyAttempt = JourneyAttempt::findOrFail($attemptid);

            $journeyStep = JourneyStep::where('journey_id', $journeyAttempt->journey_id)
                ->orderBy('order', 'asc')
                ->first();

            if (!$journeyStep) {
                throw new \Exception('No journey steps found for this journey.');
            }
            $journeyStepResponse = new JourneyStepResponse();
            $journeyStepResponse->journey_attempt_id = $attemptid;
            $journeyStepResponse->journey_step_id = $journeyStep->id;
            $journeyStepResponse->interaction_type = 'voice';
            $journeyStepResponse->submitted_at = time();
            $journeyStepResponse->created_at = time();
            $journeyStepResponse->updated_at = time();
            $journeyStepResponse->save();
            $prompt = $this->promptBuilderService->getChatPrompt($attemptid);


            broadcast(new VoiceChunk('Controller: Dispatching job with prompt: ' . substr($prompt, 0, 100) . '...', 'text', $attemptid, 0));

            // For testing, you might want to dispatch synchronously
            if (config('app.debug')) {
                // Synchronous dispatch for development/debugging
                StartRealtimeChatWithOpenAI::dispatchSync($prompt, $attemptid, $input,$journeyStepResponse->id);
            } else {
                // Asynchronous dispatch for production
                StartRealtimeChatWithOpenAI::dispatchSync($prompt, $attemptid, $input,$journeyStepResponse->id);
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

    /**
     * Serve AI voice audio file (MP3 or WAV)
     */
    public function aivoice($jsrid)
    {
        try {
            // Find the journey step response
            $journeyStepResponse = JourneyStepResponse::findOrFail($jsrid);
            
            // Get the attempt ID from the response
            $attemptId = $journeyStepResponse->journey_attempt_id;
            
            // Try to find MP3 first, then fallback to WAV
            $mp3Path = "ai_audios/{$attemptId}/{$jsrid}/ai_audio.mp3";
            $wavPath = "ai_audios/{$attemptId}/{$jsrid}/ai_vaw.wav";
            
            $filePath = null;
            $mimeType = null;
            $fileName = null;
            
            if (Storage::disk('local')->exists($mp3Path)) {
                $filePath = $mp3Path;
                $mimeType = 'audio/mpeg';
                $fileName = 'ai_audio.mp3';
            } elseif (Storage::disk('local')->exists($wavPath)) {
                $filePath = $wavPath;
                $mimeType = 'audio/wav';
                $fileName = 'ai_audio.wav';
            } else {
                abort(404, 'Audio file not found');
            }
            
            // Get file contents
            $fileContents = Storage::disk('local')->get($filePath);
            $fileSize = Storage::disk('local')->size($filePath);
            
            // Return response with appropriate headers
            return Response::make($fileContents, 200, [
                'Content-Type' => $mimeType,
                'Content-Length' => $fileSize,
                'Content-Disposition' => 'inline; filename="' . $fileName . '"',
                'Accept-Ranges' => 'bytes',
                'Cache-Control' => 'public, max-age=3600',
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to serve audio file: ' . $e->getMessage(), [
                'jsrid' => $jsrid,
                'error' => $e->getTraceAsString()
            ]);
            
            abort(500, 'Failed to serve audio file');
        }
    }
}
