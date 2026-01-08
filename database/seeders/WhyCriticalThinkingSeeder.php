<?php

namespace Database\Seeders;

use App\Models\JourneyCollection;
use App\Models\Journey;
use App\Models\JourneyStep;
use App\Models\User;
use Illuminate\Database\Seeder;
use App\Services\PromptDefaults;

class WhyCriticalThinkingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get or create a collection
        $collection = JourneyCollection::first();
        
        // Get an admin user to assign as creator
        $user = User::query()->first();
        
        if (!$user) {
            $this->command->error('No users found in database. Please run UserSeeder first.');
            return;
        }


        // Create the journey based on actual data from Journey ID 4
        $journey = Journey::create([
            'title' => 'Why Critical Thinking is Your Superpower. How sharper thinking changes your life',
            'description' => 'Facts are objective statements that can be proven true or false through evidence. Opinions are subjective statements reflecting personal beliefs, values, or preferences. Many statements combine both, blurring the line between verifiable
truth and personal judgement. Philosophy, journalism, and science all rely on distinguishing fact from opinion.',
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
                'title' => 'Step 1: Relevance in Everyday Life',
                'type' => 'text',
                'content' => <<<'EOD'
INSTRUCTION: Critical thinking influences how we interpret news, make purchases,
resolve conflicts, and even choose who to trust. Without it, we are more vulnerable to
manipulation, misinformation, and poor decisions.

MANDATORY QUESTION: Where do you think critical thinking could help you in your life. How could it make your life better?
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
INSTRUCTION: Critical thinking influences how we interpret news, make purchases,
resolve conflicts, and even choose who to trust. Without it, we are more vulnerable to
manipulation, misinformation, and poor decisions.

MANDATORY QUESTION: Where do you think critical thinking could help you in your life? How could it make your life better?
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
INSTRUCTION: From Socratic questioning in ancient Greece to modern scientific methods, critical thinking has always been a cornerstone of progress. Today, with AI, deepfakes, and an overwhelming flow of information, the ability to analyse and
evaluate information has become essential for personal and professional success.

MANDATORY QUESTION: Why do you think critical thinking is even more important today than in the past?
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
                'title' => 'Step 4: Component Part 1 - Awareness',
                'type' => 'text',
                'content' => <<<'EOD'
INSTRUCTION: Awareness means recognising what is influencing your thoughts — emotions, assumptions, and cognitive biases. Without awareness, it’s easy to react automatically instead of thoughtfully.

MANDATORY QUESTION: What are the things in your life that could be impacting on your ability to think critically. Think of what prejudices, biases or strong opinions you hold that could be blurring your thinking.
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
                'title' => 'Step 5: Component Part 2 - Analysis?',
                'type' => 'text',
                'content' => <<<'EOD'
INSTRUCTION: Analysis is breaking information into parts to see how they connect. This involves identifying claims, examining evidence, and spotting gaps or inconsistencies.

MANDATORY QUESTION: When you hear a new claim, what is the first thing you usually look for to judge its reliability?
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
                'title' => 'Step 6: Component Part 3 - Evaluation',
                'type' => 'text',
                'content' => <<<'EOD'
INSTRUCTION: Evaluation is judging the quality of evidence and reasoning. This involves recognising strong vs. weak arguments, identifying bias, and assessing credibility.

MANDATORY QUESTION: What makes you trust one source of information more than another?
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
                'title' => 'Step 7: Practical Exercise 1 - Headline Check',
                'type' => 'text',
                'content' => <<<'EOD'
INSTRUCTION: Look at a headline and decide: is it realistic, and what evidence would you need to believe it? This trains awareness, analysis, and evaluation together. Examples: “Eating chocolate daily boosts IQ by 20 points.” or "Drinking coffee daily adds ten years to your life, scientists reveal.” or “New vaccine causes severe side effects in 80% of users.” or “Climate change no longer a threat, top researcher claims.”

MANDATORY QUESTION: Select one of these headlines and ask the learner "What would you want to know before believing a headline like this?"
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
                'title' => 'Step 8: Practical Exercise 2 - The Mixed Statement Test',
                'type' => 'text',
                'content' => <<<'EOD'
INSTRUCTION: Example headline: “Research shows daily walking improves health,
making it the smartest choice for everyone.” The first part is fact; the second is
opinion.

MANDATORY QUESTION: How would you reword this headline to clearly separate
the fact from the opinion?
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
                'title' => 'Step 9: Wrap-Up and Evaluation',
                'type' => 'text',
                'content' => <<<'EOD'
INSTRUCTION: Critical thinking combines awareness, analysis, and evaluation to help you make better decisions and avoid manipulation. Strengthening it is a skill that grows with practice.

MANDATORY QUESTION: Which of the three components — awareness, analysis, or evaluation — do you think you most need to develop?
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
