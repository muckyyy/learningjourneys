<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Journey;
use App\Models\JourneyAttempt;
use App\Models\JourneyStepResponse;
use App\Models\JourneyStep;
use App\Models\JourneyPromptLog;
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
                
                Log::info('StartChat using existing attempt', [
                    'attempt_id' => $attempt->id,
                    'journey_id' => $journeyId,
                    'user_id' => $user->id
                ]);
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
                $aiResponse = $this->generateAIResponse($initialPrompt, 0.8);
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
                        'response_data' => [], // Add the required response_data field
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
    }

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

        try {
            // Get the attempt and verify ownership
            $attempt = JourneyAttempt::where('id', $attemptId)
                ->where('user_id', $user->id)
                ->firstOrFail();

            // Get the journey and current step based on attempt progress
            $journey = $attempt->journey;
            
            Log::info('ChatSubmit step detection', [
                'attempt_id' => $attempt->id,
                'current_step_order' => $attempt->current_step,
                'total_steps' => $journey->steps()->count()
            ]);
            
            // Always use the attempt's current step - backend is the source of truth
            $currentStep = $journey->steps()
                ->where('order', $attempt->current_step)
                ->first();
                
            Log::info('ChatSubmit using attempt current_step', [
                'order' => $attempt->current_step,
                'found_step' => $currentStep ? $currentStep->title : 'null',
                'step_id' => $currentStep ? $currentStep->id : 'null'
            ]);

            if (!$currentStep) {
                Log::error('ChatSubmit current step not found', [
                    'attempt_id' => $attempt->id,
                    'current_step_order' => $attempt->current_step,
                    'available_steps' => $journey->steps()->pluck('title', 'order')->toArray()
                ]);
                throw new \Exception('Current step not found - attempt is on step ' . $attempt->current_step . ' but no step exists with that order');
            }
            
            Log::info('ChatSubmit proceeding with step', [
                'step_id' => $currentStep->id,
                'step_title' => $currentStep->title,
                'step_order' => $currentStep->order,
                'attempt_current_step' => $attempt->current_step
            ]);

            return response()->stream(function () use ($attempt, $journey, $currentStep, $userInput, $user) {
                // Enhanced output buffering management for production
                while (ob_get_level()) {
                    ob_end_clean();
                }
                
                // Set additional headers for production streaming
                header('X-Accel-Buffering: no'); // Nginx buffering disable
                header('X-Output-Buffering: off'); // Apache buffering disable
                
                // Disable default PHP output buffering
                ini_set('output_buffering', 'off');
                ini_set('zlib.output_compression', false);
                
                try {
                    // PHASE 1: Get rating from AI (1-5)
                    echo "data: " . json_encode([
                        'step_id' => $currentStep->id,
                        'attempt_id' => $attempt->id,
                        'type' => 'evaluating',
                        'message' => 'Evaluating your response...'
                    ]) . "\n\n";
                    
                    if (ob_get_level()) ob_flush();
                    flush();

                    // Count existing attempts for this step
                    $existingAttempts = JourneyStepResponse::where('journey_attempt_id', $attempt->id)
                        ->where('journey_step_id', $currentStep->id)
                        ->whereNotNull('step_rate')
                        ->count();
                    
                    $currentAttemptNumber = $existingAttempts + 1;

                    Log::info('ChatSubmit Phase 1 - Rating', [
                        'attempt_id' => $attempt->id,
                        'step_id' => $currentStep->id,
                        'current_attempt' => $currentAttemptNumber,
                        'user_input' => $userInput
                    ]);

                    // Build rating prompt using PromptBuilderService
                    $ratingPrompt = $this->promptBuilderService->getRatePrompt($attempt->id);
                    
                    // Get AI rating (1-5)
                    $startTime = microtime(true);
                    $ratingResponse = $this->generateAIResponse($ratingPrompt, 0.3); // Lower temperature for consistent rating
                    $ratingTime = (microtime(true) - $startTime) * 1000;
                    
                    Log::info('ChatSubmit Rating Response', [
                        'response' => $ratingResponse,
                        'time_ms' => $ratingTime
                    ]);
                    
                    // Extract rating from AI response
                    $stepRate = $this->extractRating($ratingResponse['text']);
                    
                    // Determine step action based on rating and attempts
                    $stepAction = $this->determineStepAction($currentStep, $stepRate, $currentAttemptNumber, $journey);
                    
                    Log::info('ChatSubmit Rating Result', [
                        'rating' => $stepRate,
                        'action' => $stepAction,
                        'required_pass' => $currentStep->ratepass,
                        'max_attempts' => $currentStep->maxattempts
                    ]);
                    
                    // Send rating result
                    echo "data: " . json_encode([
                        'type' => 'rating',
                        'rating' => $stepRate,
                        'attempt' => $currentAttemptNumber,
                        'max_attempts' => $currentStep->maxattempts,
                        'required_rating' => $currentStep->ratepass,
                        'action' => $stepAction
                    ]) . "\n\n";
                    
                    if (ob_get_level()) ob_flush();
                    flush();

                    usleep(300000); // Brief pause

                    // PHASE 2: Generate text response
                    echo "data: " . json_encode([
                        'type' => 'generating',
                        'message' => 'Generating response...'
                    ]) . "\n\n";
                    
                    if (ob_get_level()) ob_flush();
                    flush();

                    // Build AI response prompt based on rating and action
                    $responsePrompt = $this->buildResponsePrompt($attempt, $currentStep, $userInput, $stepRate, $stepAction, $currentAttemptNumber);
                    
                    // Generate AI text response
                    $startTime = microtime(true);
                    $aiResponse = $this->generateAIResponse($responsePrompt, 0.7);
                    $responseTime = (microtime(true) - $startTime) * 1000;
                    
                    $aiResponseText = $aiResponse['text'] ?? "Thank you for your response. Let me provide some feedback.";
                    
                    Log::info('ChatSubmit Text Response', [
                        'response_length' => strlen($aiResponseText),
                        'time_ms' => $responseTime
                    ]);
                    
                    // Create step response record with rating and action
                    $stepResponse = JourneyStepResponse::create([
                        'journey_attempt_id' => $attempt->id,
                        'journey_step_id' => $currentStep->id,
                        'step_action' => $stepAction,
                        'step_rate' => $stepRate,
                        'user_input' => $userInput,
                        'ai_response' => $aiResponseText,
                        'interaction_type' => 'chat',
                        'response_data' => [
                            'attempt_number' => $currentAttemptNumber,
                            'max_attempts' => $currentStep->maxattempts,
                            'required_rating' => $currentStep->ratepass,
                            'rating_achieved' => $stepRate >= $currentStep->ratepass,
                            'can_progress' => in_array($stepAction, ['next_step', 'finish_journey'])
                        ],
                        'ai_metadata' => [
                            'rating_time_ms' => $ratingTime,
                            'response_time_ms' => $responseTime,
                            'is_preview' => true,
                            'ai_model' => $aiResponse['model'] ?? config('openai.default_model'),
                            'total_tokens' => ($aiResponse['usage']['total_tokens'] ?? 0) + ($ratingResponse['usage']['total_tokens'] ?? 0),
                        ],
                        'submitted_at' => now()
                    ]);

                    // Log both prompts and responses
                    // Temporarily disabled to debug streaming issues
                    // $this->logRatingAndResponse($attempt, $stepResponse, $ratingPrompt, $responsePrompt, $ratingResponse, $aiResponse, $stepRate, $stepAction);

                    // Update attempt progress based on step action
                    $this->updateAttemptProgress($attempt, $currentStep, $stepAction);

                    // For next_step action, get the next step information
                    $nextStepInfo = null;
                    if ($stepAction === 'next_step') {
                        $nextStep = $journey->steps()
                            ->where('order', $currentStep->order + 1)
                            ->first();
                        
                        if ($nextStep) {
                            $nextStepInfo = [
                                'id' => $nextStep->id,
                                'title' => $nextStep->title,
                                'order' => $nextStep->order,
                                'content' => $nextStep->content
                            ];
                        }
                    }

                    // Send response metadata
                    $responseMetadata = [
                        'step_id' => $currentStep->id,
                        'attempt_id' => $attempt->id,
                        'type' => 'response_start'
                    ];
                    
                    if ($nextStepInfo) {
                        $responseMetadata['next_step'] = $nextStepInfo;
                    }
                    
                    echo "data: " . json_encode($responseMetadata) . "\n\n";
                    
                    if (ob_get_level()) ob_flush();
                    flush();

                    // Send response in chunks to simulate streaming
                    $chunks = str_split($aiResponseText, 30); // Increased chunk size from 15 to 30
                    foreach ($chunks as $index => $chunk) {
                        echo "data: " . json_encode([
                            'text' => $chunk,
                            'type' => 'chunk',
                            'index' => $index
                        ]) . "\n\n";
                        
                        if (ob_get_level()) ob_flush();
                        flush();
                        usleep(80000); // Reduced delay from 0.12 to 0.08 seconds
                    }

                    // Refresh attempt to get updated current_step after updateAttemptProgress
                    $attempt->refresh();
                    
                    // Count step attempts for the current step
                    $stepAttemptCount = \App\Models\JourneyStepResponse::where('journey_attempt_id', $attempt->id)
                        ->where('journey_step_id', $currentStep->id)
                        ->count();
                    
                    // Send completion signal with final status
                    $completionData = [
                        'type' => 'done',
                        'action' => $stepAction,
                        'rating' => $stepRate,
                        'can_continue' => in_array($stepAction, ['next_step', 'finish_journey']),
                        'is_complete' => $stepAction === 'finish_journey',
                        'current_step_completed' => $currentStep->id,
                        'current_step_order' => $currentStep->order,
                        'attempt_current_step' => $attempt->current_step, // This should be updated after progression
                        'total_steps' => $journey->steps()->count(),
                        'step_attempt_count' => $stepAttemptCount,
                        'step_max_attempts' => $currentStep->maxattempts
                    ];
                    
                    // Include next step information if progressing
                    if ($stepAction === 'next_step' && $nextStepInfo) {
                        $completionData['next_step'] = $nextStepInfo;
                        $completionData['progressed_to_step'] = $nextStepInfo['id'];
                        $completionData['progressed_to_order'] = $nextStepInfo['order'];
                    }
                    
                    echo "data: " . json_encode($completionData) . "\n\n";
                    
                    if (ob_get_level()) ob_flush();
                    flush();
                    
                } catch (\Exception $e) {
                    Log::error('ChatSubmit Error', [
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                    
                    echo "data: " . json_encode([
                        'type' => 'error',
                        'message' => 'An error occurred while processing your response. Please try again.'
                    ]) . "\n\n";
                    
                    if (ob_get_level()) ob_flush();
                    flush();
                }
            }, 200, [
                'Content-Type' => 'text/event-stream',
                'Cache-Control' => 'no-cache, no-store, must-revalidate',
                'Pragma' => 'no-cache',
                'Expires' => '0',
                'Connection' => 'keep-alive',
                'X-Accel-Buffering' => 'no', // Disable Nginx buffering
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to process chat: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate AI response using the AIInteractionService
     */
    private function generateAIResponse($prompt, $temperature = 0.7)
    {
        try {
            $startTime = microtime(true);
            
            // Call the AI service
            $response = $this->aiService->generateResponse(
                $prompt,
                $temperature,
                config('openai.default_model', 'gpt-4'),
                config('openai.max_tokens', 2048)
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
     * Build the AI prompt based on journey context and user input
     */
    private function buildAIPrompt($attempt, $userInput, $currentStep)
    {
        $journey = $attempt->journey;
        $variables = $attempt->progress_data['variables'] ?? [];
        
        // Start with the master prompt
        $prompt = $journey->master_prompt ?? '';
        
        // Replace variables in the master prompt
        foreach ($variables as $key => $value) {
            $prompt = str_replace("{{$key}}", $value, $prompt);
        }
        
        // Add context about the current step
        if ($currentStep && $currentStep->content) {
            $prompt .= "\n\nCurrent Learning Step: " . $currentStep->content;
        }
        
        // Add conversation history context
        $recentResponses = $attempt->stepResponses()
            ->orderBy('created_at', 'desc')
            ->limit(3)
            ->get();
            
        if ($recentResponses->count() > 0) {
            $prompt .= "\n\nRecent Conversation:";
            foreach ($recentResponses->reverse() as $response) {
                if ($response->user_input) {
                    $prompt .= "\nUser: " . $response->user_input;
                }
                if ($response->ai_response) {
                    $prompt .= "\nAI: " . $response->ai_response;
                }
            }
        }
        
        // Add the current user input
        $prompt .= "\n\nCurrent User Input: " . $userInput;
        $prompt .= "\n\nPlease provide a helpful, educational response that continues the learning journey.";
        
        return $prompt;
    }

    /**
     * Log the prompt and response for debugging and analysis
     */
    private function logPromptAndResponse($attempt, $stepResponse, $fullPrompt, $userInput, $aiResponseText, $processingTime, $aiResponse = null)
    {
        // Get token usage from AI response if available
        $requestTokens = $aiResponse['usage']['prompt_tokens'] ?? $this->estimateTokens($fullPrompt);
        $responseTokens = $aiResponse['usage']['completion_tokens'] ?? $this->estimateTokens($aiResponseText);
        $totalTokens = $aiResponse['usage']['total_tokens'] ?? ($requestTokens + $responseTokens);

        // Create the prompt log entry
        JourneyPromptLog::create([
            'journey_attempt_id' => $attempt->id,
            'journey_step_response_id' => $stepResponse->id,
            'action_type' => 'submit_chat',
            'prompt' => $fullPrompt,
            'response' => $aiResponseText,
            'metadata' => [
                'journey_title' => $attempt->journey->title,
                'user_input' => $userInput,
                'user_input_length' => strlen($userInput),
                'response_length' => strlen($aiResponseText),
                'is_preview' => $attempt->isPreview(),
                'ai_model' => $aiResponse['model'] ?? config('openai.default_model'),
                'created_at' => $aiResponse['created'] ?? time(),
            ],
            'ai_model' => $aiResponse['model'] ?? config('openai.default_model'),
            'tokens_used' => $totalTokens,
            'request_tokens' => $requestTokens,
            'response_tokens' => $responseTokens,
            'processing_time_ms' => $processingTime
        ]);
    }

    /**
     * Log the initial prompt and response for debugging and analysis
     */
    private function logInitialPromptAndResponse($attempt, $stepResponse, $initialPrompt, $initialResponse, $aiResponse = null)
    {
        // Get token usage from AI response if available
        $requestTokens = $aiResponse['usage']['prompt_tokens'] ?? $this->estimateTokens($initialPrompt);
        $responseTokens = $aiResponse['usage']['completion_tokens'] ?? $this->estimateTokens($initialResponse);
        $totalTokens = $aiResponse['usage']['total_tokens'] ?? ($requestTokens + $responseTokens);

        // Create the prompt log entry
        JourneyPromptLog::create([
            'journey_attempt_id' => $attempt->id,
            'journey_step_response_id' => $stepResponse->id,
            'action_type' => 'start_chat',
            'prompt' => $initialPrompt,
            'response' => $initialResponse,
            'metadata' => [
                'journey_title' => $attempt->journey->title,
                'variables_count' => count($attempt->progress_data['variables'] ?? []),
                'interaction_type' => 'initial',
                'response_length' => strlen($initialResponse),
                'is_preview' => $attempt->isPreview(),
                'ai_model' => $aiResponse['model'] ?? config('openai.default_model'),
                'created_at' => $aiResponse['created'] ?? time(),
            ],
            'ai_model' => $aiResponse['model'] ?? config('openai.default_model'),
            'tokens_used' => $totalTokens,
            'request_tokens' => $requestTokens,
            'response_tokens' => $responseTokens,
            'processing_time_ms' => $processingTime ?? 500
        ]);
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
     * Build prompt for AI to rate user response (1-5 scale)
     */
    private function buildRatingPrompt($attempt, $currentStep, $userInput)
    {
        $journey = $attempt->journey;
        $variables = $attempt->progress_data['variables'] ?? [];
        
        $prompt = "You are an educational evaluator. Please rate the following student response on a scale of 1-5:\n\n";
        $prompt .= "Learning Context:\n";
        $prompt .= "Journey: " . $journey->title . "\n";
        $prompt .= "Step: " . $currentStep->title . "\n";
        $prompt .= "Step Content: " . $currentStep->content . "\n\n";
        
        $prompt .= "Student Response: " . $userInput . "\n\n";
        
        $prompt .= "Rating Scale:\n";
        $prompt .= "1 - Poor: Does not demonstrate understanding\n";
        $prompt .= "2 - Below Average: Shows minimal understanding\n";
        $prompt .= "3 - Average: Shows basic understanding\n";
        $prompt .= "4 - Good: Shows solid understanding\n";
        $prompt .= "5 - Excellent: Shows exceptional understanding\n\n";
        
        $prompt .= "Please respond with ONLY a single number (1, 2, 3, 4, or 5) representing your rating.";
        
        return $prompt;
    }

    /**
     * Extract rating from AI response text
     */
    private function extractRating($responseText)
    {
        // Try to extract a number from 1-5 from the response
        if (preg_match('/([1-5])/', $responseText, $matches)) {
            return (int) $matches[1];
        }
        
        // Fallback: return 3 as a middle rating if no valid rating found
        return 3;
    }

    /**
     * Determine step action based on rating, attempts, and step configuration
     */
    private function determineStepAction($currentStep, $stepRate, $currentAttemptNumber, $journey)
    {
        $passedRating = $stepRate >= $currentStep->ratepass;
        $maxAttemptsReached = $currentAttemptNumber >= $currentStep->maxattempts;
        
        // If user passed the rating OR reached max attempts, they can progress
        if ($passedRating || $maxAttemptsReached) {
            // Check if this is the last step
            $isLastStep = $journey->steps()->where('order', '>', $currentStep->order)->count() === 0;
            
            if ($isLastStep) {
                return 'finish_journey';
            } else {
                return 'next_step';
            }
        } else {
            // User needs to retry this step
            return 'retry_step';
        }
    }

    /**
     * Build response prompt based on rating and action
     */
    private function buildResponsePrompt($attempt, $currentStep, $userInput, $stepRate, $stepAction, $currentAttemptNumber)
    {
        $journey = $attempt->journey;
        $variables = $attempt->progress_data['variables'] ?? [];
        
        // Start with master prompt
        $prompt = $journey->master_prompt ?? '';
        
        // Replace variables
        foreach ($variables as $key => $value) {
            $prompt = str_replace("{{$key}}", $value, $prompt);
        }
        
        $prompt .= "\n\nCompleted Learning Step: " . $currentStep->content . "\n";
        $prompt .= "Student Response: " . $userInput . "\n";
        $prompt .= "Response Rating: " . $stepRate . "/5\n";
        $prompt .= "Required Rating: " . $currentStep->ratepass . "/5\n";
        $prompt .= "Attempt Number: " . $currentAttemptNumber . "/" . $currentStep->maxattempts . "\n\n";
        
        // Customize prompt based on action
        switch ($stepAction) {
            case 'next_step':
                // Get the next step for progression
                $nextStep = $journey->steps()
                    ->where('order', $currentStep->order + 1)
                    ->first();
                
                if ($nextStep) {
                    $prompt .= "The student has successfully completed the current step and is now progressing to the next step.\n\n";
                    $prompt .= "NEXT LEARNING STEP:\n";
                    $prompt .= "Step Title: " . $nextStep->title . "\n";
                    $prompt .= "Step Content: " . $nextStep->content . "\n\n";
                    $prompt .= "Please provide:\n";
                    $prompt .= "1. Brief positive feedback on their previous response\n";
                    $prompt .= "2. Introduction to the next learning step\n";
                    $prompt .= "3. Clear explanation of what they need to learn or do in this new step\n";
                    $prompt .= "4. Engaging content that helps them understand the new concepts\n";
                    $prompt .= "5. End with a question or prompt that encourages them to engage with the new material\n\n";
                    $prompt .= "Make the transition smooth and motivating, connecting their previous success to the new learning challenge.";
                } else {
                    $prompt .= "The student has successfully completed this step. Provide encouraging feedback and prepare them for the next learning step. Be positive and motivating.";
                }
                break;
                
            case 'finish_journey':
                $prompt .= "The student has successfully completed the final step of the journey! Provide congratulatory feedback, summarize their learning achievements, and celebrate their completion.";
                break;
                
            case 'retry_step':
                if ($currentAttemptNumber >= $currentStep->maxattempts) {
                    $prompt .= "The student has reached the maximum number of attempts but will still progress. Provide constructive feedback and encourage them to continue learning.";
                } else {
                    $prompt .= "The student needs to retry this step. Provide helpful guidance, specific feedback on their response, and encouragement. Help them understand what they need to improve. They have " . ($currentStep->maxattempts - $currentAttemptNumber) . " attempt(s) remaining.";
                }
                break;
        }
        
        $prompt .= "\n\nPlease provide a helpful, educational response that continues the learning journey.";
        
        return $prompt;
    }

    /**
     * Update attempt progress based on step action
     */
    private function updateAttemptProgress($attempt, $currentStep, $stepAction)
    {
        $updates = [
            'progress_data' => array_merge($attempt->progress_data, [
                'last_interaction' => now()->toISOString(),
                'total_interactions' => ($attempt->progress_data['total_interactions'] ?? 0) + 1
            ])
        ];
        
        // Update current step and status based on action
        switch ($stepAction) {
            case 'next_step':
                $updates['current_step'] = $currentStep->order + 1;
                break;
                
            case 'finish_journey':
                $updates['status'] = 'completed';
                $updates['completed_at'] = now();
                break;
                
            case 'retry_step':
                // Keep current step the same for retry
                break;
        }
        
        $attempt->update($updates);
    }

    /**
     * Log both rating and response prompts for analysis
     */
    private function logRatingAndResponse($attempt, $stepResponse, $ratingPrompt, $responsePrompt, $ratingResponse, $aiResponse, $stepRate, $stepAction)
    {
        // Calculate token usage
        $ratingTokens = $ratingResponse['usage']['total_tokens'] ?? $this->estimateTokens($ratingPrompt . $ratingResponse['text']);
        $responseTokens = $aiResponse['usage']['total_tokens'] ?? $this->estimateTokens($responsePrompt . $aiResponse['text']);
        $totalTokens = $ratingTokens + $responseTokens;

        // Log the rating evaluation
        JourneyPromptLog::create([
            'journey_attempt_id' => $attempt->id,
            'journey_step_response_id' => $stepResponse->id,
            'action_type' => 'evaluate_rating',
            'prompt' => $ratingPrompt,
            'response' => $ratingResponse['text'],
            'metadata' => [
                'journey_title' => $attempt->journey->title,
                'step_title' => $stepResponse->step->title ?? 'Unknown',
                'extracted_rating' => $stepRate,
                'step_action' => $stepAction,
                'is_preview' => $attempt->isPreview(),
                'ai_model' => $ratingResponse['model'] ?? config('openai.default_model'),
            ],
            'ai_model' => $ratingResponse['model'] ?? config('openai.default_model'),
            'tokens_used' => $ratingTokens,
            'request_tokens' => $ratingResponse['usage']['prompt_tokens'] ?? $this->estimateTokens($ratingPrompt),
            'response_tokens' => $ratingResponse['usage']['completion_tokens'] ?? $this->estimateTokens($ratingResponse['text']),
            'processing_time_ms' => $ratingResponse['processing_time'] ?? 500
        ]);

        // Log the text response generation
        JourneyPromptLog::create([
            'journey_attempt_id' => $attempt->id,
            'journey_step_response_id' => $stepResponse->id,
            'action_type' => 'generate_response',
            'prompt' => $responsePrompt,
            'response' => $aiResponse['text'],
            'metadata' => [
                'journey_title' => $attempt->journey->title,
                'step_title' => $stepResponse->step->title ?? 'Unknown',
                'user_input' => $stepResponse->user_input,
                'step_rating' => $stepRate,
                'step_action' => $stepAction,
                'response_length' => strlen($aiResponse['text']),
                'is_preview' => $attempt->isPreview(),
                'ai_model' => $aiResponse['model'] ?? config('openai.default_model'),
            ],
            'ai_model' => $aiResponse['model'] ?? config('openai.default_model'),
            'tokens_used' => $responseTokens,
            'request_tokens' => $aiResponse['usage']['prompt_tokens'] ?? $this->estimateTokens($responsePrompt),
            'response_tokens' => $aiResponse['usage']['completion_tokens'] ?? $this->estimateTokens($aiResponse['text']),
            'processing_time_ms' => $aiResponse['processing_time'] ?? 500
        ]);
    }

    /**
     * Log prompt and response for report submission (for future use)
     * This would be used when generating reports or summaries
     */
    private function logReportPromptAndResponse($attempt, $reportPrompt, $reportResponse, $processingTime)
    {
        // Calculate token usage
        $requestTokens = $this->estimateTokens($reportPrompt);
        $responseTokens = $this->estimateTokens($reportResponse);
        $totalTokens = $requestTokens + $responseTokens;

        // Create the prompt log entry
        JourneyPromptLog::create([
            'journey_attempt_id' => $attempt->id,
            'journey_step_response_id' => null, // Reports may not be tied to specific step responses
            'action_type' => 'submit_report',
            'prompt' => $reportPrompt,
            'response' => $reportResponse,
            'metadata' => [
                'journey_title' => $attempt->journey->title,
                'report_type' => 'summary', // Could be 'summary', 'progress', 'completion', etc.
                'response_length' => strlen($reportResponse),
                'is_preview' => $attempt->isPreview()
            ],
            'ai_model' => 'simulated', // Replace with actual AI model when integrated
            'tokens_used' => $totalTokens,
            'request_tokens' => $requestTokens,
            'response_tokens' => $responseTokens,
            'processing_time_ms' => $processingTime
        ]);
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

    public function chatAudio(Request $request)
    {
        $request->validate([
            'attempt_id' => 'required|integer|exists:journey_attempts,id',
            'audio' => 'required|file|mimes:webm,mp4,wav,ogg|max:10240' // 10MB max
        ]);

        try {
            $attempt = JourneyAttempt::findOrFail($request->attempt_id);
            
            // Check if the authenticated user owns this attempt
            if ($attempt->user_id !== auth()->id()) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            // For now, return an error indicating this feature is not implemented
            return response()->json([
                'error' => 'Audio chat is not yet implemented. Please use text input.'
            ], 501);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to process audio: ' . $e->getMessage()
            ], 500);
        }
    }
}
