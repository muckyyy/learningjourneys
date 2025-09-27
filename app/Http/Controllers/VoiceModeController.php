<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Events\VoiceChunk;
use App\Services\PromptBuilderService;
use App\Services\AIInteractionService;
use App\Jobs\StartRealtimeChatWithOpenAI;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Response;
use App\Models\JourneyStep;
use App\Models\JourneyStepResponse;
use App\Models\JourneyAttempt;
use Illuminate\Support\Facades\DB;

class VoiceModeController extends Controller
{
    protected $promptBuilderService;
    protected $aiService;

    public function __construct(PromptBuilderService $promptBuilderService, AIInteractionService $aiService)
    {
        $this->promptBuilderService = $promptBuilderService;
        $this->aiService = $aiService;
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

     /**
     * Receive a voice chunk and broadcast it.
     */
    public function submitChat(Request $request)
    {
        DB::beginTransaction();
        try {
            $request->validate([
                'attemptid' => 'required|numeric',
                'input' => 'required|string',
            ]);
            
            $attemptid = (int) $request->input('attemptid');
            $input = $request->input('input');
            broadcast(new VoiceChunk('Starting response acceptance ' . $input, 'text', $attemptid, 0));
            $journeyAttempt = JourneyAttempt::findOrFail($attemptid);
            
            $journeyStep = JourneyStep::where('journey_id', $journeyAttempt->journey_id)
                ->where('order', $journeyAttempt->current_step)
                ->first();

            if (!$journeyStep) {
                throw new \Exception('No journey step found for this attempt and current step.');
            }
            
            $journeyStepResponse = JourneyStepResponse::where('journey_attempt_id', $attemptid)
                ->orderBy('submitted_at', 'desc')
                ->first();
            
            if (!$journeyStepResponse) {
                throw new \Exception('No journey step response found for this attempt.');
            }
            
            if ($journeyStepResponse->user_input) {
                throw new \Exception('This journey step response has already been processed.');
            }
            $journeyStepResponse->user_input = $input;
            $journeyStepResponse->updated_at = time();
            $journeyStepResponse->save();
            
            $messages = $this->promptBuilderService->getFullContext($attemptid, 'rate');  
            $context = [
                'journey_attempt_id' => $attemptid,
                'journey_step_response_id' => $journeyStepResponse->id,
                'ai_model' => config('openai.default_model', 'gpt-4'),
            ];
            $response = $this->aiService->executeChatRequest($messages, $context);

            $rate = $response->choices[0]->message->content;
            if (!is_numeric(trim($rate))) {
                throw new \Exception('AI response is not a valid number: ' . $rate);
            }
            $rate = (int) trim($rate);
            if ($rate < 1 || $rate > 5) {
                throw new \Exception('AI response is out of range (1-5): ' . $rate);
            }
            $journeyStepResponse->step_rate = $rate;
            // Determine what is the next step/action
            // Count how many attempts have been made for this specific step in this attempt
            $currentAttemptNumber = JourneyStepResponse::where('journey_attempt_id', $attemptid)
                ->where('journey_step_id', $journeyStep->id)
                ->count();

            $passedRating = $rate >= (int) $journeyStep->ratepass;
            $maxAttemptsReached = $journeyStep->maxattempts !== null
                ? ($currentAttemptNumber === (int) $journeyStep->maxattempts)
                : false;

            // Check if there is a next step
            $hasNextStep = JourneyStep::where('journey_id', $journeyAttempt->journey_id)
                ->where('order', '>', $journeyStep->order)
                ->orderBy('order', 'asc')
                ->first();
            
            if ($passedRating || $maxAttemptsReached) {
                $stepAction = $hasNextStep ? 'next_step' : 'finish_journey';
            } else {
                $stepAction = 'retry_step';
            }
            
            if ($stepAction == 'retry_step') {
                $nextstepresponse = new JourneyStepResponse();
                $nextstepresponse->journey_attempt_id = $attemptid;
                $nextstepresponse->journey_step_id = $journeyStep->id;
                $nextstepresponse->interaction_type = 'voice';
                $nextstepresponse->submitted_at = time();
                $nextstepresponse->created_at = time();
                $nextstepresponse->updated_at = time();
                $nextstepresponse->save();
            }
            else {
                if ($stepAction === 'next_step') {
                    $journeyAttempt->current_step = $journeyStep->order + 1;
                    $journeyAttempt->save();
                    $nextstepresponse = new JourneyStepResponse();
                    $nextstepresponse->journey_attempt_id = $attemptid;
                    $nextstepresponse->journey_step_id = $hasNextStep->id;
                    $nextstepresponse->interaction_type = 'voice';
                    $nextstepresponse->submitted_at = time();
                    $nextstepresponse->created_at = time();
                    $nextstepresponse->updated_at = time();
                    $nextstepresponse->save();

                } elseif ($stepAction === 'finish_journey') {
                    
                    $journeyAttempt->status = 'completed';
                    $journeyAttempt->completed_at = now();
                    $journeyAttempt->save();
                    //We need to recreate same step for AI to close discusssion
                    $nextstepresponse = new JourneyStepResponse();
                    $nextstepresponse->journey_attempt_id = $attemptid;
                    $nextstepresponse->journey_step_id = $journeyStep->id;
                    $nextstepresponse->interaction_type = 'voice';
                    $nextstepresponse->step_action = 'finish_journey';
                    $nextstepresponse->submitted_at = time();
                    $nextstepresponse->created_at = time();
                    $nextstepresponse->updated_at = time();
                    $nextstepresponse->save();

                }
            }
            
            // Save action on the response
            $journeyStepResponse->step_action = $stepAction;
            $journeyStepResponse->save();

            // Update attempt progress based on action
            if ($stepAction === 'next_step') {
                $journeyAttempt->current_step = $journeyStep->order + 1;
                // Keep status as in_progress if not explicitly set
                if (!$journeyAttempt->status || $journeyAttempt->status === 'pending') {
                    $journeyAttempt->status = 'in_progress';
                }
                $journeyAttempt->save();
            } elseif ($stepAction === 'finish_journey') {
                $journeyAttempt->status = 'completed';
                $journeyAttempt->completed_at = now();
                $journeyAttempt->save();
            }

            // Prepare response payload
            $payload = [
                'status' => 'success',
                'attempt_id' => $attemptid,
                'step_id' => $journeyStep->id,
                'rate' => $rate,
                'action' => $stepAction,
                'current_attempt' => $currentAttemptNumber,
                'max_attempts' => (int) ($journeyStep->maxattempts ?? 0),
            ];
        
            if ($stepAction === 'next_step') {
                $nextStep = JourneyStep::where('journey_id', $journeyAttempt->journey_id)
                    ->where('order', $journeyStep->order + 1)
                    ->first();
                if ($nextStep) {
                    $payload['next_step'] = [
                        'id' => $nextStep->id,
                        'title' => $nextStep->title,
                        'order' => $nextStep->order,
                    ];
                }
            }
            
            StartRealtimeChatWithOpenAI::dispatchSync('', $attemptid, $input,$nextstepresponse->id);
            DB::commit(); // Commit if all went well
            return response()->json($payload);
        }catch (\Exception $e) {
            Log::error('VoiceModeController submit chat failed: ' . $e->getMessage(), [
                'request' => $request->all(),
                'error' => $e->getTraceAsString()
            ]);
            DB::rollBack();
            $attemptid = $request->input('attemptid', 1);
            broadcast(new VoiceChunk('Controller error: ' . $e->getMessage(), 'error', $attemptid, 0));
            
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function testdata(){
        $messages = $this->promptBuilderService->getFullContext(1,'rate');
        $response = $this->aiService->executeChatRequest($messages);
        dd($messages, $response->choices[0]->message->content);
        
    }
}
