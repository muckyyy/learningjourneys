<?php

namespace App\Services;

use App\Models\Journey;
use App\Models\JourneyAttempt;
use App\Models\JourneyStep;
use App\Models\JourneyStepResponse;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class PromptBuilderService
{
    /**
     * Get both rate and chat prompts for an attempt
     *
     * @param JourneyAttempt $attempt
     * @return array Array containing 'rate' and 'chat' prompts
     */
    public function getPrompts(JourneyAttempt $attempt): array
    {
        $journey = $attempt->journey;
        $user = $attempt->user;
        
        // Build user context data
        $userData = $this->buildUserContext($attempt, $user, $journey);
        
        // Generate chat prompt (includes full context)
        $userData['expectedformat'] = $this->getTextResponseFormat();
        $chatPrompt = $this->replacePlaceholders($journey->master_prompt ?: $this->getDefaultChatPrompt(), $userData);
        
        // Generate rate prompt (for rating responses)
        $userData['expectedformat'] = $this->getRatingResponseFormat();
        $ratePrompt = $this->replacePlaceholders($journey->master_prompt ?: $this->getDefaultRatePrompt(), $userData);
        
        return [
            'rate' => $ratePrompt,
            'chat' => $chatPrompt
        ];
    }
    
    /**
     * Build user context data for prompt generation
     */
    protected function buildUserContext(JourneyAttempt $attempt, User $user, Journey $journey): array
    {
        $userData = [
            'student_name' => $user->name,
            'student_email' => $user->email,
            'institution_name' => $user->institution ? $user->institution->name : 'Unknown Institution',
            'journey_title' => $journey->title,
            'journey_description' => $journey->description,
            'language' => 'English', // Default, could be made configurable
            'segments' => $this->buildSegmentsHtml($journey),
            'currentsegment' => $this->buildCurrentSegmentPrompt($attempt),
            'nextsegment' => $this->buildNextSegmentPrompt($attempt),
            'lastjourney' => $this->getLastCompletedJourney($user->id, $journey->id),
        ];
        
        return $userData;
    }
    
    /**
     * Build HTML representation of all segments
     */
    protected function buildSegmentsHtml(Journey $journey): string
    {
        $segments = $journey->steps()->orderBy('order')->get();
        $segmentsHtml = '';
        
        foreach ($segments as $segment) {
            $segmentsHtml .= $segment->title . "<br>\n\n";
        }
        
        return $segmentsHtml;
    }
    
    /**
     * Build current segment prompt information
     */
    protected function buildCurrentSegmentPrompt(JourneyAttempt $attempt): string
    {
        $currentStep = $this->getCurrentStep($attempt);
        
        if (!$currentStep) {
            // First step
            $firstStep = $attempt->journey->steps()->orderBy('order')->first();
            if (!$firstStep) return 'No steps defined';
            
            return $this->formatSegmentPrompt($firstStep, 1, 1);
        }
        
        // Get attempt count for current step
        $attemptCount = JourneyStepResponse::where('journey_attempt_id', $attempt->id)
            ->where('journey_step_id', $currentStep->id)
            ->where('interaction_type', '!=', 'start_chat')
            ->count() + 1;
            
        return $this->formatSegmentPrompt($currentStep, $attemptCount, $currentStep->max_attempts ?: 3);
    }
    
    /**
     * Build next segment prompt information
     */
    protected function buildNextSegmentPrompt(JourneyAttempt $attempt): string
    {
        $currentStep = $this->getCurrentStep($attempt);
        
        if (!$currentStep) {
            $firstStep = $attempt->journey->steps()->orderBy('order')->first();
            $nextStep = $attempt->journey->steps()->where('order', '>', $firstStep->order ?? 0)->orderBy('order')->first();
        } else {
            $nextStep = $attempt->journey->steps()->where('order', '>', $currentStep->order)->orderBy('order')->first();
        }
        
        if (!$nextStep) {
            return 'No next segment - this is the final step';
        }
        
        return $nextStep->title . '<br>' . $nextStep->instructions . '<br>';
    }
    
    /**
     * Format segment prompt with attempt and rating information
     */
    protected function formatSegmentPrompt(JourneyStep $step, int $attemptNum, int $maxAttempts): string
    {
        $prompt = $step->title . '<br>';
        $prompt .= $step->instructions . '<br>';
        $prompt .= "Attempt: {$attemptNum} out of {$maxAttempts}<br>";
        $prompt .= 'Rating: 1 (lowest) to 5 (highest)<br>';
        $prompt .= 'Passing rate is: ' . ($step->passing_score ?: 7);
        
        return $prompt;
    }
    
    /**
     * Get current step for attempt
     */
    protected function getCurrentStep(JourneyAttempt $attempt): ?JourneyStep
    {
        $lastResponse = JourneyStepResponse::where('journey_attempt_id', $attempt->id)
            ->orderBy('submitted_at', 'desc')
            ->first();
            
        if (!$lastResponse) {
            return null;
        }
        
        return $lastResponse->step;
    }

    /**
     * Get last completed journey for user context
     */
    protected function getJourneyHistory(int $userId, int $targetJourneyId): string
    {
        $lastAttempt = JourneyAttempt::where('user_id', $userId)
            ->where('journey_id', '!=', $targetJourneyId)
            ->where('status', 'completed')
            ->with(['journey', 'responses.step'])
            ->orderBy('completed_at', 'desc')
            ->first();
            
        if (!$lastAttempt) {
            return '';
        }
        
        $journeyInfo = "PREVIOUS JOURNEY:\n";
        $journeyInfo .= "Journey Title: " . $lastAttempt->journey->title . "\n";
        $journeyInfo .= "Description: " . $lastAttempt->journey->description . "\n\n";
        
        if ($lastAttempt->responses->count() > 0) {
            $journeyInfo .= "CHAT INTERACTIONS:\n";
            $journeyInfo .= "================\n";
            
            foreach ($lastAttempt->responses as $response) {
                if ($response->user_input) {
                    $userTime = $response->submitted_at->format('Y-m-d H:i');
                    $journeyInfo .= "Student [{$userTime}]: " . strip_tags($response->user_input) . "\n";
                    if ($response->score) {
                        $journeyInfo .= "Rating: {$response->score}\n";
                    }
                    $journeyInfo .= "\n";
                }
                if ($response->ai_response) {
                    $aiTime = $response->updated_at->format('Y-m-d H:i');
                    $journeyInfo .= "AI [{$aiTime}]: " . strip_tags($response->ai_response) . "\n\n";
                }
            }
        }
        
        return $journeyInfo;
    }
    
    /**
     * Get last completed journey for user context
     */
    protected function getLastCompletedJourney(int $userId, int $currentJourneyId): string
    {
        $lastAttempt = JourneyAttempt::where('user_id', $userId)
            ->where('journey_id', '!=', $currentJourneyId)
            ->where('status', 'completed')
            ->with(['journey', 'responses.step'])
            ->orderBy('completed_at', 'desc')
            ->first();
            
        if (!$lastAttempt) {
            return '';
        }
        
        $journeyInfo = "PREVIOUS JOURNEY:\n";
        $journeyInfo .= "Journey Title: " . $lastAttempt->journey->title . "\n";
        $journeyInfo .= "Description: " . $lastAttempt->journey->description . "\n\n";
        
        if ($lastAttempt->responses->count() > 0) {
            $journeyInfo .= "CHAT INTERACTIONS:\n";
            $journeyInfo .= "================\n";
            
            foreach ($lastAttempt->responses as $response) {
                if ($response->user_input) {
                    $userTime = $response->submitted_at->format('Y-m-d H:i');
                    $journeyInfo .= "Student [{$userTime}]: " . strip_tags($response->user_input) . "\n";
                    if ($response->score) {
                        $journeyInfo .= "Rating: {$response->score}\n";
                    }
                    $journeyInfo .= "\n";
                }
                if ($response->ai_response) {
                    $aiTime = $response->updated_at->format('Y-m-d H:i');
                    $journeyInfo .= "AI [{$aiTime}]: " . strip_tags($response->ai_response) . "\n\n";
                }
            }
        }
        
        return $journeyInfo;
    }
    
    /**
     * Replace placeholders in prompt text
     */
    protected function replacePlaceholders(string $text, array $data): string
    {
        // Handle both {$a->property} (Moodle style) and {property} (standard style) patterns
        $text = preg_replace_callback('/\{\$a->([a-zA-Z0-9_]+)\}/', function ($matches) use ($data) {
            $property = $matches[1];
            return isset($data[$property]) ? $data[$property] : $matches[0];
        }, $text);
        
        // Handle standard {property} placeholders
        $text = preg_replace_callback('/\{([a-zA-Z0-9_]+)\}/', function ($matches) use ($data) {
            $property = $matches[1];
            return isset($data[$property]) ? $data[$property] : $matches[0];
        }, $text);
        
        return $text;
    }
    
    /**
     * Get text response format instruction
     */
    protected function getTextResponseFormat(): string
    {
        return 'Respond in conversational text format. Be engaging and educational.';
    }
    
    /**
     * Get rating response format instruction
     */
    protected function getRatingResponseFormat(): string
    {
        return 'Respond with a JSON object containing: {"rate": <number 1-10>, "action": "<action>", "feedback": "<text>"}. Actions can be: START_CHAT, RETRY_STEP, NEXT_STEP, FINISH_JOURNEY.';
    }
    
    /**
     * Get default chat prompt template
     */
    protected function getDefaultChatPrompt(): string
    {
        return "You are an AI learning assistant helping {{\$a->name}} through their learning journey.

Journey: {{\$a->journeydescription}}

Current Segment: {{\$a->currentsegment}}
Next Segment: {{\$a->nextsegment}}

Previous Learning Context:
{{\$a->lastjourney}}

Guidelines:
- Be supportive and encouraging
- Provide clear explanations and examples
- Ask thoughtful questions to check understanding
- Guide the learner through the current segment
- {{\$a->expectedformat}}

Please engage with the learner and help them progress through their journey.";
    }
    
    /**
     * Get chat prompt for a journey attempt
     *
     * @param int $journeyAttemptId
     * @return string
     */
    public function getChatPrompt(int $journeyAttemptId): string
    {
        $attempt = JourneyAttempt::with(['journey', 'user.institution'])->findOrFail($journeyAttemptId);
        $journey = $attempt->journey;
        $user = $attempt->user;
        
        // Get current step based on attempt's current_step
        $currentStep = $journey->steps()->where('order', $attempt->current_step)->first();
        
        // Get next step
        $nextStep = null;
        if ($currentStep) {
            $nextStep = $journey->steps()->where('order', '>', $currentStep->order)->orderBy('order')->first();
        } else {
            // If no current step, get first step
            $currentStep = $journey->steps()->orderBy('order')->first();
            if ($currentStep) {
                $nextStep = $journey->steps()->where('order', '>', $currentStep->order)->orderBy('order')->first();
            }
        }
        
        // Build variables array
        $variables = [
            'student_name' => $user->name,
            'student_email' => $user->email,
            'institution_name' => $user->institution ? $user->institution->name : 'Unknown Institution',
            'journey_title' => $journey->title,
            'journey_description' => $journey->description,
            'current_step' => $this->buildCurrentStepSection($attempt, $currentStep),
            'next_step' => $this->buildNextStepSection($nextStep),
            'expected_output' => $currentStep ? $currentStep->expected_output : '',
            'previous_journey' => $this->getLastCompletedJourney($user->id, $journey->id),
        ];

        //Lets work on user profile fields
        $profilefields = DB::table('profile_fields')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();
        foreach($profilefields as $pf) {
            $value = DB::table('user_profile_values')
                ->where('user_id', $user->id)
                ->where('profile_field_id', $pf->id)
                ->value('value');
            $variables['profile_' . $pf->short_name] = $value ?: '';
        }
        // Scan master_prompt for {journey_pathXX} placeholders
        // Get master prompt and replace variables
        $masterPrompt = $journey->master_prompt;
        if ($masterPrompt) {
            preg_match_all('/\{journey_path(\d+)\}/', $masterPrompt, $matches);
            if (!empty($matches[1])) {
                foreach ($matches[1] as $journeyId) {

                    $variables['journey_path' . $journeyId] = $this->getJourneyHistory($user->id, (int)$journeyId);
                }
            }
        }
        
        $masterPrompt =  $this->replacePlaceholders($masterPrompt, $variables);
        //We do one more pass to catch any remaining {property} placeholders that may come from steps
        $masterPrompt =  $this->replacePlaceholders($masterPrompt, $variables);
        return $masterPrompt;
    }
    
    /**
     * Get rate prompt for a journey attempt
     *
     * @param int $journeyAttemptId
     * @return string
     */
    public function getRatePrompt(int $journeyAttemptId): string
    {
        $attempt = JourneyAttempt::with(['journey', 'user.institution'])->findOrFail($journeyAttemptId);
        $journey = $attempt->journey;
        $user = $attempt->user;
        
        // Get current step based on attempt's current_step
        $currentStep = $journey->steps()->where('order', $attempt->current_step)->first();
        
        // Get next step
        $nextStep = null;
        if ($currentStep) {
            $nextStep = $journey->steps()->where('order', '>', $currentStep->order)->orderBy('order')->first();
        } else {
            // If no current step, get first step
            $currentStep = $journey->steps()->orderBy('order')->first();
            if ($currentStep) {
                $nextStep = $journey->steps()->where('order', '>', $currentStep->order)->orderBy('order')->first();
            }
        }
        
        // Build variables array
        $variables = [
            'student_name' => $user->name,
            'student_email' => $user->email,
            'institution_name' => $user->institution ? $user->institution->name : 'Unknown Institution',
            'journey_title' => $journey->title,
            'journey_description' => $journey->description,
            'current_step' => $this->buildCurrentStepSection($attempt, $currentStep),
            'next_step' => $this->buildNextStepSection($nextStep),
            'expected_output' => $currentStep && $currentStep->rating_prompt ? $currentStep->rating_prompt : PromptDefaults::getDefaultRatePrompt()
        ];
        

        return $currentStep->rating_prompt;
    }
    
    /**
     * Build current step section for prompt
     */
    private function buildCurrentStepSection(JourneyAttempt $attempt, ?JourneyStep $currentStep): string
    {
        if (!$currentStep) {
            return 'No current step defined';
        }
        
        // Get attempt count for current step
        $attemptCount = JourneyStepResponse::where('journey_attempt_id', $attempt->id)
            ->where('journey_step_id', $currentStep->id)
            ->count() + 1;
        $lastJourneyStepResponse = JourneyStepResponse::where('journey_attempt_id', $attempt->id)
            ->where('journey_step_id', $currentStep->id)
            ->orderBy('submitted_at', 'desc')
            ->first();
        $previousResponse = null;
        if ($lastJourneyStepResponse) {
            $previousResponse = JourneyStepResponse::where('journey_attempt_id', $attempt->id)
                ->where('id', '<', $lastJourneyStepResponse->id)
                ->orderBy('id', 'desc')
                ->first();
        }
        
        $section = "Title: " . $currentStep->title . "\n";
        $section .= "Content: " . $currentStep->content . "\n";
        
        $section .= "Rate pass: " . ($currentStep->ratepass ?: 3) . "\n";
        $section .= "Attempt: " . $attemptCount . " of " . ($currentStep->maxattempts ?: 3) . "\n";
        $section .= "Current time: " . now()->format('Y-m-d H:i:s') . "\n";

        if ($previousResponse && $previousResponse->step_action) $section .= 'Step action: ' . ($previousResponse->step_action ?: 'standard') . "\n";
        return $section;
    }
    
    /**
     * Build next step section for prompt
     */
    private function buildNextStepSection(?JourneyStep $nextStep): string
    {
        if (!$nextStep) {
            return 'No next step - this is the final step';
        }
        
        $section = "Title: " . $nextStep->title . "\n";
        $section .= "Content: " . $nextStep->content;
        
        
        return $section;
    }

    /**
     * Get default rating prompt template
     */
    protected function getDefaultRatePrompt(): string
    {
        return "You are an AI assessment assistant. Rate the student's response and determine next action.

Journey: {{\$a->journeydescription}}
Current Segment: {{\$a->currentsegment}}
Next Segment: {{\$a->nextsegment}}

Rating Guidelines:
- Rate from 1 (lowest) to 10 (highest)
- Consider accuracy, understanding, and completeness
- Use the passing rate as benchmark for progression

Actions:
- RETRY_STEP: If below passing rate and attempts remaining
- NEXT_STEP: If passing rate achieved or max attempts reached
- FINISH_JOURNEY: If this was the last segment

{{\$a->expectedformat}}";
    }

    public function getMessagesHistory($attemptid, $type,$addtime=false) {
        // Placeholder for future implementation
        $messages = [];
        $attempt = JourneyAttempt::findOrFail($attemptid);
        $steps = JourneyStepResponse::where('journey_attempt_id', $attempt->id)
            ->orderBy('id', 'asc')
            ->get();

            foreach ($steps as $step) {
                if ($step->ai_response){
                    if ($addtime) {
                        $time = $step->submitted_at ? $step->submitted_at->format('Y-m-d H:i') : '';
                        $stepText = trim($step->ai_response) . " \n (Submitted: {$time})";
                    } else {
                        $stepText = trim($step->ai_response);
                    }
                    $messages[] = [
                        'role' => 'assistant',
                        'content' => $stepText
                    ];
                }
                
                if ($step->user_input) {
                    
                    if ($addtime) {
                        $time = $step->updated_at ? $step->updated_at->format('Y-m-d H:i') : '';
                        $stepText = trim($step->user_input) . " \n (Submitted: {$time})";
                    } else {
                        $stepText = trim($step->user_input);
                    }
                    $messages[] = [
                        'role' => 'user',
                        'content' => $stepText
                    ];
                }
                
            }
            
        
        return $messages;
    }

    public function getFullContext($attemptid,$type='chat',$addtime = null) {
        // Placeholder for future implementation
        if ($type == 'chat') {
            $context = $this->getChatPrompt($attemptid);
        } else {
            $context = $this->getRatePrompt($attemptid);
        }
        $messages = ['role' => 'system', 'content' => $context];
        
        $messagesHistory = $this->getMessagesHistory($attemptid,$type,$addtime);
        array_unshift($messagesHistory, $messages);
        return $messagesHistory;
    }

    public function getFullChatPrompt($attemptid) {
        // Placeholder for future implementation
        
        $context = $this->getChatPrompt($attemptid);
        $messages = $this->getMessagesHistory($attemptid,'chat',true);
        $messagesprompt='### CHAT HISTORY (Current time: ' . date('Y-m-d H:i') . ') ###
';
        foreach($messages as $m){
            if ($m === end($messages) && $m['role'] == 'user') {
                continue;
            }
            $messagesprompt .= strtoupper($m['role']) . ": " . $m['content'] . "\r\n";

        }
        $context = str_replace('{journey_history}', $messagesprompt, $context);
        return $context;
    }

}
