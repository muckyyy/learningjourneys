<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Events\VoiceChunk;
use App\Services\PromptBuilderService;
use App\Services\AIInteractionService;
use App\Services\CertificateIssueService;
use App\Jobs\StartRealtimeChatWithOpenAI;
use App\Jobs\IssueCollectionCertificate;
use App\Jobs\DispatchCertificateIssuedPayload;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Response;
use App\Models\JourneyStep;
use App\Models\JourneyStepResponse;
use App\Models\JourneyAttempt;
use App\Models\CertificateIssue;
use App\Enums\CertificateVariable;
use Illuminate\Support\Facades\DB;

class VoiceModeController extends Controller
{
    protected $promptBuilderService;
    protected $aiService;
    protected CertificateIssueService $certificateIssueService;

    public function __construct(
        PromptBuilderService $promptBuilderService,
        AIInteractionService $aiService,
        CertificateIssueService $certificateIssueService
    ) {
        $this->promptBuilderService = $promptBuilderService;
        $this->aiService = $aiService;
        $this->certificateIssueService = $certificateIssueService;
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
            $journeyStepResponse->step_action = 'start_journey';
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
                    Log::warning('VoiceModeController submitChat: AI rating failed: ' . $e->getMessage());
                }
            }
            
            if ($rate === null) {
                $rate = (int) $journeyStep->ratepass;
            }
            $journeyStepResponse->step_rate = $rate;
            $journeyStepResponse->save();
            // Determine what is the next step/action
            // Count how many attempts have been made for this specific step in this attempt
            $currentAttemptNumber = JourneyStepResponse::where('journey_attempt_id', $attemptid)
                ->where('journey_step_id', $journeyStep->id)
                ->where(function ($query) {
                    $query->whereNull('step_action')
                        ->orWhere('step_action', '!=', 'start_journey');
                })
                ->count();

            $passedRating = $rate >= (int) $journeyStep->ratepass;
            $maxAttemptsReached = $journeyStep->maxattempts !== null
                ? ($currentAttemptNumber >= (int) $journeyStep->maxattempts)
                : false;

            // Check if there is a next step
            $hasNextStep = JourneyStep::where('journey_id', $journeyAttempt->journey_id)
                ->where('order', '>', $journeyStep->order)
                ->orderBy('order', 'asc')
                ->first();
            $nextStepIsFinal = $hasNextStep
                ? !$this->journeyHasStepAfter($journeyAttempt->journey_id, $hasNextStep->order)
                : false;
           
            $stepAction = 'retry_step';

            $canAdvance = $passedRating || $maxAttemptsReached;
            if ($canAdvance) {
                $maxFollowups = (int) ($journeyStep->maxfollowups ?? 0);
                $followupAllowed = $followup && $maxFollowups > 0;

                if ($followupAllowed) {
                    $followupCount = JourneyStepResponse::where('journey_attempt_id', $attemptid)
                        ->where('journey_step_id', $journeyStep->id)
                        ->where('step_action', 'followup_step')
                        ->count();

                    if ($followupCount < $maxFollowups) {
                        $stepAction = 'followup_step';
                    }
                }

                if ($stepAction !== 'followup_step') {
                    if ($hasNextStep) {
                        $stepAction = $nextStepIsFinal ? 'finish_journey' : 'next_step';
                    } else {
                        $stepAction = 'finish_journey';
                    }
                }
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
            
            $shouldMoveToAwaiting = false;
            $skipAwaitingProgressBroadcast = false;

            if ($stepAction === 'next_step') {
                $responseStep = $hasNextStep ?? $journeyStep;
                $journeyAttempt->current_step = $journeyStep->order + 1;
                $journeyAttempt->save();
            } elseif ($stepAction === 'finish_journey') {
                $shouldMoveToAwaiting = true;
            }
            
            // Broadcast the same response ID that will be updated with AI response
            broadcast(new VoiceChunk($journeyStepResponse->id, 'jsrid', $attemptid, 1));
            // Save action on the response
            $journeyStepResponse->step_action = $stepAction;
            $journeyStepResponse->save();
            if ($stepAction === 'next_step' && $hasNextStep && !$this->journeyHasStepAfter($journeyAttempt->journey_id, $hasNextStep->order)) {
                $shouldMoveToAwaiting = true;
            }

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

            if ($shouldMoveToAwaiting) {
                $this->moveAttemptToAwaitingFeedback($journeyAttempt, $journeyStepResponse, $payload, $skipAwaitingProgressBroadcast);
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
                    $this->moveAttemptToAwaitingFeedback($journeyAttempt, $lastjourneystepresponse, $payload);
                }
            } catch (\Throwable $e) {
                Log::warning('VoiceModeController final completion check failed: ' . $e->getMessage());
            }

            if ($journeyAttempt->status === 'awaiting_feedback') {
                $payload['awaiting_feedback'] = true;
            } elseif ($journeyAttempt->status === 'completed') {
                $payload['awaiting_feedback'] = false;
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
        $journeyAttempt->loadMissing('journey.collection');

        if (!in_array($journeyAttempt->status, ['awaiting_feedback', 'completed'], true)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Journey must be awaiting feedback before submitting a rating.'
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
        $journeyAttempt->status = 'completed';
        if (!$journeyAttempt->completed_at) {
            $journeyAttempt->completed_at = now();
        }
        $journeyAttempt->save();

        if ($issue = $this->issueCollectionCertificateIfEligible($journeyAttempt)) {
            IssueCollectionCertificate::dispatch($issue->id);
            $collectionId = $journeyAttempt->journey?->collection?->id;
            if ($collectionId) {
                DispatchCertificateIssuedPayload::dispatch($issue->certificate_id, $collectionId);
            }
        }

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

    protected function issueCollectionCertificateIfEligible(JourneyAttempt $attempt): ?CertificateIssue
    {
        $attempt->loadMissing(
            'user',
            'journey.collection.certificate',
            'journey.collection.journeys',
            'journey.collection.institution'
        );

        $journey = $attempt->journey;
        $collection = $journey?->collection;

        if (! $collection || ! $collection->certificate_id) {
            return null;
        }

        $certificate = $collection->certificate;
        if (! $certificate || ! $certificate->enabled) {
            return null;
        }

        $publishedJourneyIds = $collection->journeys
            ? $collection->journeys->where('is_published', true)->pluck('id')
            : $collection->journeys()->where('is_published', true)->pluck('id');

        if ($publishedJourneyIds->isEmpty()) {
            return null;
        }

        $completedJourneys = JourneyAttempt::query()
            ->where('user_id', $attempt->user_id)
            ->whereIn('journey_id', $publishedJourneyIds)
            ->where('status', 'completed')
            ->where(function ($query) {
                $query->whereNull('journey_type')
                    ->orWhere('journey_type', '!=', 'preview');
            })
            ->distinct()
            ->pluck('journey_id');

        if ($completedJourneys->count() !== $publishedJourneyIds->count()) {
            return null;
        }

        $alreadyIssued = CertificateIssue::query()
            ->where('certificate_id', $certificate->id)
            ->where('user_id', $attempt->user_id)
            ->exists();

        if ($alreadyIssued) {
            return null;
        }

        $overrides = [
            'variables' => [
                CertificateVariable::COLLECTION_NAME => $collection->name,
                CertificateVariable::JOURNEY_COUNT => $publishedJourneyIds->count(),
            ],
        ];

        return $this->certificateIssueService->issue(
            $certificate,
            $attempt->user,
            $overrides,
            $collection->institution
        );
    }

    public function getprompt(int $id, ?int $steporder = null){
        $messages = $this->promptBuilderService->getFullChatPrompt($id);

        $messages = str_replace(["\r\n", "\n", "\r"], '<br>', $messages);

        //$response = $this->aiService->executeChatRequest($messages);
        echo $messages;
        
    }

    protected function journeyHasStepAfter(int $journeyId, int $order): bool
    {
        return JourneyStep::where('journey_id', $journeyId)
            ->where('order', '>', $order)
            ->exists();
    }

    protected function moveAttemptToAwaitingFeedback(
        JourneyAttempt $journeyAttempt,
        JourneyStepResponse $journeyStepResponse,
        array &$payload,
        bool $skipProgressBroadcast = false
    ): void {
        if (in_array($journeyAttempt->status, ['awaiting_feedback', 'completed'], true)) {
            return;
        }

        $journeyAttempt->loadMissing('journey.steps');

        $journeyAttempt->status = 'awaiting_feedback';
        if ($journeyAttempt->journey && $journeyAttempt->journey->steps->isNotEmpty()) {
            $journeyAttempt->current_step = $journeyAttempt->journey->steps->max('order');
        }
        $journeyAttempt->save();

        $journeyStepResponse->step_action = 'finish_journey';
        $journeyStepResponse->save();

        $payload['journey_status'] = 'finish_journey';
        $payload['awaiting_feedback'] = true;

        if (!$skipProgressBroadcast) {
            try {
                broadcast(new VoiceChunk('95', 'progress', $journeyAttempt->id, 1));
            } catch (\Throwable $e) {
                Log::warning('VoiceModeController awaiting feedback progress broadcast failed: ' . $e->getMessage());
            }
        }

        $this->generateFinalReport($journeyAttempt);
    }

    protected function generateFinalReport(JourneyAttempt $journeyAttempt): void
    {
        try {
            $messages = $this->promptBuilderService->getJourneyReport($journeyAttempt->id);
            $response = $this->aiService->executeChatRequest($messages, [
                'journey_attempt_id' => $journeyAttempt->id,
                'ai_model' => config('openai.default_model', 'gpt-4'),
            ]);
            $finalContent = $response->choices[0]->message->content ?? null;
            if ($finalContent) {
                $journeyAttempt->report = is_string($finalContent) ? trim($finalContent) : null;
                $journeyAttempt->save();
            }
        } catch (\Throwable $e) {
            Log::warning('VoiceModeController final report generation failed: ' . $e->getMessage());
        }
    }
}
