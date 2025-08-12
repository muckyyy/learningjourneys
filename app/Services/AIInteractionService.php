<?php

namespace App\Services;

use App\Models\JourneyDebug;
use App\Models\JourneyStep;
use App\Models\JourneyStepResponse;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class AIInteractionService
{
    /**
     * Process AI interaction for a journey step
     */
    public function processStepInteraction(
        JourneyStep $step,
        User $user,
        string $userInput,
        JourneyStepResponse $stepResponse,
        array $options = []
    ): array {
        $startTime = microtime(true);
        $debugEntry = null;

        try {
            // Build the prompt using step configuration and user input
            $prompt = $this->buildPrompt($step, $user, $userInput, $options);
            
            // Create debug entry for tracking
            $debugEntry = $this->createDebugEntry($stepResponse, $step, $user, [
                'debug_type' => 'ai_interaction',
                'prompt_sent' => $prompt,
                'status' => 'processing',
                'ai_model' => $options['ai_model'] ?? 'gpt-3.5-turbo',
            ]);

            // Simulate AI API call (replace with actual AI service call)
            $aiResponse = $this->callAIService($prompt, $options);
            
            $processingTime = microtime(true) - $startTime;

            // Update debug entry with response data
            $this->updateDebugEntry($debugEntry, [
                'ai_response_received' => $aiResponse['content'] ?? '',
                'request_data' => [
                    'prompt' => $prompt,
                    'model' => $options['ai_model'] ?? 'gpt-3.5-turbo',
                    'temperature' => $options['temperature'] ?? 0.7,
                    'max_tokens' => $options['max_tokens'] ?? 1000,
                ],
                'response_data' => $aiResponse,
                'request_tokens' => $aiResponse['usage']['prompt_tokens'] ?? 0,
                'response_tokens' => $aiResponse['usage']['completion_tokens'] ?? 0,
                'total_tokens' => $aiResponse['usage']['total_tokens'] ?? 0,
                'cost' => $this->calculateCost($aiResponse['usage'] ?? [], $options['ai_model'] ?? 'gpt-3.5-turbo'),
                'processing_time' => $processingTime,
                'status' => 'success',
            ]);

            // Update step response with AI data
            $stepResponse->update([
                'user_input' => $userInput,
                'ai_response' => $aiResponse['content'] ?? '',
                'interaction_type' => $options['interaction_type'] ?? 'text',
                'ai_metadata' => [
                    'model' => $options['ai_model'] ?? 'gpt-3.5-turbo',
                    'tokens_used' => $aiResponse['usage']['total_tokens'] ?? 0,
                    'processing_time' => $processingTime,
                    'timestamp' => now()->toISOString(),
                ],
            ]);

            return [
                'success' => true,
                'ai_response' => $aiResponse['content'] ?? '',
                'tokens_used' => $aiResponse['usage']['total_tokens'] ?? 0,
                'cost' => $this->calculateCost($aiResponse['usage'] ?? [], $options['ai_model'] ?? 'gpt-3.5-turbo'),
                'processing_time' => $processingTime,
                'debug_id' => $debugEntry->id,
            ];

        } catch (\Exception $e) {
            $processingTime = microtime(true) - $startTime;

            // Log error to debug entry
            if ($debugEntry) {
                $this->updateDebugEntry($debugEntry, [
                    'status' => 'error',
                    'error_message' => $e->getMessage(),
                    'processing_time' => $processingTime,
                ]);
            }

            Log::error('AI Interaction failed', [
                'step_id' => $step->id,
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'processing_time' => $processingTime,
                'debug_id' => $debugEntry ? $debugEntry->id : null,
            ];
        }
    }

    /**
     * Build the AI prompt using step configuration and context
     */
    private function buildPrompt(JourneyStep $step, User $user, string $userInput, array $options = []): string
    {
        $journey = $step->journey;
        $masterPrompt = $journey->master_prompt ?? '';

        // Replace variables in the master prompt
        $prompt = $this->replacePromptVariables($masterPrompt, [
            'journey_title' => $journey->title,
            'journey_description' => $journey->description,
            'student_firstname' => $user->name, // Assuming name field contains first name
            'student_lastname' => '', // Add if you have separate last name field
            'student_email' => $user->email,
            'institution_name' => $user->institution->name ?? 'Unknown Institution',
            'current_step' => $step->title . ': ' . $step->content,
            'user_input' => $userInput,
            'step_type' => $step->type,
            'step_order' => $step->order,
        ]);

        return $prompt;
    }

    /**
     * Replace variables in prompt template
     */
    private function replacePromptVariables(string $template, array $variables): string
    {
        foreach ($variables as $key => $value) {
            $template = str_replace('{' . $key . '}', $value, $template);
        }
        return $template;
    }

    /**
     * Simulate AI service call (replace with actual implementation)
     */
    private function callAIService(string $prompt, array $options = []): array
    {
        // This is a mock response - replace with actual AI service integration
        // For example: OpenAI API, Azure OpenAI, etc.
        
        $simulatedResponse = [
            'content' => $this->generateMockAIResponse($prompt),
            'usage' => [
                'prompt_tokens' => strlen($prompt) / 4, // Rough estimate
                'completion_tokens' => 150,
                'total_tokens' => (strlen($prompt) / 4) + 150,
            ],
            'model' => $options['ai_model'] ?? 'gpt-3.5-turbo',
            'created' => time(),
        ];

        // Simulate processing delay
        usleep(100000); // 0.1 seconds

        return $simulatedResponse;
    }

    /**
     * Generate a mock AI response for testing
     */
    private function generateMockAIResponse(string $prompt): string
    {
        $responses = [
            '<div class="ainode-reflection">Thank you for sharing your thoughts on this topic. I can see you\'ve put consideration into your response.</div><div class="ainode-teaching">This concept connects to broader principles in the field. Let me help you explore the deeper implications and how this knowledge can be applied in real-world scenarios.</div><div class="ainode-task">For your next reflection, consider how this concept might apply to a situation you\'ve experienced personally. Can you think of an example where this principle would be useful?</div>',
            
            '<div class="ainode-reflection">Your understanding shows good progress in grasping these fundamental concepts.</div><div class="ainode-teaching">Building on what you\'ve shared, let\'s explore how this connects to other areas of study. This foundational knowledge will serve you well as we move to more complex topics.</div><div class="ainode-task">I\'d like you to think about a specific scenario where you could apply this knowledge. What would be the first step you\'d take in implementing this concept?</div>',
            
            '<div class="ainode-reflection">I appreciate the thoughtful approach you\'ve taken to this problem.</div><div class="ainode-teaching">Your response demonstrates good analytical thinking. Let\'s dive deeper into the nuances of this topic and examine some edge cases that might challenge our initial assumptions.</div><div class="ainode-task">For our next discussion point, consider what might happen if we change one key variable in this scenario. How do you think that would affect the outcome?</div>',
        ];

        return $responses[array_rand($responses)];
    }

    /**
     * Calculate estimated cost based on token usage and model
     */
    private function calculateCost(array $usage, string $model): float
    {
        // Pricing as of 2025 (these are example rates - update with actual pricing)
        $pricing = [
            'gpt-4' => ['input' => 0.00003, 'output' => 0.00006],
            'gpt-3.5-turbo' => ['input' => 0.0000015, 'output' => 0.000002],
            'gpt-4-turbo' => ['input' => 0.00001, 'output' => 0.00003],
        ];

        $rates = $pricing[$model] ?? $pricing['gpt-3.5-turbo'];
        
        $inputCost = ($usage['prompt_tokens'] ?? 0) * $rates['input'];
        $outputCost = ($usage['completion_tokens'] ?? 0) * $rates['output'];
        
        return $inputCost + $outputCost;
    }

    /**
     * Create a debug entry for tracking AI interactions
     */
    private function createDebugEntry(
        JourneyStepResponse $stepResponse,
        JourneyStep $step,
        User $user,
        array $data
    ): JourneyDebug {
        return JourneyDebug::create([
            'journey_step_response_id' => $stepResponse->id,
            'journey_step_id' => $step->id,
            'user_id' => $user->id,
            ...$data,
        ]);
    }

    /**
     * Update debug entry with additional data
     */
    private function updateDebugEntry(JourneyDebug $debugEntry, array $data): void
    {
        $debugEntry->update($data);
    }

    /**
     * Get debug information for a step response
     */
    public function getDebugInfo(JourneyStepResponse $stepResponse): array
    {
        $debugEntries = $stepResponse->debugEntries()->latest()->get();
        
        return [
            'total_entries' => $debugEntries->count(),
            'total_tokens' => $debugEntries->sum('total_tokens'),
            'total_cost' => $debugEntries->sum('cost'),
            'avg_processing_time' => $debugEntries->avg('processing_time'),
            'success_rate' => $debugEntries->where('status', 'success')->count() / max($debugEntries->count(), 1),
            'entries' => $debugEntries->toArray(),
        ];
    }
}
