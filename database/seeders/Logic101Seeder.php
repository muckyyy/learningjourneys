<?php

namespace Database\Seeders;

use App\Models\JourneyCollection;
use App\Models\Journey;
use App\Models\JourneyStep;
use App\Models\User;
use Illuminate\Database\Seeder;
use App\Services\PromptDefaults;

class Logic101Seeder extends Seeder
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
            'title' => 'Logic 101: The Building Blocks of Reasoning',
            'short_description' => 'Logic is the systematic study of valid reasoning. It examines the structure of arguments to determine whether conclusions follow from premises.',
            'description' => 'Logic is the systematic study of valid reasoning. It examines the structure of arguments to determine whether conclusions follow from premises. Formal logic dates back to Aristotle, who introduced syllogisms as a way of structuring reasoning. Modern logic includes both deductive reasoning (where conclusions necessarily follow from premises) and inductive reasoning (where conclusions are probable based on evidence).',
            'journey_collection_id' => $collection?->id,
            'created_by' => $user->id,
            'sort' => 3,
            'difficulty_level' => 'beginner',
            'estimated_duration' => 15,
            'is_published' => true,
            'token_cost' => 10,
            'master_prompt' => PromptDefaults::getDefaultMasterPrompt(),
            'report_prompt' => PromptDefaults::getDefaultReportPrompt(),
        ]);
        $stepsData = [
            [
                'title' => 'Step 1: Establishing Prior Understanding',
                'type' => 'text',
                'content' => <<<'EOD'
INSTRUCTION: Logic is the framework we use to determine if reasoning is sound. It helps us connect ideas in a clear, structured way.

MANDATORY QUESTION: What comes to mind when you hear the word “logic”?
EOD,
                'order' => 1,
                'ratepass' => 2,
                'expected_output' => PromptDefaults::getDefaultTextStepOutput(),
                'expected_output_followup' => PromptDefaults::getDefaultTextStepOutputFollowUp(),
                'expected_output_retry' => PromptDefaults::getDefaultTextStepOutputRetry(),
                'config' => PromptDefaults::getDefaultStepConfig(),
                'rating_prompt' => PromptDefaults::getDefaultRatePrompt(),
                'maxattempts' => 2,
                'is_required' => true,
            ],
            [
                'title' => 'Step 2: Relevance in Everyday Life',
                'type' => 'text',
                'content' => <<<'EOD'
INSTRUCTION: Whether we’re making a purchase, debating an issue, or solving a problem, logical thinking helps us make consistent, reliable decisions.

MANDATORY QUESTION: Does the logic of this sentence add up? “If it’s raining then the ground is wet. The ground is wet. Therefore it’s raining.”
EOD,
                'order' => 2,
                'ratepass' => 2,
                'expected_output' => PromptDefaults::getDefaultTextStepOutput(),
                'expected_output_followup' => PromptDefaults::getDefaultTextStepOutputFollowUp(),
                'expected_output_retry' => PromptDefaults::getDefaultTextStepOutputRetry(),
                'config' => PromptDefaults::getDefaultStepConfig(),
                'rating_prompt' => PromptDefaults::getDefaultRatePrompt(),
                'maxattempts' => 2,
                'is_required' => true,
            ],
            [
                'title' => 'Step 3: Big Picture and Context',
                'type' => 'text',
                'content' => <<<'EOD'
INSTRUCTION: Logical reasoning has been central to philosophy, law, science, and everyday problem-solving for centuries. Logic and critical thinking are deeply connected — logic provides the structure, while critical thinking provides the application. From Socrates onward, reasoning well has meant questioning assumptions and exposing contradictions — the essence of critical thinking. Plato and Aristotle formalized that process: Aristotle’s logical rules turned good reasoning into a system that could be tested for validity. In essence, logic gives critical thinking its tools and standards: it tells us how to reason correctly, while critical thinking tells us when and why to question, evaluate, and apply that reasoning in real-world contexts.

MANDATORY QUESTION: Why is logic so important to science, law and other everyday activities?
EOD,
                'order' => 3,
                'ratepass' => 2,
                'expected_output' => PromptDefaults::getDefaultTextStepOutput(),
                'expected_output_followup' => PromptDefaults::getDefaultTextStepOutputFollowUp(),
                'expected_output_retry' => PromptDefaults::getDefaultTextStepOutputRetry(),
                'config' => PromptDefaults::getDefaultStepConfig(),
                'rating_prompt' => PromptDefaults::getDefaultRatePrompt(),
                'maxattempts' => 2,
                'is_required' => true,
            ],
            [
                'title' => 'Step 4: Component Part 1 - Premises',
                'type' => 'text',
                'content' => <<<'EOD'
INSTRUCTION: A premise is a statement that provides a reason or support for a conclusion. Good reasoning starts with true or reliable premises.

MANDATORY QUESTION: Based on this statement (provide a statement), what is the premise of this statement?
EOD,
                'order' => 4,
                'ratepass' => 2,
                'expected_output' => PromptDefaults::getDefaultTextStepOutput(),
                'expected_output_followup' => PromptDefaults::getDefaultTextStepOutputFollowUp(),
                'expected_output_retry' => PromptDefaults::getDefaultTextStepOutputRetry(),
                'config' => PromptDefaults::getDefaultStepConfig(),
                'rating_prompt' => PromptDefaults::getDefaultRatePrompt(),
                'maxattempts' => 2,
                'is_required' => true,
            ],
            [
                'title' => 'Step 5:Component Part 2 - Conclusions',
                'type' => 'text',
                'content' => <<<'EOD'
INSTRUCTION: The conclusion is what follows logically from the premises. If the premises are strong and the reasoning valid, the conclusion should be reliable.

MANDATORY QUESTION: Can you give an example of a conclusion based on the premise “Exercise improves health”?
EOD,
                'order' => 5,
                'ratepass' => 2,
                'expected_output' => PromptDefaults::getDefaultTextStepOutput(),
                'expected_output_followup' => PromptDefaults::getDefaultTextStepOutputFollowUp(),
                'expected_output_retry' => PromptDefaults::getDefaultTextStepOutputRetry(),
                'config' => PromptDefaults::getDefaultStepConfig(),
                'rating_prompt' => PromptDefaults::getDefaultRatePrompt(),
                'maxattempts' => 2,
                'is_required' => true,
            ],
            [
                'title' => 'Step 6: Component Part 3 - Validity vs. Truth',
                'type' => 'text',
                'content' => <<<'EOD'
INSTRUCTION: An argument can be valid (the reasoning works) but still be false if the premises are wrong.

MANDATORY QUESTION: Why is the following argument (provide a statement) false even if the argument is valid Explain.
EOD,
                'order' => 6,
                'ratepass' => 2,
                'expected_output' => PromptDefaults::getDefaultTextStepOutput(),
                'expected_output_followup' => PromptDefaults::getDefaultTextStepOutputFollowUp(),
                'expected_output_retry' => PromptDefaults::getDefaultTextStepOutputRetry(),
                'config' => PromptDefaults::getDefaultStepConfig(),
                'rating_prompt' => PromptDefaults::getDefaultRatePrompt(),
                'maxattempts' => 2,
                'is_required' => true,
            ],
            [
                'title' => 'Step 7: Practical Exercise 1 - Statement Checkn',
                'type' => 'text',
                'content' => <<<'EOD'
INSTRUCTION: “All birds have feathers. Penguins are birds. Therefore, penguins have feathers.” Check if this reasoning is valid and true.

MANDATORY QUESTION: What is the premise of this statement, what is the conclusion of this statement, which is true, which is valid and why?
EOD,
                'order' => 7,
                'ratepass' => 2,
                'expected_output' => PromptDefaults::getDefaultTextStepOutput(),
                'expected_output_followup' => PromptDefaults::getDefaultTextStepOutputFollowUp(),
                'expected_output_retry' => PromptDefaults::getDefaultTextStepOutputRetry(),
                'config' => PromptDefaults::getDefaultStepConfig(),
                'rating_prompt' => PromptDefaults::getDefaultRatePrompt(),
                'maxattempts' => 2,
                'is_required' => true,
            ],
            [
                'title' => 'Step 8: Practical Exercise 2 - Broken Logic',
                'type' => 'text',
                'content' => <<<'EOD'
                INSTRUCTION: “All cats are animals. My dog is an animal. Therefore, my dog is a cat.” This is invalid reasoning despite true premises.

MANDATORY QUESTION: What’s wrong with this reasoning?
EOD,
                'order' => 8,
                'ratepass' => 2,
                'expected_output' => PromptDefaults::getDefaultTextStepOutput(),
                'expected_output_followup' => PromptDefaults::getDefaultTextStepOutputFollowUp(),
                'expected_output_retry' => PromptDefaults::getDefaultTextStepOutputRetry(),
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
                'expected_output_followup' => PromptDefaults::getDefaultTextStepOutputFollowUp(),
                'expected_output_retry' => PromptDefaults::getDefaultTextStepOutputRetry(),
                'config' => PromptDefaults::getDefaultStepConfig(),
                'rating_prompt' => PromptDefaults::getDefaultRatePrompt(),
                'maxattempts' => 2,
                'is_required' => true,
            ],
            [
                'title' => 'Step 10: Goodbye',
                'type' => 'text',
                'content' => <<<'EOD'
TThis is last step of a journey. There are no actions required. Do not ask any question that requires a response.

MANDATORY: Provide a summary for this journey and offer parting and encouraging words.
EOD,
                'order' => 10,
                'ratepass' => 1,
                'expected_output' => PromptDefaults::getDefaultTextStepOutput(),
                'expected_output_followup' => PromptDefaults::getDefaultTextStepOutputFollowUp(),
                'expected_output_retry' => PromptDefaults::getDefaultTextStepOutputRetry(),
                'config' => PromptDefaults::getDefaultStepConfig(),
                'rating_prompt' => PromptDefaults::getDefaultRatePrompt(),
                'maxattempts' => 1,
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
                'expected_output_followup' => $stepData['expected_output_followup'] ?? null,
                'expected_output_retry' => $stepData['expected_output_retry'] ?? null,
                'rating_prompt' => $stepData['rating_prompt'] ?? null,
                'config' => $stepData['config'] ?? null,
                'order' => $stepData['order'],
                'is_required' => $stepData['is_required'],
            ]);
        }
        $this->command->info("Journey '{$journey->title}' created successfully with " . count($stepsData) . " steps.");

        
    }
}
