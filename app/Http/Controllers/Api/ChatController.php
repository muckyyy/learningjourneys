<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Journey;
use App\Models\JourneyAttempt;
use App\Models\JourneyStepResponse;
use App\Models\JourneyStep;
use App\Models\JourneyPromptLog;
use App\Events\ChatChunk;
use App\Services\AIInteractionService;
use App\Services\PromptBuilderService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ChatController extends Controller
{
    protected $aiService;
    protected $promptBuilderService;

    public function __construct(AIInteractionService $aiService, PromptBuilderService $promptBuilderService)
    {
        $this->aiService = $aiService;
        $this->promptBuilderService = $promptBuilderService;
    }

    public function startChat(Request $request)
    {
        $request->validate([
            'journey_id' => 'required|integer|exists:journeys,id',
            'attempt_id' => 'sometimes|integer|exists:journey_attempts,id',
        ]);

        $user = Auth::user();
        
        $journeyId = $request->journey_id;
        $attemptId = $request->attempt_id;
        //broadcast(new ChatChunk($attemptId,'system', 'Checking start status'));
        $attempt = JourneyAttempt::where('id', $attemptId)
            ->where('user_id', $user->id)
            ->first();

        //Lets see do we have a response already
        $existingResponse = JourneyStepResponse::where('journey_attempt_id', $attemptId)
            ->first();
        if (!$existingResponse) {
            $journeyStep = JourneyStep::where('journey_id', $journeyId)
                ->where('order', 1)
                ->first();
            //I need to broadcast to add new text which will hold ai response.
           
            //We need to create a new response step
            $newResponse = new JourneyStepResponse();
            $newResponse->journey_attempt_id = $attemptId;
            $newResponse->journey_step_id = $journeyStep->id;
            $newResponse->interaction_type = 'chat';
            $newResponse->submitted_at = time();
            $newResponse->created_at = time();
            $newResponse->updated_at = time();
            $newResponse->save();
            broadcast(new ChatChunk($attemptId, 'addaibubble', $newResponse->id, $newResponse->id));
            $service = new \App\Services\OpenAIChatService($attemptId);
            $service->makeStreamingRequest($newResponse->id);
        }
        if ($attempt->status == 'completed') {
            //Journey is already completed
            return response()->json([
                'status' => 'chat_complete '
            ], 200);
        }
        return response()->json([
            'status' => 'chat_continue '
        ], 200);
    }

    /*
    public function startChat(Request $request)
    {
        // Validate the request
        
        $request->validate([
            'journey_id' => 'required|integer|exists:journeys,id',
            'attempt_id' => 'sometimes|integer|exists:journey_attempts,id',
            'variables' => 'sometimes|array'
        ]);

        $user = Auth::user();
        $journeyId = $request->journey_id;
        $attemptId = $request->attempt_id;

        try {
            $attempt = null;
            
            // If attempt_id is provided, use the existing attempt
            if ($attemptId) {
                $attempt = JourneyAttempt::where('id', $attemptId)
                    ->where('user_id', $user->id)
                    ->first();
                    
                if (!$attempt) {
                    return response()->json([
                        'error' => 'Journey attempt not found or access denied'
                    ], 404);
                }
                
            }
            
            // If no valid attempt found, create a new one (for preview mode)
            if (!$attempt) {
                // Prefer standard input path
                $variables = $request->input('variables', []);
                // Fallback: parse raw JSON body if empty
                if (empty($variables)) {
                    try {
                        $raw = json_decode($request->getContent(), true);
                        if (isset($raw['variables']) && is_array($raw['variables'])) {
                            $variables = $raw['variables'];
                        }
                    } catch (\Throwable $e) {
                        // ignore parse errors
                    }
                }
                // Handle optional variables_json (stringified)
                if (empty($variables) && $request->filled('variables_json')) {
                    $decoded = json_decode($request->input('variables_json'), true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                        $variables = $decoded;
                    }
                }
                // Handle stringified JSON variables
                if (is_string($variables)) {
                    $decoded = json_decode($variables, true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                        $variables = $decoded;
                    }
                }
                // Final guard: ensure associative array (not object) and only scalar/stringable values
                if (!is_array($variables)) {
                    $variables = [];
                }
                foreach ($variables as $k => $v) {
                    if (is_array($v) || is_object($v)) {
                        $variables[$k] = json_encode($v);
                    }
                }

                // Get the journey
                $journey = Journey::findOrFail($journeyId);

                // If variables are missing/empty, build from user profile values
                if (empty($variables)) {
                    $profileVars = [];
                    try {
                        $profileFields = \App\Models\ProfileField::where('is_active', true)->get();
                        foreach ($profileFields as $pf) {
                            $val = $pf->getValueForUser($user->id);
                            if ($val !== null && $val !== '') {
                                $profileVars[$pf->short_name] = $val;
                            }
                        }
                        // Reduce to variables actually referenced in master prompt (if any)
                        $mpVars = [];
                        if (!empty($journey->master_prompt) && preg_match_all('/\{([a-zA-Z0-9_]+)\}/', $journey->master_prompt, $m)) {
                            $mpVars = array_values(array_unique($m[1]));
                        }
                        if (!empty($mpVars)) {
                            $variables = array_intersect_key($profileVars, array_flip($mpVars));
                        } else {
                            $variables = $profileVars; // fall back to all
                        }
                        Log::info('StartChat variables fallback used', ['user_id' => $user->id, 'count' => count($variables)]);
                    } catch (\Throwable $e) {
                        Log::warning('StartChat variables fallback failed', ['error' => $e->getMessage()]);
                    }
                }

                // Create a new journey attempt marked as preview
                $attempt = JourneyAttempt::create([
                    'user_id' => $user->id,
                    'journey_id' => $journeyId,
                    'journey_type' => 'preview', // Mark as preview
                    'status' => 'in_progress',
                    'mode' => 'chat',
                    'started_at' => now(),
                    'current_step' => 1,
                    'progress_data' => [
                        'variables' => $variables,
                        'is_preview' => true, // Mark this as a preview/simulation
                        'preview_started_at' => now()->toISOString()
                    ]
                ]);
                
                Log::info('StartChat created new preview attempt', [
                    'attempt_id' => $attempt->id,
                    'journey_id' => $journeyId,
                    'user_id' => $user->id
                ]);
            }

            // Get the journey and current step
            $journey = $attempt->journey;
            $currentStep = $journey->steps()->where('order', $attempt->current_step)->first();
            
            if (!$currentStep) {
                $currentStep = $journey->steps()->orderBy('order')->first();
            }

            return response()->stream(function () use ($attempt, $journey, $currentStep) {
                // AGGRESSIVE production buffering fixes
                while (ob_get_level()) {
                    ob_end_clean();
                }
                
                // Force disable all possible buffering layers
                if (function_exists('apache_setenv')) {
                    apache_setenv('no-gzip', '1');
                    apache_setenv('no-brotli', '1');
                }
                
                // Production-specific headers for immediate streaming
                header('X-Accel-Buffering: no'); // Nginx
                header('X-Output-Buffering: off'); // Apache  
                header('X-Sendfile-Type: X-Accel-Redirect'); // Force no sendfile
                
                header('Connection: keep-alive');
                header('Keep-Alive: timeout=300, max=1000');
                
                // Prevent CDN/Load Balancer buffering
                header('Cache-Control: no-cache, no-store, must-revalidate, no-transform');
                header('Pragma: no-cache');
                header('Expires: 0');
                header('Vary: Accept-Encoding');
                
                // AWS ALB specific headers
                header('X-ALB-Classification-Response: no-cache');
                
                // Send minimal initial chunk to establish connection
                echo "data: " . json_encode(['type' => 'connection', 'status' => 'established']) . "\n\n";
                if (function_exists('ob_flush')) { @ob_flush(); }
                flush();
                
                // Shorter initial delay for production
                usleep(100000); // 0.1 seconds instead of 0.5
                
                // Send initial metadata with comprehensive step information
                $attemptCount = \App\Models\JourneyAttempt::where('journey_id', $journey->id)
                    ->where('user_id', auth()->id())
                    ->count();
                
                echo "data: " . json_encode([
                    'step_id' => $currentStep?->id ?? 1,
                    'step_order' => $currentStep?->order ?? 1,
                    'step_title' => $currentStep?->title ?? 'Step 1',
                    'attempt_id' => $attempt->id,
                    'current_step' => $attempt->current_step,
                    'total_steps' => $journey->steps()->count(),
                    'attempt_count' => $attemptCount,
                    'total_attempts' => $currentStep?->maxattempts ?? 3,
                    'type' => 'metadata'
                ]) . "\n\n";
                if (function_exists('ob_flush')) { @ob_flush(); }
                flush();

                // Generate initial AI response using the new PromptBuilderService
                $initialPrompt = $this->promptBuilderService->getChatPrompt($attempt->id);
                
                // Call OpenAI API for initial response
                $startTime = microtime(true);
                $aiResponse = $this->generateAIResponse($initialPrompt, 0.8, [
                    'journey_attempt_id' => $attempt->id,
                ]);
                $processingTime = (microtime(true) - $startTime) * 1000;
                
                $initialResponse = $aiResponse['text'] ?? "Welcome to '{$journey->title}'! Let's begin your learning journey.";
                if (!is_string($initialResponse) || trim($initialResponse) === '') {
                    $initialResponse = "Welcome to '{$journey->title}'! Let's begin your learning journey.";
                }

                // Create the first step response record
                if ($currentStep) {
                    $stepResponse = JourneyStepResponse::create([
                        'journey_attempt_id' => $attempt->id,
                        'journey_step_id' => $currentStep->id,
                        'user_input' => null, // No user input for initial message
                        'ai_response' => $initialResponse,
                        'interaction_type' => 'initial',
                        'ai_metadata' => [
                            'variables_used' => $attempt->progress_data['variables'] ?? [],
                            'processed_prompt' => $initialPrompt,
                            'is_preview' => $attempt->progress_data['is_preview'] ?? false
                        ],
                        'submitted_at' => now()
                    ]);

                    // Log the initial prompt and response
                    $this->logInitialPromptAndResponse($attempt, $stepResponse, $initialPrompt, $initialResponse, $aiResponse);
                }

                // Debug: Log what we're about to send
                Log::info('StartChat initial response', [
                    'attempt_id' => $attempt->id,
                    'len' => strlen($initialResponse)
                ]);
                
                // Send response in smaller chunks with faster intervals for production
                $chunks = str_split($initialResponse, 8); // Smaller chunks for better streaming
                Log::info('StartChat chunk count', [
                    'attempt_id' => $attempt->id,
                    'chunks' => count($chunks)
                ]);
                
                foreach ($chunks as $index => $chunk) {
                    echo "data: " . json_encode([
                        'text' => $chunk,
                        'type' => 'chunk',
                        'index' => $index
                    ]) . "\n\n";
                    if (function_exists('ob_flush')) { @ob_flush(); }
                    flush();
                    usleep(80000); // 0.08 seconds between chunks - faster for production
                }

                // Send completion signal
                echo "data: " . json_encode(['type' => 'done']) . "\n\n";
                flush();
            }, 200, [
                'Content-Type' => 'text/event-stream',
                'Cache-Control' => 'no-cache, no-store, must-revalidate',
                'Connection' => 'keep-alive',
                'X-Accel-Buffering' => 'no',
                'X-Output-Buffering' => 'off',
                'Pragma' => 'no-cache',
                'Expires' => '0',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to start chat: ' . $e->getMessage()
            ], 500);
        }
    }*/

    public function chatSubmit(Request $request)
    {
        // Validate the request - removed step_id as backend determines this
        $request->validate([
            'attempt_id' => 'required|integer|exists:journey_attempts,id',
            'user_input' => 'required|string'
        ]);

        $user = Auth::user();
        $userInput = $request->user_input;
        $attemptId = $request->attempt_id;
        $attempt = JourneyAttempt::where('id', $attemptId)
                ->where('user_id', $user->id)
                ->firstOrFail();

        if ($attempt->status != 'in_progress') {
            return response()->json([
                'error' => 'chat_done.'
            ], 400);
        }
        try {
            DB::beginTransaction();
            // Get the attempt and verify ownership
            
            // Get the journey and current step based on attempt progress
            $journey = $attempt->journey;
            // Always use the attempt's current step - backend is the source of truth
            $currentStep = $journey->steps()
                ->where('order', $attempt->current_step)
                ->first();
            if (!$currentStep) {
                throw new \Exception('Current step not found - attempt is on step ' . $attempt->current_step . ' but no step exists with that order');
            }
            $journeyStep = JourneyStep::where('journey_id', $journey->id)
                ->where('order', $attempt->current_step)
                ->first();
            if (!$journeyStep) {
                throw new \Exception('No journey step found for this attempt and current step.');
            }
            $journeyStepsCount = $journey->steps()->orderBy('order')->count();
            $journeyStepResponse = JourneyStepResponse::where('journey_attempt_id', $attempt->id)
                ->orderBy('submitted_at', 'desc')
                ->first();
            
            if (!$journeyStepResponse) {
                throw new \Exception('No journey step response found for this attempt.');
            }
            
            if ($journeyStepResponse->user_input) {
                throw new \Exception('This journey step response has already been processed.');
            }
            $journeyStepResponse->user_input = $userInput;
            $journeyStepResponse->updated_at = time();
            $journeyStepResponse->save();
            $messages = $this->promptBuilderService->getFullContext($attempt->id, 'rate',false);
            $context = [
                'journey_attempt_id' => $attempt->id,
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
            $currentAttemptNumber = JourneyStepResponse::where('journey_attempt_id', $attempt->id)
                ->where('journey_step_id', $journeyStep->id)
                ->count();
            $passedRating = $rate >= (int) $journeyStep->ratepass;
            $maxAttemptsReached = $journeyStep->maxattempts !== null
                ? ($currentAttemptNumber === (int) $journeyStep->maxattempts)
                : false;

            // Check if there is a next step
            $hasNextStep = JourneyStep::where('journey_id', $attempt->journey_id)
                ->where('order', '>', $journeyStep->order)
                ->orderBy('order', 'asc')
                ->first();
            
            if ($passedRating || $maxAttemptsReached) {
                $stepAction = $hasNextStep ? 'next_step' : 'finish_journey';
            } else {
                $stepAction = 'retry_step';
                $hasNextStep = null;
            }
             if ($stepAction == 'retry_step') {
                $nextstepresponse = new JourneyStepResponse();
                $nextstepresponse->journey_attempt_id = $attempt->id;
                $nextstepresponse->journey_step_id = $journeyStep->id;
                $nextstepresponse->interaction_type = 'chat';
                $nextstepresponse->submitted_at = time();
                $nextstepresponse->created_at = time();
                $nextstepresponse->updated_at = time();
                $nextstepresponse->save();
                $attemptcount = \App\Models\JourneyStepResponse::where('journey_attempt_id', $attempt->id)
                    ->where('journey_step_id', $journeyStep->id)
                    ->count();
            }
            else {
                $attemptcount = 0;
                if ($stepAction === 'next_step') {
                    $attempt->current_step = $journeyStep->order + 1;
                    $attempt->save();
                    $nextstepresponse = new JourneyStepResponse();
                    $nextstepresponse->journey_attempt_id = $attempt->id;
                    $nextstepresponse->journey_step_id = ($hasNextStep ? $hasNextStep->id : $journeyStep->id);
                    $nextstepresponse->interaction_type = 'chat';
                    $nextstepresponse->submitted_at = time();
                    $nextstepresponse->created_at = time();
                    $nextstepresponse->updated_at = time();
                    $nextstepresponse->save();

                } elseif ($stepAction === 'finish_journey') {

                    $attempt->status = 'completed';
                    $attempt->completed_at = now();
                    $attempt->save();
                    //We need to recreate same step for AI to close discusssion
                    $nextstepresponse = new JourneyStepResponse();
                    $nextstepresponse->journey_attempt_id = $attempt->id;
                    $nextstepresponse->journey_step_id = $journeyStep->id;
                    $nextstepresponse->interaction_type = 'chat';
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
            $progress = number_format((($attempt->current_step - 1) / $journeyStepsCount * 100), 2);
            broadcast(new ChatChunk($attemptId, 'progress', $progress, $progress));
            // Update attempt progress based on action
            if ($stepAction === 'next_step') {
                $attempt->current_step = $journeyStep->order + 1;
                // Keep status as in_progress if not explicitly set
                if (!$attempt->status || $attempt->status === 'pending') {
                    $attempt->status = 'in_progress';
                }
                $attempt->save();
            } elseif ($stepAction === 'finish_journey') {
                $attempt->status = 'completed';
                $attempt->completed_at = now();
                $attempt->save();
            }
            broadcast(new ChatChunk($attemptId, 'addaibubble', $nextstepresponse->id, $nextstepresponse->id));
            $service = new \App\Services\OpenAIChatService($attemptId);
            $service->makeStreamingRequest($nextstepresponse->id);
            //Check if we need to finish journey
            $lastjourneystepresponse = JourneyStepResponse::where('journey_attempt_id', $attempt->id)
                ->orderBy('submitted_at', 'desc')
                ->first();
            $lastStep = JourneyStep::where('journey_id', $attempt->journey_id)
                ->orderBy('order', 'desc')
                ->first();
            
            if ($lastjourneystepresponse && $lastStep && $lastjourneystepresponse->journey_step_id == $lastStep->id) {
                $attempt->status = 'completed';
                $attempt->completed_at = now();
                $attempt->save();
                $stepAction = 'finish_journey';
                $lastjourneystepresponse->step_action = 'finish_journey';
                $lastjourneystepresponse->save();
            }
            DB::commit();
            return response()->json([
                'joruney_status' =>  $stepAction
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'error' => 'Failed to process chat: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate AI response using the AIInteractionService
     */
    private function generateAIResponse($prompt, $temperature = 0.7, array $context = [])
    {
        try {
            $startTime = microtime(true);
            
            // Call the AI service (now passing context for logging)
            $response = $this->aiService->generateResponse(
                $prompt,
                $temperature,
                config('openai.default_model', 'gpt-4'),
                config('openai.max_tokens', 2048),
                $context
            );
            
            $endTime = microtime(true);
            $processingTime = ($endTime - $startTime) * 1000; // Convert to milliseconds
            
            // Extract the text content from the response
            $responseText = '';
            if (isset($response['content'])) {
                $responseText = $response['content'];
            } elseif (isset($response['choices'][0]['message']['content'])) {
                $responseText = $response['choices'][0]['message']['content'];
            } elseif (isset($response['choices'][0]['text'])) {
                $responseText = $response['choices'][0]['text'];
            }
            
            return [
                'text' => trim($responseText),
                'processing_time' => $processingTime,
                'model' => $response['model'] ?? config('openai.default_model', 'gpt-4'),
                'usage' => $response['usage'] ?? ['total_tokens' => 0, 'prompt_tokens' => 0, 'completion_tokens' => 0],
                'full_response' => $response
            ];
            
        } catch (\Exception $e) {
            Log::error('AI Response Generation Failed', [
                'error' => $e->getMessage(),
                'prompt_length' => strlen($prompt)
            ]);
            
            // For testing: Check if this is a rating request and provide appropriate fallback
            if (strpos($prompt, 'rate the following student response') !== false) {
                return [
                    'text' => "3", // Default rating
                    'processing_time' => 500,
                    'model' => 'fallback',
                    'usage' => ['total_tokens' => 10, 'prompt_tokens' => 5, 'completion_tokens' => 5],
                    'full_response' => null
                ];
            }
            
            // Return a fallback response
            return [
                'text' => "Thank you for your response! I understand what you're sharing about critical thinking. Let me provide some feedback: Critical thinking is indeed a valuable skill that helps us analyze information more effectively. Based on your input, you're showing interest in learning more about this topic. Let me guide you further in understanding how critical thinking can be applied in your field of Computer Science and in everyday decision-making.",
                'processing_time' => 500,
                'model' => 'fallback',
                'usage' => ['total_tokens' => 50, 'prompt_tokens' => 25, 'completion_tokens' => 25],
                'full_response' => null
            ];
        }
    }

   

    /**
     * Estimate token count for text (rough approximation)
     * Replace this with actual tokenizer when integrating with real AI services
     */
    private function estimateTokens($text)
    {
        // Rough estimation: ~4 characters per token for English text
        // This is a very basic estimation - real AI services provide exact token counting
        return max(1, (int) ceil(strlen($text) / 4));
    }


    /**
     * Get current prompt for testing purposes
     *
     * @param int $journeyAttemptId
     * @param string $type Either 'chat' or 'rate'
     * @return \Illuminate\Http\JsonResponse
     */
    public function getCurrentPrompt(int $journeyAttemptId, string $type)
    {
        try {
            // Validate type parameter
            if (!in_array($type, ['chat', 'rate'])) {
                return response()->json([
                    'error' => 'Invalid type. Must be either "chat" or "rate".'
                ], 400);
            }

            // Verify the journey attempt exists and user has access
            $attempt = JourneyAttempt::findOrFail($journeyAttemptId);
            
            // Check if user owns this attempt or has admin privileges
            $user = Auth::user();
            if ($attempt->user_id !== $user->id && !in_array($user->role, ['admin', 'administrator', 'institution'])) {
                return response()->json([
                    'error' => 'Unauthorized access to this journey attempt.'
                ], 403);
            }

            // Get the appropriate prompt
            if ($type === 'chat') {
                $prompt = $this->promptBuilderService->getChatPrompt($journeyAttemptId);
            } else {
                $prompt = $this->promptBuilderService->getRatePrompt($journeyAttemptId);
            }

            return response()->json([
                'success' => true,
                'type' => $type,
                'journey_attempt_id' => $journeyAttemptId,
                'prompt' => $prompt, // Clean prompt with proper line breaks
                'prompt_lines' => explode("\n", $prompt), // Split into array of lines for better readability
                'prompt_preview' => substr($prompt, 0, 200) . '...', // First 200 characters for quick preview
                'generated_at' => now()->toISOString()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to get prompt: ' . $e->getMessage()
            ], 500);
        }
    }

}
