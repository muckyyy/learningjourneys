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
use Illuminate\Support\Facades\Bus;
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

    // ─── Public endpoints ────────────────────────────────────────────

    /**
     * Start a new step in the journey.
     *
     * Creates a step_start (or step_finish_journey) record and dispatches AI
     * to generate the step introduction.  For the very first call this also
     * initialises the journey attempt.
     */
    public function start(Request $request)
    {
        //set_time_limit(0);
        //ignore_user_abort(true);

        try {
            DB::beginTransaction();

            $request->validate([
                'attemptid' => 'required|numeric',
            ]);

            $attemptid = (int) $request->input('attemptid');
            $input     = $request->input('input', '');

            $journeyAttempt = JourneyAttempt::findOrFail($attemptid);

            // Resolve which step we are starting
            $journeyStep = JourneyStep::where('journey_id', $journeyAttempt->journey_id)
                ->where('order', $journeyAttempt->current_step)
                ->first();

            if (! $journeyStep) {
                $journeyStep = JourneyStep::where('journey_id', $journeyAttempt->journey_id)
                    ->orderBy('order', 'asc')
                    ->first();
            }

            if (! $journeyStep) {
                throw new \Exception('No journey steps found for this journey.');
            }

            // Ensure attempt tracks the correct step
            if ($journeyAttempt->current_step !== $journeyStep->order) {
                $journeyAttempt->current_step = $journeyStep->order;
                $journeyAttempt->save();
            }

            // Determine action (last step → finish)
            $isLastStep = ! $this->journeyHasStepAfter($journeyAttempt->journey_id, $journeyStep->order);
            $stepAction = $isLastStep ? 'step_finish_journey' : 'step_start';

            // Create step record
            $jsr = new JourneyStepResponse();
            $jsr->journey_attempt_id = $attemptid;
            $jsr->journey_step_id    = $journeyStep->id;
            $jsr->interaction_type   = 'voice';
            $jsr->step_action        = $stepAction;
            $jsr->submitted_at       = now();
            $jsr->save();

            // Broadcast the response ID the frontend should associate with
            broadcast(new VoiceChunk($jsr->id, 'jsrid', $attemptid, 1));
            $this->broadcastProgress($journeyAttempt);

            $payload = [
                'status'      => 'success',
                'message'     => 'Step started successfully',
                'attempt_id'  => $attemptid,
                'step_action' => $stepAction,
            ];

            if ($stepAction === 'step_finish_journey') {
                $this->moveAttemptToAwaitingFeedback($journeyAttempt, $jsr, $payload);
            }

            // Dispatch AI generation
            $this->dispatchAI($attemptid, $input, $jsr->id, $journeyStep->title, $stepAction);

            DB::commit();
            return response()->json($payload);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('VoiceModeController start failed: ' . $e->getMessage(), [
                'request' => $request->all(),
                'error'   => $e->getTraceAsString(),
            ]);

            $attemptid = $request->input('attemptid', 1);
            broadcast(new VoiceChunk('Controller error: ' . $e->getMessage(), 'error', $attemptid, 0));

            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Handle user chat submission with the fixed-step process.
     *
     * Flow
     * ────
     *  1. Save user input as a new JourneyStepResponse
     *  2. AI rates the response
     *  3. Determine next action:
     *       step_retry    – rate < ratepass AND attempts < maxattempts
     *       step_followup – can advance AND followups remaining
     *       step_complete – can advance, no followups → reflection on current step,
     *                       then auto-chain step_start for next step
     *                       (or step_finish_journey if it was the last)
     */
    public function submitChat(Request $request)
    {
        DB::beginTransaction();

        try {
            $request->validate([
                'attemptid' => 'required|numeric',
                'input'     => 'required|string',
            ]);

            $attemptid = (int) $request->input('attemptid');
            $input     = $request->input('input');

            $journeyAttempt = JourneyAttempt::findOrFail($attemptid);

            $journeyStep = JourneyStep::where('journey_id', $journeyAttempt->journey_id)
                ->where('order', $journeyAttempt->current_step)
                ->first();

            if (! $journeyStep) {
                throw new \Exception('No journey step found for this attempt and current step.');
            }

            // ── 1. Save user input ──────────────────────────────────────
            $jsr = new JourneyStepResponse();
            $jsr->journey_attempt_id = $attemptid;
            $jsr->journey_step_id    = $journeyStep->id;
            $jsr->interaction_type   = 'voice';
            $jsr->user_input         = $input;
            $jsr->submitted_at       = now();
            $jsr->save();

            // ── 2. AI rating ────────────────────────────────────────────
            $rate = $this->rateUserResponse($attemptid, $jsr);
            $jsr->step_rate = $rate;
            $jsr->save();

            // ── 3. Determine action ─────────────────────────────────────
            $attemptCount = JourneyStepResponse::where('journey_attempt_id', $attemptid)
                ->where('journey_step_id', $journeyStep->id)
                ->whereNotNull('user_input')
                ->where('user_input', '!=', '')
                ->count();

            $passedRating      = $rate >= (int) $journeyStep->ratepass;
            $maxAttemptsReached = $journeyStep->maxattempts !== null
                ? ($attemptCount >= (int) $journeyStep->maxattempts)
                : false;

            $canAdvance = $passedRating || $maxAttemptsReached;
            $stepAction = 'step_retry'; // default

            if ($canAdvance) {
                $maxFollowups = (int) ($journeyStep->allowfollowup ?? 0);

                if ($maxFollowups > 0) {
                    $followupCount = JourneyStepResponse::where('journey_attempt_id', $attemptid)
                        ->where('journey_step_id', $journeyStep->id)
                        ->where('step_action', 'step_followup')
                        ->count();

                    if ($followupCount < $maxFollowups) {
                        $stepAction = 'step_followup';
                    }
                }

                if ($stepAction !== 'step_followup') {
                    $stepAction = 'step_complete';
                }
            }

            $jsr->step_action = $stepAction;
            $jsr->save();

            $this->broadcastProgress($journeyAttempt);
            broadcast(new VoiceChunk($jsr->id, 'jsrid', $attemptid, 1));

            // ── 4. Build first AI job for current action ────────────────
            $currentPrompt = $this->promptBuilderService->getFullChatPrompt($attemptid);
            $firstJob = new StartRealtimeChatWithOpenAI($currentPrompt, $attemptid, $input, $jsr->id, $journeyStep->title, $stepAction);

            // ── 5. Build payload ────────────────────────────────────────
            $payload = [
                'status'          => 'success',
                'attempt_id'      => $attemptid,
                'step_id'         => $journeyStep->id,
                'rate'            => $rate,
                'ratepass'        => (int) ($journeyStep->ratepass ?? 3),
                'action'          => $stepAction,
                'current_attempt' => $attemptCount,
                'max_attempts'    => (int) ($journeyStep->maxattempts ?? 0),
            ];

            // ── 6. If step_complete → auto-chain next step ─────────────
            $secondJob = null;

            if ($stepAction === 'step_complete') {
                $nextStep = JourneyStep::where('journey_id', $journeyAttempt->journey_id)
                    ->where('order', '>', $journeyStep->order)
                    ->orderBy('order', 'asc')
                    ->first();

                if ($nextStep) {
                    $journeyAttempt->current_step = $nextStep->order;
                    if (! $journeyAttempt->status || $journeyAttempt->status === 'pending') {
                        $journeyAttempt->status = 'in_progress';
                    }
                    $journeyAttempt->save();

                    $isLastStep = ! $this->journeyHasStepAfter($journeyAttempt->journey_id, $nextStep->order);
                    $nextAction = $isLastStep ? 'step_finish_journey' : 'step_start';

                    $nextJsr = new JourneyStepResponse();
                    $nextJsr->journey_attempt_id = $attemptid;
                    $nextJsr->journey_step_id    = $nextStep->id;
                    $nextJsr->interaction_type   = 'voice';
                    $nextJsr->step_action        = $nextAction;
                    $nextJsr->submitted_at       = now();
                    $nextJsr->save();

                    broadcast(new VoiceChunk($nextJsr->id, 'jsrid', $attemptid, 1));
                    $this->broadcastProgress($journeyAttempt);

                    if ($nextAction === 'step_finish_journey') {
                        $this->moveAttemptToAwaitingFeedback($journeyAttempt, $nextJsr, $payload);
                    }

                    $nextPrompt = $this->promptBuilderService->getFullChatPrompt($attemptid);
                    $secondJob = new StartRealtimeChatWithOpenAI($nextPrompt, $attemptid, '', $nextJsr->id, $nextStep->title, $nextAction);

                    $payload['next_step'] = [
                        'id'     => $nextStep->id,
                        'title'  => $nextStep->title,
                        'order'  => $nextStep->order,
                        'action' => $nextAction,
                    ];
                } else {
                    // No next step — was effectively the last step
                    $this->moveAttemptToAwaitingFeedback($journeyAttempt, $jsr, $payload);
                }
            }

            // ── Dispatch AI jobs (chained so they don't overlap) ────────
            if ($secondJob) {
                if (config('app.debug')) {
                    StartRealtimeChatWithOpenAI::dispatchSync($currentPrompt, $attemptid, $input, $jsr->id, $journeyStep->title, $stepAction);
                    StartRealtimeChatWithOpenAI::dispatchSync($nextPrompt, $attemptid, '', $nextJsr->id, $nextStep->title, $nextAction);
                } else {
                    Bus::chain([$firstJob, $secondJob])->dispatch();
                }
            } else {
                $this->dispatchAI($attemptid, $input, $jsr->id, $journeyStep->title, $stepAction);
            }

            // Append feedback / completion flags
            $journeyAttempt->refresh();

            if ($journeyAttempt->status === 'awaiting_feedback') {
                $payload['journey_status']    = 'finish_journey';
                $payload['awaiting_feedback'] = true;
            } elseif ($journeyAttempt->status === 'completed') {
                $payload['awaiting_feedback'] = false;
                if ($journeyAttempt->rating !== null) {
                    $payload['report'] = $journeyAttempt->report;
                }
            }

            DB::commit();
            return response()->json($payload);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('VoiceModeController submit chat failed: ' . $e->getMessage(), [
                'request' => $request->all(),
                'error'   => $e->getTraceAsString(),
            ]);

            $attemptid = $request->input('attemptid', 1);
            broadcast(new VoiceChunk('Controller error: ' . $e->getMessage(), 'error', $attemptid, 0));

            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Serve AI voice audio file (MP3 or WAV).
     */
    public function aivoice($jsrid)
    {
        try {
            $jsr       = JourneyStepResponse::findOrFail($jsrid);
            $attemptId = $jsr->journey_attempt_id;

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

            $fileContents = Storage::disk('local')->get($filePath);
            $fileSize     = Storage::disk('local')->size($filePath);

            return Response::make($fileContents, 200, [
                'Content-Type'        => $mimeType,
                'Content-Length'      => $fileSize,
                'Content-Disposition' => 'inline; filename="' . $fileName . '"',
                'Accept-Ranges'       => 'bytes',
                'Cache-Control'       => 'public, max-age=3600',
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to serve audio file: ' . $e->getMessage(), [
                'jsrid' => $jsrid,
                'error' => $e->getTraceAsString(),
            ]);
            abort(500, 'Failed to serve audio file');
        }
    }

    /**
     * Handle journey feedback submission.
     */
    public function submitFeedback(Request $request)
    {
        $data = $request->validate([
            'attemptid' => 'required|integer|exists:journey_attempts,id',
            'rating'    => 'required|integer|min:1|max:5',
            'feedback'  => 'required|string|max:2000',
        ]);

        $journeyAttempt = JourneyAttempt::findOrFail($data['attemptid']);
        $journeyAttempt->loadMissing('journey.collection');

        if (! in_array($journeyAttempt->status, ['awaiting_feedback', 'completed'], true)) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Journey must be awaiting feedback before submitting a rating.',
            ], 422);
        }

        if ($journeyAttempt->rating !== null) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Feedback already submitted for this journey.',
            ], 422);
        }

        $journeyAttempt->rating   = (int) $data['rating'];
        $journeyAttempt->feedback = $data['feedback'];
        $journeyAttempt->status   = 'completed';
        if (! $journeyAttempt->completed_at) {
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
            Log::warning('VoiceModeController submitFeedback: progress broadcast failed: ' . $e->getMessage());
        }

        return response()->json([
            'status'         => 'success',
            'message'        => 'Feedback submitted successfully.',
            'journey_status' => 'finish_journey',
            'report'         => $journeyAttempt->report,
            'rating'         => $journeyAttempt->rating,
            'feedback'       => $journeyAttempt->feedback,
        ]);
    }

    /**
     * Admin-only: reset a journey attempt back to step 1.
     *
     * Deletes all JourneyStepResponses (and related audio files),
     * resets current_step to 1, clears report/rating/feedback,
     * and sets status back to in_progress.
     */
    public function resetAttempt(Request $request)
    {
        $request->validate([
            'attemptid' => 'required|integer|exists:journey_attempts,id',
        ]);

        $user = $request->user();
        if (! $user || ! $user->isAdministrator()) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized.'], 403);
        }

        try {
            DB::beginTransaction();

            $attempt = JourneyAttempt::findOrFail($request->input('attemptid'));

            // Delete related audio files from storage
            $audioDir = "ai_audios/{$attempt->id}";
            if (Storage::disk('local')->exists($audioDir)) {
                Storage::disk('local')->deleteDirectory($audioDir);
            }

            // Delete all step responses (cascades to debug entries, audio recordings, prompt logs via DB)
            $attempt->stepResponses()->delete();

            // Reset attempt state
            $attempt->current_step  = 1;
            $attempt->status        = 'in_progress';
            $attempt->report        = null;
            $attempt->rating        = null;
            $attempt->feedback      = null;
            $attempt->completed_at  = null;
            $attempt->score         = null;
            $attempt->progress_data = null;
            $attempt->save();

            DB::commit();

            return response()->json([
                'status'  => 'success',
                'message' => 'Journey attempt has been reset to step 1.',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('VoiceModeController resetAttempt failed: ' . $e->getMessage(), [
                'attemptid' => $request->input('attemptid'),
                'error'     => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status'  => 'error',
                'message' => 'Failed to reset attempt: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Admin-only: rollback to a specific response.
     *
     * Deletes the targeted JourneyStepResponse and every response that
     * came after it, removes their audio files, and resets the attempt's
     * current_step to match the last remaining response's step (or 1).
     */
    public function rollbackToResponse(Request $request)
    {
        $request->validate([
            'attemptid' => 'required|integer|exists:journey_attempts,id',
            'jsrid'     => 'required|integer|exists:journey_step_responses,id',
        ]);

        $user = $request->user();
        if (! $user || ! $user->isAdministrator()) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized.'], 403);
        }

        try {
            DB::beginTransaction();

            $attempt  = JourneyAttempt::findOrFail($request->input('attemptid'));
            $targetId = (int) $request->input('jsrid');

            // Get all responses for this attempt ordered by id
            $allResponses = $attempt->stepResponses()->orderBy('id')->get();

            // Find responses to delete: the target and everything after it
            $toDelete = $allResponses->filter(fn ($r) => $r->id >= $targetId);

            if ($toDelete->isEmpty()) {
                DB::rollBack();
                return response()->json(['status' => 'error', 'message' => 'Response not found in this attempt.'], 404);
            }

            // Delete audio files for each response being removed
            foreach ($toDelete as $resp) {
                $audioDir = "ai_audios/{$attempt->id}/{$resp->id}";
                if (Storage::disk('local')->exists($audioDir)) {
                    Storage::disk('local')->deleteDirectory($audioDir);
                }
            }

            // Delete the responses
            $attempt->stepResponses()->where('id', '>=', $targetId)->delete();

            // Determine what step we should be on now
            $remaining = $attempt->stepResponses()->orderBy('id', 'desc')->first();

            if ($remaining) {
                // Set current_step to the step of the last remaining response
                $step = JourneyStep::find($remaining->journey_step_id);
                $attempt->current_step = $step ? $step->order : 1;
            } else {
                $attempt->current_step = 1;
            }

            $attempt->status        = 'in_progress';
            $attempt->report        = null;
            $attempt->rating        = null;
            $attempt->feedback      = null;
            $attempt->completed_at  = null;
            $attempt->save();

            DB::commit();

            return response()->json([
                'status'       => 'success',
                'message'      => 'Rolled back successfully.',
                'current_step' => $attempt->current_step,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('VoiceModeController rollbackToResponse failed: ' . $e->getMessage(), [
                'attemptid' => $request->input('attemptid'),
                'jsrid'     => $request->input('jsrid'),
                'error'     => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status'  => 'error',
                'message' => 'Rollback failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Debug helper: dump the compiled prompt for an attempt.
     */
    public function getprompt(int $id, ?int $steporder = null)
    {
        $messages = $this->promptBuilderService->getFullChatPrompt($id);
        echo str_replace(["\r\n", "\n", "\r"], '<br>', $messages);
    }

    // ─── Private helpers ─────────────────────────────────────────────

    /**
     * Rate the user's response via AI.  Retries up to 5 times; defaults to ratepass on failure.
     */
    private function rateUserResponse(int $attemptid, JourneyStepResponse $jsr): int
    {
        $journeyStep = JourneyStep::findOrFail($jsr->journey_step_id);

        $messages = $this->promptBuilderService->getFullContext($attemptid, 'rate');
        $context  = [
            'journey_attempt_id'       => $attemptid,
            'journey_step_response_id' => $jsr->id,
            'ai_model'                 => config('openai.default_model', 'gpt-4'),
        ];

        $maxRetries = 5;
        $rate       = null;

        for ($i = 1; $i <= $maxRetries; $i++) {
            try {
                $response  = $this->aiService->executeChatRequest($messages, $context);
                $content   = $response->choices[0]->message->content ?? null;
                $candidate = is_string($content) ? (int) trim($content) : null;

                if ($candidate !== null && $candidate >= 1 && $candidate <= 5) {
                    $rate = $candidate;
                    break;
                }
            } catch (\Throwable $e) {
                Log::warning("VoiceModeController rateUserResponse attempt {$i} failed: " . $e->getMessage());
            }
        }

        return $rate ?? (int) $journeyStep->ratepass;
    }

    /**
     * Dispatch the AI generation job (sync in debug, async in production).
     */
    private function dispatchAI(int $attemptid, string $input, int $jsrid, ?string $stepTitle, ?string $stepAction = null): void
    {
        $prompt = $this->promptBuilderService->getFullChatPrompt($attemptid);

        if (config('app.debug')) {
            StartRealtimeChatWithOpenAI::dispatchSync($prompt, $attemptid, $input, $jsrid, $stepTitle, $stepAction);
        } else {
            StartRealtimeChatWithOpenAI::dispatch($prompt, $attemptid, $input, $jsrid, $stepTitle, $stepAction);
        }
    }

    /**
     * Broadcast current journey progress percentage.
     */
    private function broadcastProgress(JourneyAttempt $journeyAttempt): void
    {
        try {
            $totalSteps = JourneyStep::where('journey_id', $journeyAttempt->journey_id)->count();
            if ($totalSteps > 0) {
                $progress = number_format((($journeyAttempt->current_step - 1) / $totalSteps * 100), 2);
                broadcast(new VoiceChunk($progress, 'progress', $journeyAttempt->id, 1));
            }
        } catch (\Throwable $e) {
            Log::warning('VoiceModeController broadcastProgress failed: ' . $e->getMessage());
        }
    }

    /**
     * Does this journey have a step with order > $order?
     */
    protected function journeyHasStepAfter(int $journeyId, int $order): bool
    {
        return JourneyStep::where('journey_id', $journeyId)
            ->where('order', '>', $order)
            ->exists();
    }

    /**
     * Transition an attempt to awaiting_feedback, generate its final report,
     * and optionally issue a collection certificate.
     */
    protected function moveAttemptToAwaitingFeedback(
        JourneyAttempt $journeyAttempt,
        JourneyStepResponse $jsr,
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

        $jsr->step_action = 'step_finish_journey';
        $jsr->save();

        $payload['journey_status']    = 'finish_journey';
        $payload['awaiting_feedback'] = true;

        if (! $skipProgressBroadcast) {
            try {
                broadcast(new VoiceChunk('95', 'progress', $journeyAttempt->id, 1));
            } catch (\Throwable $e) {
                Log::warning('VoiceModeController awaiting feedback progress broadcast failed: ' . $e->getMessage());
            }
        }

        $this->generateFinalReport($journeyAttempt);
    }

    /**
     * Ask the AI for a journey report and persist it on the attempt.
     */
    protected function generateFinalReport(JourneyAttempt $journeyAttempt): void
    {
        try {
            $messages = $this->promptBuilderService->getJourneyReport($journeyAttempt->id);
            $response = $this->aiService->executeChatRequest($messages, [
                'journey_attempt_id' => $journeyAttempt->id,
                'ai_model'           => config('openai.default_model', 'gpt-4'),
            ]);
            $finalContent = $response->choices[0]->message->content ?? null;
            if ($finalContent) {
                $journeyAttempt->report = is_string($finalContent) ? trim($finalContent) : null;
                $journeyAttempt->save();
            }
        } catch (\Throwable $e) {
            Log::warning('VoiceModeController final report generation failed: ' . $e->getMessage());
        }

        // Issue collection certificate if all journeys in the collection are done
        try {
            if ($issue = $this->issueCollectionCertificateIfEligible($journeyAttempt)) {
                IssueCollectionCertificate::dispatch($issue->id);
                $collectionId = $journeyAttempt->journey?->collection?->id;
                if ($collectionId) {
                    DispatchCertificateIssuedPayload::dispatch($issue->certificate_id, $collectionId);
                }
            }
        } catch (\Throwable $e) {
            Log::warning('VoiceModeController certificate issuance failed: ' . $e->getMessage());
        }
    }

    /**
     * Issue a collection certificate if every published journey in the
     * collection has been completed by this user.
     */
    protected function issueCollectionCertificateIfEligible(JourneyAttempt $attempt): ?CertificateIssue
    {
        $attempt->loadMissing(
            'user',
            'journey.collection.certificate',
            'journey.collection.journeys',
            'journey.collection.institution'
        );

        $journey    = $attempt->journey;
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
            ->whereIn('status', ['completed', 'awaiting_feedback'])
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
                CertificateVariable::JOURNEY_COUNT   => $publishedJourneyIds->count(),
            ],
        ];

        return $this->certificateIssueService->issue(
            $certificate,
            $attempt->user,
            $overrides,
            $collection->institution,
            $collection->id
        );
    }
}
