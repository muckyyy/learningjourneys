<?php

namespace Database\Seeders;

use App\Models\JourneyCollection;
use App\Models\Journey;
use App\Models\JourneyStep;
use App\Models\User;
use Illuminate\Database\Seeder;
use App\Services\PromptDefaults;

class TheAssumptionTrapSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get or create a collection
        $collection = JourneyCollection::first();
        
        // Get an admin user to assign as creator
        $user = User::where('role', 'admin')->first() ?? User::first();
        
        if (!$user) {
            $this->command->error('No users found in database. Please run UserSeeder first.');
            return;
        }


        // Create the journey based on actual data from Journey ID 4
        $journey = Journey::create([
            'title' => 'The Assumption Trap - Finding hidden beliefs in statements',
            'description' => 'An assumption is something accepted as true without proof. In reasoning, assumptions can be useful shortcuts but are risky when left unexamined. They can be explicit (clearly stated) or implicit (hidden or unstated).',
            'journey_collection_id' => $collection?->id,
            'created_by' => $user->id,
            'difficulty_level' => 'beginner',
            'estimated_duration' => 15,
            'is_published' => true,

            'master_prompt' => PromptDefaults::getDefaultMasterPrompt(),
            'report_prompt' => PromptDefaults::getDefaultReportPrompt(),
        ]);
        $stepsData = [
            [
                'title' => 'Step 1: Establishing Prior Understanding',
                'type' => 'text',
                'content' => <<<'EOD'
INSTRUCTION: Assumptions are unstated ideas we accept as true without evidence. They often hide beneath the surface of our reasoning.

MANDATORY QUESTION: How often do you stop to question what’s assumed in what someone says?
EOD,
                'order' => 1,
                'ratepass' => 2,
                'expected_output' => PromptDefaults::getDefaultTextStepOutput(),
                'config' => PromptDefaults::getDefaultStepConfig(),
                'rating_prompt' => PromptDefaults::getDefaultRatePrompt(),
                'maxattempts' => 2,
                'is_required' => true,
            ],
            [
                'title' => 'Step 2: Relevance in Everyday Life',
                'type' => 'text',
                'content' => <<<'EOD'
INSTRUCTION: Unchecked assumptions can lead to poor decisions, misunderstandings, and even conflicts. Spotting them lets you make clearer choices.

MANDATORY QUESTION: Why do you think unchecked assumptions can lead to poor decisions or judgements?
EOD,
                'order' => 2,
                'ratepass' => 2,
                'expected_output' => PromptDefaults::getDefaultTextStepOutput(),
                'config' => PromptDefaults::getDefaultStepConfig(),
                'rating_prompt' => PromptDefaults::getDefaultRatePrompt(),
                'maxattempts' => 2,
                'is_required' => true,
            ],
            [
                'title' => 'Step 3: Big Picture and Context',
                'type' => 'text',
                'content' => <<<'EOD'
INSTRUCTION: From everyday conversations to complex negotiations, assumptions shape conclusions. Critical thinkers uncover these hidden beliefs to avoid false reasoning.

MANDATORY QUESTION: Why might people avoid questioning assumptions?
EOD,
                'order' => 3,
                'ratepass' => 2,
                'expected_output' => PromptDefaults::getDefaultTextStepOutput(),
                'config' => PromptDefaults::getDefaultStepConfig(),
                'rating_prompt' => PromptDefaults::getDefaultRatePrompt(),
                'maxattempts' => 2,
                'is_required' => true,
            ],
            [
                'title' => 'Step 4: Component Part 1 - Identifying Obvious Assumptions',
                'type' => 'text',
                'content' => <<<'EOD'
INSTRUCTION: These are clearly implied but unstated — for example, “We need more staff to improve productivity” assumes more people = better output. or “If people are unemployed, they just aren’t trying hard enough.” assumes everyone has equal access to jobs and opportunities. or “Good leaders are always extroverts.” assumes introverted people can’t be effective leaders. or “Working from home makes people less productive.” assumes productivity can only be measured by visible activity in an office. or “He failed the exam, so he must not be very smart.” assumes exam performance fully reflects intelligence.

MANDATORY QUESTION: Select one of the examples and ask the learner to identify the assumption in the statement.
EOD,
                'order' => 4,
                'ratepass' => 2,
                'expected_output' => PromptDefaults::getDefaultTextStepOutput(),
                'config' => PromptDefaults::getDefaultStepConfig(),
                'rating_prompt' => PromptDefaults::getDefaultRatePrompt(),
                'maxattempts' => 2,
                'is_required' => true,
            ],
            [
                'title' => 'Step 5: Component Part 2 - Hidden Assumptions',
                'type' => 'text',
                'content' => <<<'EOD'
INSTRUCTION: These are embedded so deeply in beliefs or culture that they are harder to detect. Examples include “A good mother always puts her children before herself.” assumes self-sacrifice defines “good” motherhood; caregiving is primarily a woman’s role. “Men are naturally better leaders.” assumes leadership traits (confidence, assertiveness) are inherently masculine. “Real men don’t cry.” assumes emotional expression is weakness; masculinity equals emotional control. 
“Marriage is the foundation of a stable society.” assumes stability and morality depend on a specific family structure (usually heterosexual, nuclear).

MANDATORY QUESTION: Provide a statement and ask the learner to identify and explain the hidden cultural assumption or belie.
EOD,
                'order' => 5,
                'ratepass' => 2,
                'expected_output' => PromptDefaults::getDefaultTextStepOutput(),
                'config' => PromptDefaults::getDefaultStepConfig(),
                'rating_prompt' => PromptDefaults::getDefaultRatePrompt(),
                'maxattempts' => 2,
                'is_required' => true,
            ],
            [
                'title' => 'Step 6: Component Part 3 - Testing Assumptions',
                'type' => 'text',
                'content' => <<<'EOD'
INSTRUCTION: Testing assumptions involves asking questions with a purpose. The following questions provide a basis on which you can test assumptions. Clarifying the claim: These questions help unpack what the statement really means. “What exactly do you mean by that?” 
“Can you define that term more precisely?” Probing for causes and reasoning: These questions test what the claim is built on. “What makes you say that?” “What evidence supports this idea?”
Exploring exceptions and alternatives: These invite the mind to consider counterexamples. 
“Can you think of any situation where that’s not the case?” “Would this still make sense in another culture or time period?” Revealing values and priorities: These questions dig into the moral or emotional foundations behind a statement. “What values are behind this idea?” 
“Why is this important to you (or to the people who believe it)?” Testing consistency: These check whether the assumption fits with other beliefs.

MANDATORY QUESTION: Provide three statements and ask the learner to propose a testing question to uncover the hidden assumption.
EOD,
                'order' => 6,
                'ratepass' => 2,
                'expected_output' => PromptDefaults::getDefaultTextStepOutput(),
                'config' => PromptDefaults::getDefaultStepConfig(),
                'rating_prompt' => PromptDefaults::getDefaultRatePrompt(),
                'maxattempts' => 2,
                'is_required' => true,
            ],
            [
                'title' => 'Step 7: Practical Exercise 1 - Statement Breakdown',
                'type' => 'text',
                'content' => <<<'EOD'
INSTRUCTION: Hidden assumptions can be uncovered using the statement breakdown technique. Example: 
“Successful people always work harder than others.”
Step 1 – Break it up:
“Successful people”
“always work harder”
“than others”
Step 2 – Examine each piece:
“Successful” → What counts as success? (Money? Happiness? Recognition?)
“always work harder” → Assumes effort is the only or main factor in success.
“than others” → Assumes there’s a clear and fair comparison across people’s opportunities.
Hidden assumptions revealed:
Success equals external achievement.
Everyone starts with equal chances.
Hard work automatically leads to success.

MANDATORY QUESTION: Following the technique just explained, apply the breakdown technique to the following statement: “Remote work reduces productivity.”
EOD,
                'order' => 7,
                'ratepass' => 2,
                'expected_output' => PromptDefaults::getDefaultTextStepOutput(),
                'config' => PromptDefaults::getDefaultStepConfig(),
                'rating_prompt' => PromptDefaults::getDefaultRatePrompt(),
                'maxattempts' => 2,
                'is_required' => true,
            ],
            [
                'title' => 'Step 8: Practical Exercise 2 - Assumption Swap',
                'type' => 'text',
                'content' => <<<'EOD'
INSTRUCTION: When someone makes a statement, it’s built on an implicit assumption — something that feels so obvious it goes unquestioned. To uncover it, you:
1) Identify what that underlying assumption might be. 2) Swap it out with a different, plausible assumption. 3) Observe how the meaning of the original statement shifts (or collapses). If changing the assumption changes the conclusion, you’ve just found a hidden assumption.
Example 1:
“If you work hard, you’ll be successful.”
Step 1 — Identify the hidden assumption:
Assumes success is entirely determined by effort.
Step 2 — Swap the assumption:
Alternative assumption: Success also depends on privilege, opportunity, and environment.
Step 3 — See what happens:
With the new assumption, the statement would need to change to something like:
“If you work hard and have access to opportunities, you’ll be more likely to succeed.”
Result:
The original claim no longer holds in all cases — revealing that it relied on a cultural belief in meritocracy (effort = success).
Hidden assumption uncovered: “The world is fair and rewards effort equally.”

MANDATORY_QUESTION: First explain the Assumption Swap technique then ask the learner to use the technique to uncover the hidden assumptions in the following statement “Good parents spend a lot of time with their children.”
EOD,
                'order' => 8,
                'ratepass' => 2,
                'expected_output' => PromptDefaults::getDefaultTextStepOutput(),
                'config' => PromptDefaults::getDefaultStepConfig(),
                'rating_prompt' => PromptDefaults::getDefaultRatePrompt(),
                'maxattempts' => 2,
                'is_required' => true,
            ],
            [
                'title' => 'Step 9: Learner perception',
                'type' => 'text',
                'content' => <<<'EOD'
MANDATORY QUESTION: What did you learn in this learning journey and was it useful.
EOD,
                'order' => 9,
                'ratepass' => 2,
                'expected_output' => PromptDefaults::getDefaultTextStepOutput(),
                'config' => PromptDefaults::getDefaultStepConfig(),
                'rating_prompt' => PromptDefaults::getDefaultRatePrompt(),
                'maxattempts' => 2,
                'is_required' => true,
            ],
            [
                'title' => 'Step 10: Goodbye',
                'type' => 'text',
                'content' => <<<'EOD'
This is last step of a journey. There are no actions required. Do not ask any question that requires a response.

MANDATORY: End this journey by providing feedback to learners response from the previous step and offer parting and encouraging words.
EOD,
                'order' => 10,
                'ratepass' => 2,
                'expected_output' => PromptDefaults::getDefaultTextStepOutput(),
                'config' => PromptDefaults::getDefaultStepConfig(),
                'rating_prompt' => PromptDefaults::getDefaultRatePrompt(),
                'maxattempts' => 2,
                'is_required' => true,
            ],
        ];
        // Create the steps
        foreach ($stepsData as $stepData) {
            JourneyStep::create([
                'journey_id' => $journey->id,
                'title' => $stepData['title'],
                'content' => $stepData['content'],
                'type' => $stepData['type'],
                'ratepass' => $stepData['ratepass'],
                'maxattempts' => $stepData['maxattempts'],
                'expected_output' => $stepData['expected_output'] ?? null,
                'rating_prompt' => $stepData['rating_prompt'] ?? null,
                'config' => $stepData['config'] ?? null,
                'order' => $stepData['order'],
                'is_required' => $stepData['is_required'],
            ]);
        }
        $this->command->info("Journey '{$journey->title}' created successfully with " . count($stepsData) . " steps.");

        
    }
}
