<?php

namespace Database\Seeders;

use App\Models\JourneyCollection;
use App\Models\Journey;
use App\Models\JourneyStep;
use App\Models\User;
use Illuminate\Database\Seeder;
use App\Services\PromptDefaults;

class JourneyCriticalThinkingSeeder extends Seeder
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
            'title' => 'Your first steps to critical thinking',
            'description' => 'This journey introduces students to concepts of critical thinking and basic understanding',
            'journey_collection_id' => $collection?->id,
            'created_by' => $user->id,
            'difficulty_level' => 'beginner',
            'estimated_duration' => 15,
            'is_published' => true,

            'master_prompt' => PromptDefaults::getDefaultMasterPrompt(),
            'report_prompt' => PromptDefaults::getDefaultReportPrompt(),
        ]);

        // Create the journey steps based on actual data
        $stepsData = [
            [
                'title' => 'Step 1: Eliciting Prior Knowledge',
                'type' => 'text',
                'content' => <<<'EOD'
MANDATORY: Ask the learner what they already know or think about critical thinking. Use open-ended questions and affirm their responses. Encourage reflection on any personal experience where they think they used critical thinking.

MANDATORY_QUESTION: Probe learners grasp on critical thinking, and in feedback explain content from segment two.
EOD,
                'order' => 1,
                'ratepass' => 3,
                'expected_output' => PromptDefaults::getDefaultTextStepOutput(),
                'config' => PromptDefaults::getDefaultStepConfig(),
                'rating_prompt' => PromptDefaults::getDefaultRatePrompt(),
                'maxattempts' => 3,
                'is_required' => true,
            ],
            [
                'title' => 'Step 2: Defining and Unpacking Critical Thinking',
                'type' => 'text',
                'content' => <<<'EOD'
"Critical thinking is the process of actively and skillfully conceptualizing, applying, analyzing, synthesizing, and evaluating information to make well-informed judgments".

MANDATORY: Provide a clear and simple definition of critical thinking. 

MANDATORY_QUESTION: Ask the learner to try to define critical thinking in their own words.
EOD,
                'order' => 2,
                'ratepass' => 3,
                'expected_output' => PromptDefaults::getDefaultTextStepOutput(),
                'config' => PromptDefaults::getDefaultStepConfig(),
                'rating_prompt' => PromptDefaults::getDefaultRatePrompt(),
                'maxattempts' => 3,
                'is_required' => true,
            ],
            [
                'title' => 'Step 3: Importance in Daily Life',
                'type' => 'text',
                'content' => <<<'EOD'
MANDATORY: Discuss why critical thinking is essential for everybody. Being a critical thinker means you can: Solve problems more effectively; Make better decisions; See through manipulation, bias, or flawed logic; communicate more clearly; Learn more deeply and think more independently. It helps in all areas of life from evaluating news articles to making financial decisions or resolving personal conflicts. In everyday life, critical thinking might show up as: Fact-checking a social media post before sharing it, asking thoughtful questions during a meeting rather than just nodding along, considering both sides of a political argument before forming an opinion, deciding which job offer is better, not just by salary but by weighing location, values, growth opportunities, etc. Importantly it also involves challenging your own beliefs or gut reactions to see if they are based on facts or assumptions.

MANDATORY_QUESTION: Describe a scenario with a negative outcome due to poor critical thinking and ask the learner to comment on how some critical thinking could have avoided or improved this outcome.
EOD,
                'order' => 3,
                'ratepass' => 3,
                'expected_output' => PromptDefaults::getDefaultTextStepOutput(),
                'rating_prompt' => PromptDefaults::getDefaultRatePrompt(),
                'config' => PromptDefaults::getDefaultStepConfig(),
                'maxattempts' => 3,
                'is_required' => true,
            ],
            [
                'title' => 'Step 4: Self-Assessment Exercise: How do I think?',
                'type' => 'text',
                'content' => <<<'EOD'
MANDATORY_QUESTION: Present short scenarios with potentially biased or flawed statements or claims or situations. Ask the learner to identify what questions a critical thinker might ask. Offer supportive feedback based on their answers and guide them toward deeper thinking.
EOD,
                'order' => 4,
                'ratepass' => 3,
                'expected_output' => PromptDefaults::getDefaultTextStepOutput(),
                'rating_prompt' => PromptDefaults::getDefaultRatePrompt(),
                'config' => PromptDefaults::getDefaultStepConfig(),
                'maxattempts' => 3,
                'is_required' => true,
            ],
            [
                'title' => 'Step 5: Reflecting on Thinking Habits',
                'type' => 'text',
                'content' => <<<'EOD'
MANDATORY_QUESTION: Ask the learner about their own thinking style. Present a few habits (e.g., jumping to conclusions, resisting new ideas, over generalization, groupthink, too emotional etc.) and ask them to reflect.

MANDATORY: Give feedback on their answers, affirm their awareness and suggest improvement strategies.
EOD,
                'order' => 5,
                'ratepass' => 3,
                'expected_output' => PromptDefaults::getDefaultTextStepOutput(),
                'rating_prompt' => PromptDefaults::getDefaultRatePrompt(),
                'config' => PromptDefaults::getDefaultStepConfig(),
                'maxattempts' => 3,
                'is_required' => true,
            ],
            [
                'title' => 'Step 6: Assessment',
                'type' => 'text',
                'content' => <<<'EOD'
MANDATORY: Ask the learner a question that tests their theoretical understanding of critical thinking. Ask an open ended question. Only ask questions about the content covered in this journey. If they get it wrong, give them the correct answer but ask them another question.
EOD,
                'order' => 6,
                'ratepass' => 3,
                'expected_output' => PromptDefaults::getDefaultTextStepOutput(),
                'rating_prompt' => PromptDefaults::getDefaultRatePrompt(),
                'config' => PromptDefaults::getDefaultStepConfig(),
                'maxattempts' => 3,
                'is_required' => true,
            ],
            [
                'title' => 'Step 7: Wrap up',
                'type' => 'text',
                'content' => <<<'EOD'
MANDATORY: Provide a summary of the learning journey.

MANDATORY_QUESTION: What part of the learning experience did you find most helpful or surprising? What part of the learning experience was less useful or enjoyable?

EOD,
                'order' => 7,
                'ratepass' => 3,
                'expected_output' => PromptDefaults::getDefaultTextStepOutput(),
                'rating_prompt' => PromptDefaults::getDefaultRatePrompt(),
                'config' => PromptDefaults::getDefaultStepConfig(),
                'maxattempts' => 3,
                'is_required' => true,
            ]
            ,
            [
                'title' => 'Step 8: Goodbye',
                'type' => 'text',
                'content' => <<<'EOD'
This is last step of a journey.
There is no actions required. Do not ask any question that requires a response.
MANDATORY: End this journey by providing feedback to learners response and then offer parting and encouraging words. 
Also recommend that the learner go to the next learning journey, How biases shape our lives.
Give constructive feedback on their responses in this journey and encourage them to continue practicing critical thinking in daily life.
EOD,
                'order' => 8,
                'ratepass' => 1,
                'expected_output' => PromptDefaults::getDefaultTextStepOutput(),
                'rating_prompt' => PromptDefaults::getDefaultRatePrompt(),
                'config' => PromptDefaults::getDefaultStepConfig(),
                'maxattempts' => 1,
                'is_required' => true,
            ]
        ];

        // Recommend that the learner go to the next learning journey, How biases shape our lives.
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
        
        // Create the journey based on actual data from Journey ID 4
        $journey = Journey::create([
            'title' => 'Fact or Fiction? Spotting truth in a sea of opinions',
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
                'title' => 'Step 1: Establishing Prior Understanding',
                'type' => 'text',
                'content' => <<<'EOD'
INSTRUCTION: Many people confuse facts and opinions, or fail to spot when they
are mixed together. Understanding the difference is the first step to evaluating
information effectively.

MANDATORY QUESTION: How do you currently decide if something you read or
hear is a fact or an opinion?
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
INSTRUCTION: Being able to separate fact from opinion protects you from
misinformation, strengthens your credibility, and improves your decision-making.

MANDATORY QUESTION: Can you think of a time when believing something untrue
had consequences for you or someone you know?ntly decide if something you read or
hear is a fact or an opinion?
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
INSTRUCTION: In today’s digital environment, anyone can publish information
instantly, making it harder to separate verified truth from personal belief or falsehood.
Critical thinkers develop the skill to recognise each.

MANDATORY QUESTION: Why do you think separating facts from opinions is
harder now than in the past?
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
                'title' => 'Step 4: Component Part 1 - What is a Fact?',
                'type' => 'text',
                'content' => <<<'EOD'
INSTRUCTION: A fact is something objectively verifiable, supported by credible
evidence, and not dependent on personal beliefs. Example: “Water boils at 100°C at
sea level.”
MANDATORY QUESTION: Can you give an example of a fact that most people
agree on?
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
                'title' => 'Step 5: Component Part 2 - What is an Opinion?',
                'type' => 'text',
                'content' => <<<'EOD'
INSTRUCTION: An opinion is a personal judgement or preference. It can be
informed by facts but is not itself objectively verifiable. Example: “Vanilla ice cream
tastes better than chocolate.”
MANDATORY QUESTION: What’s an opinion you hold strongly, even though you
know others may disagree?
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
                'title' => 'Step 6: Component Part 3 - Blurred Lines',
                'type' => 'text',
                'content' => <<<'EOD'
INSTRUCTION: Many statements mix facts and opinions, often leading to confusion.
Being able to separate the two helps you avoid misleading conclusions.
MANDATORY QUESTION: Can you think of a statement you've heard recently that
mixed facts and opinions?
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
                'title' => 'Step 7: Practical Exercise 1 - Fact or Opinion?',
                'type' => 'text',
                'content' => <<<'EOD'
INSTRUCTION: Test your skill: “Mount Everest is the tallest mountain above sea
level” (fact) vs. “Mount Everest is the most beautiful mountain in the world” (opinion).
MANDATORY QUESTION: Can you create one fact and one opinion about the
same topic?
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
INSTRUCTION: Separating facts from opinions helps you judge credibility, detect
bias, and make more informed decisions.
MANDATORY QUESTION: Which do you think is harder to spot — false facts or
hidden opinions? Why?
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
