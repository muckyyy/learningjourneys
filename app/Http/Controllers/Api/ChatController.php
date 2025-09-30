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
