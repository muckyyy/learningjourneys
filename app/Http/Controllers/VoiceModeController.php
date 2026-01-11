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
        set_time_limit(0); // Unlimited
        ignore_user_abort(true); // Continue processing even if user disconnects
        try {
            DB::beginTransaction();
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
            broadcast(new VoiceChunk($journeyStepResponse->id, 'jsrid', $attemptid, 1));
            $prompt = $this->promptBuilderService->getFullChatPrompt($attemptid);
            

            // For testing, you might want to dispatch synchronously
            if (config('app.debug')) {
                // Synchronous dispatch for development/debugging
                StartRealtimeChatWithOpenAI::dispatchSync($prompt, $attemptid, $input, $journeyStepResponse->id, $journeyStep->title);
            } else {
                // Asynchronous dispatch for production
                StartRealtimeChatWithOpenAI::dispatch($prompt, $attemptid, $input, $journeyStepResponse->id, $journeyStep->title);
            }
            DB::commit();
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
           
            $journeyAttempt = JourneyAttempt::findOrFail($attemptid);
            
            $journeyStep = JourneyStep::where('journey_id', $journeyAttempt->journey_id)
                ->where('order', $journeyAttempt->current_step)
                ->first();

            if (!$journeyStep) {
                throw new \Exception('No journey step found for this attempt and current step.');
            }

            // Find the last AI response (the one we're responding to)
            $lastAiResponse = JourneyStepResponse::where('journey_attempt_id', $attemptid)
                ->whereNotNull('ai_response')
                ->orderBy('submitted_at', 'desc')
                ->first();

            // Create a NEW JourneyStepResponse for the user input
            $journeyStepResponse = new JourneyStepResponse();
            $journeyStepResponse->journey_attempt_id = $attemptid;
            $journeyStepResponse->journey_step_id = $journeyStep->id;
            $journeyStepResponse->interaction_type = 'voice';
            $journeyStepResponse->user_input = $input;
            $journeyStepResponse->submitted_at = time();
            $journeyStepResponse->created_at = time();
            $journeyStepResponse->updated_at = time();
            $journeyStepResponse->save();

            $messages = $this->promptBuilderService->getFullContext($attemptid, 'rate');

            $context = [
                'journey_attempt_id' => $attemptid,
                'journey_step_response_id' => $journeyStepResponse->id,
                'ai_model' => config('openai.default_model', 'gpt-4'),
            ];

            // Retry AI rating up to 5 times; default to ratepass if all fail
            $maxAttempts = 5;
            $rate = null;
            $followup = false;
            for ($attemptNo = 1; $attemptNo <= $maxAttempts; $attemptNo++) {
                try {
                    $response = $this->aiService->executeChatRequest($messages, $context);
                    $content = $response->choices[0]->message->content ?? null;
                    $candidate = is_string($content) ? trim($content) : null;
                    if ($candidate !== null) {
                        $jsonData = json_decode($candidate, true);
                        if (json_last_error() === JSON_ERROR_NONE && is_array($jsonData)) {
                            if (isset($jsonData['rate']) && is_int($jsonData['rate']) && $jsonData['rate'] >= 1 && $jsonData['rate'] <= 5) {
                                $rate = $jsonData['rate'];
                                $followup = isset($jsonData['followup']) && is_bool($jsonData['followup']) ? $jsonData['followup'] : false;
                                break;
                            }
                        }
                    }
                } catch (\Throwable $e) {
                    
                }
            }

            if ($rate === null) {
                $rate = (int) $journeyStep->ratepass;
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
                if ($followup) {
                    $previousFollowup = JourneyStepResponse::where('journey_attempt_id', $attemptid)
                        ->where('journey_step_id', $journeyStep->id)
                        ->where('step_action', 'followup_step')
                        ->exists();

                    if (!$previousFollowup) $stepAction = 'followup_step';
                    
                }
            } else {
                $stepAction = 'retry_step';
            }

            // Compute and broadcast progress (align with Chat mode: (current_step-1)/total)
            try {
                $journeyStepsCount = JourneyStep::where('journey_id', $journeyAttempt->journey_id)->count();
                if ($journeyStepsCount > 0) {
                    $progress = number_format((($journeyAttempt->current_step - 1) / $journeyStepsCount * 100), 2);
                    broadcast(new VoiceChunk($progress, 'progress', $attemptid, 1));
                }
            } catch (\Throwable $e) {
                Log::warning('VoiceModeController submitChat: failed broadcasting progress: ' . $e->getMessage());
            }
            
            
            $responseStep = $journeyStep; // default: continue with current step
            
            // AI response will update the same record that has the user input
            // The $journeyStepResponse created above will receive the AI response
            
            if ($stepAction === 'next_step') {
                $responseStep = $hasNextStep ?? $journeyStep;
                $journeyAttempt->current_step = $journeyStep->order + 1;
                $journeyAttempt->save();
            } elseif ($stepAction === 'finish_journey') {
                $journeyAttempt->status = 'completed';
                $journeyAttempt->completed_at = now();
                $journeyAttempt->save();
            }
            
            // Broadcast the same response ID that will be updated with AI response
            broadcast(new VoiceChunk($journeyStepResponse->id, 'jsrid', $attemptid, 1));
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
                // Broadcast updated progress with the new current_step
                try {
                    $journeyStepsCount = JourneyStep::where('journey_id', $journeyAttempt->journey_id)->count();
                    if ($journeyStepsCount > 0) {
                        $progress = number_format((($journeyAttempt->current_step - 1) / $journeyStepsCount * 100), 2);
                        broadcast(new VoiceChunk($progress, 'progress', $attemptid, 1));
                    }
                } catch (\Throwable $e) {
                    Log::warning('VoiceModeController submitChat: failed broadcasting post-update progress (next_step): ' . $e->getMessage());
                }
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
            
            $responseStepTitle = $responseStep->title ?? null;
            if (config('app.debug')) {
                StartRealtimeChatWithOpenAI::dispatchSync('', $attemptid, $input, $journeyStepResponse->id, $responseStepTitle);
            } else {
                StartRealtimeChatWithOpenAI::dispatch('', $attemptid, $input, $journeyStepResponse->id, $responseStepTitle);
            }
            // Final completion check (mirror ChatController logic)
            try {
                $lastjourneystepresponse = JourneyStepResponse::where('journey_attempt_id', $attemptid)
                    ->orderBy('submitted_at', 'desc')
                    ->first();
                $lastStep = JourneyStep::where('journey_id', $journeyAttempt->journey_id)
                    ->orderBy('order', 'desc')
                    ->first();
                if ($lastjourneystepresponse && $lastStep && $lastjourneystepresponse->journey_step_id == $lastStep->id) {
                    $journeyAttempt->status = 'completed';
                    $journeyAttempt->completed_at = now();
                    $journeyAttempt->save();
                    $stepAction = 'finish_journey';
                    $lastjourneystepresponse->step_action = 'finish_journey';
                    $lastjourneystepresponse->save();
                    // reflect in payload and UI progress
                    $payload['journey_status'] = 'finish_journey';
                    
                    $messages = $this->promptBuilderService->getJourneyReport($attemptid);
                    // Generate final journey report via AI (no abrupt output)
                    $response = $this->aiService->executeChatRequest($messages, [
                        'journey_attempt_id' => $attemptid,
                        'ai_model' => config('openai.default_model', 'gpt-4'),
                    ]);
                    try {
                        $finalContent = $response->choices[0]->message->content ?? null;
                        if ($finalContent) {
                            $journeyAttempt->report = is_string($finalContent) ? trim($finalContent) : null;
                            $journeyAttempt->save();
                        }
                    } catch (\Throwable $t) {
                        Log::warning('Unable to extract final report content: ' . $t->getMessage());
                    }
                    try { broadcast(new VoiceChunk('100', 'progress', $attemptid, 1)); } catch (\Throwable $e) { /* noop */ }
                }
            } catch (\Throwable $e) {
                Log::warning('VoiceModeController final completion check failed: ' . $e->getMessage());
            }

            if ($journeyAttempt->status === 'completed') {
                $payload['awaiting_feedback'] = $journeyAttempt->rating === null;
                if ($journeyAttempt->rating !== null) {
                    $payload['report'] = $journeyAttempt->report;
                }
            }
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

    public function submitFeedback(Request $request)
    {
        $data = $request->validate([
            'attemptid' => 'required|integer|exists:journey_attempts,id',
            'rating' => 'required|integer|min:1|max:5',
            'feedback' => 'required|string|max:2000',
        ]);

        $journeyAttempt = JourneyAttempt::findOrFail($data['attemptid']);

        if ($journeyAttempt->status !== 'completed') {
            return response()->json([
                'status' => 'error',
                'message' => 'Journey must be completed before submitting feedback.'
            ], 422);
        }

        if ($journeyAttempt->rating !== null) {
            return response()->json([
                'status' => 'error',
                'message' => 'Feedback already submitted for this journey.'
            ], 422);
        }

        $journeyAttempt->rating = (int) $data['rating'];
        $journeyAttempt->feedback = $data['feedback'];
        if (!$journeyAttempt->completed_at) {
            $journeyAttempt->completed_at = now();
        }
        $journeyAttempt->save();

        try {
            broadcast(new VoiceChunk('100', 'progress', $journeyAttempt->id, 1));
        } catch (\Throwable $e) {
            Log::warning('VoiceModeController submitFeedback: failed broadcasting progress: ' . $e->getMessage());
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Feedback submitted successfully.',
            'journey_status' => 'finish_journey',
            'report' => $journeyAttempt->report,
            'rating' => $journeyAttempt->rating,
            'feedback' => $journeyAttempt->feedback,
        ]);
    }

    public function getprompt(int $id, ?int $steporder = null){
        $messages = $this->promptBuilderService->getFullChatPrompt($id);

        $messages = str_replace(["\r\n", "\n", "\r"], '<br>', $messages);

        //$response = $this->aiService->executeChatRequest($messages);
        echo $messages;
        
    }
}
