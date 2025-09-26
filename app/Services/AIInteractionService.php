<?php

namespace App\Services;

use App\Models\JourneyDebug;
use App\Models\JourneyStep;
use App\Models\JourneyStepResponse;
use App\Models\JourneyAttempt;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use OpenAI;

class AIInteractionService
{
    protected $openAI;

    public function __construct()
    {
        // Create HTTP client with custom options for SSL handling
        $httpClient = \Http\Discovery\Psr18ClientDiscovery::find();
        
        // For Windows/XAMPP SSL issues, create client with SSL verification disabled
        if (config('openai.http_options.verify') === false) {
            $httpClient = new \GuzzleHttp\Client([
                'verify' => false,
                'timeout' => config('openai.timeout', 30),
            ]);
        }

        $this->openAI = OpenAI::factory()
            ->withApiKey(config('openai.api_key'))
            ->withHttpClient($httpClient)
            ->make();
    }

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
     * Public method to call AI service directly
     */
    public function generateResponse(string $prompt, float $temperature = 0.7, string $model = null, int $maxTokens = null): array
    {
        $options = [
            'temperature' => $temperature,
            'ai_model' => $model ?? config('openai.default_model', 'gpt-4'),
            'max_tokens' => $maxTokens ?? config('openai.max_tokens', 2000)
        ];
        
        return $this->callAIService($prompt, $options);
    }

    /**
     * Call OpenAI API service
     */
    private function callAIService(string $prompt, array $options = []): array
    {
        try {
            $model = $options['ai_model'] ?? config('openai.default_model', 'gpt-4');
            $temperature = $options['temperature'] ?? config('openai.temperature', 0.7);
            $maxTokens = $options['max_tokens'] ?? config('openai.max_tokens', 2000);

            $response = $this->openAI->chat()->create([
                'model' => $model,
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'You are an intelligent learning assistant helping students with their educational journey. Always respond in a structured format using HTML div tags with specific classes: <div class="ainode-reflection"> for acknowledging student input, <div class="ainode-teaching"> for providing educational content, and <div class="ainode-task"> for giving next steps or questions.',
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt,
                    ],
                ],
                'temperature' => $temperature,
                'max_tokens' => $maxTokens,
            ]);

            return [
                'content' => $response->choices[0]->message->content,
                'usage' => [
                    'prompt_tokens' => $response->usage->promptTokens,
                    'completion_tokens' => $response->usage->completionTokens,
                    'total_tokens' => $response->usage->totalTokens,
                ],
                'model' => $response->model,
                'created' => $response->created,
            ];

        } catch (\Exception $e) {
            Log::error('OpenAI API Error', [
                'error' => $e->getMessage(),
                'prompt_length' => strlen($prompt),
                'model' => $model ?? 'unknown',
            ]);

            // Fallback to mock response in case of API failure
            return $this->getMockResponse($prompt, $options);
        }
    }

    /**
     * Get mock response as fallback
     */
    private function getMockResponse(string $prompt, array $options = []): array
    {
        $mockContent = $this->generateMockAIResponse($prompt);
        
        return [
            'content' => $mockContent,
            'usage' => [
                'prompt_tokens' => strlen($prompt) / 4, // Rough estimate
                'completion_tokens' => strlen($mockContent) / 4,
                'total_tokens' => (strlen($prompt) / 4) + (strlen($mockContent) / 4),
            ],
            'model' => $options['ai_model'] ?? 'mock-gpt-3.5-turbo',
            'created' => time(),
        ];
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

    public function executeChatRequest($messages){

        if (!is_array($messages) || count($messages) == 0) {
            throw new \Exception('Messages must be a non-empty array.');
        }

        return $this->openAI->chat()->create([
            'model' => config('openai.default_model', 'gpt-4'),
            'messages' => $messages,
            'temperature' => floatval(config('openai.temperature', 0.7)),
            'max_tokens' => intval(config('openai.max_tokens', 2000)),
        ]);
    }
}
