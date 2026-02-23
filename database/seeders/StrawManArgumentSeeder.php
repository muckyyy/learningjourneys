<?php

namespace Database\Seeders;

use App\Models\JourneyCollection;
use App\Models\Journey;
use App\Models\JourneyStep;
use App\Models\User;
use Illuminate\Database\Seeder;
use App\Services\PromptDefaults;

class StrawManArgumentSeeder extends Seeder
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
            'title' => 'Countering the Strawman Argument',
            'short_description' => 'This journey investigates the use and abuse of strawman arguments, a common logical fallacy where someone misrepresents another person’s position to make it easier to attack or refute.',
            'description' => 'This journey investigates the use and abuse of strawman arguments. A strawman argument is a common logical fallacy in which someone distorts, misrepresents, or oversimplifies another person’s position to make it easier to attack or refute. Instead of engaging with the actual argument, the speaker creates a “straw man” version—one that’s weaker, exaggerated, or absurd—and then knocks it down. This tactic gives the illusion of having successfully refuted the original claim, even though the real argument was never addressed.',
            'journey_collection_id' => $collection?->id,
            'created_by' => $user->id,
            'sort' => 1,
            'difficulty_level' => 'beginner',
            'estimated_duration' => 15,
            'is_published' => true,
            'token_cost' => 10,
            'master_prompt' => PromptDefaults::getDefaultMasterPrompt(),
            'report_prompt' => PromptDefaults::getDefaultReportPrompt(),
        ]);
        $stepsData = [
            [
                'title' => 'Step 1: Scenario 1',
                'type' => 'text',
                'content' => <<<'EOD'
MANDATORY QUESTION: Include the full transcript of scenario 1 which is a discussion between Jordan and Taylor and ask the user "Can you detect what is problematic with this discussion?"

Jordan says, “I think we should reduce the number of standardized tests in schools so teachers can focus more on creative learning.”
Taylor replies, “So you just want schools to stop testing students altogether and let kids do whatever they want all day? That would destroy academic standards!”
Jordan frowns, “That’s not what I said at all.”
Taylor continues, “If we follow your plan, students won’t learn discipline or accountability. It’s a terrible
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
                'title' => 'Step 2 Why do people use strawman arguments',
                'type' => 'text',
                'content' => <<<'EOD'
INSTRUCTION: Explain why people use strawman arguments both intentionally and unintentionally. In deliberate cases, it’s a rhetorical tactic—a manipulative strategy to win debates or sway audiences by appealing to emotion rather than reason. Politicians, for example, often use strawman arguments to make opponents seem extreme or unreasonable: “My opponent wants to defund all public safety,” when the real proposal might be to reallocate police budgets toward community programs. Unintentionally, people may commit this fallacy out of misunderstanding or poor listening skills. Complex arguments can be difficult to grasp, and people often reduce them to simpler forms that fit their own frameworks. This simplification can morph into a strawman, even without deceitful intent. Additionally, in emotionally charged discussions—about politics, religion, or ethics—people often hear threats to their values and instinctively overreact, caricaturing the opposing view to defend their beliefs more easily.

MANDATORY_QUESTION: Why do you think people use strawman arguments?
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
                'title' => 'Step 3 Scenario 2',
                'type' => 'text',
                'content' => <<<'EOD'
MANDATORY QUESTION: Present the full transcript of scenario 2 which is a discussion between Maya and Chris, and ask the user "what do you think is behind Chris`s objection, and do you think his use of a strawman argument is intentional or unintentional?. 

Maya says, “I think the city should invest more in public transportation to reduce traffic and pollution.”
Chris responds, “Oh, so you want to force everyone to give up their cars and take the bus? That’s totally unrealistic.”
Maya replies, “No, I just think we should make buses and trains more efficient.”
Chris shakes his head, “Your plan would destroy personal freedom and hurt car manufacturers. It’s not practical.”
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
                'title' => 'Step 4: The Psychology behind the use of the strawman argument',
                'type' => 'text',
                'content' => <<<'EOD'
INSTRUCTION: Explain the psychology of the strawman argument. The strawman fallacy taps into several human tendencies. First, it satisfies the need for cognitive ease—it’s mentally easier to argue against a simplified version of an idea than to engage with a complex, nuanced one. Second, it caters to ego protection: by attacking a weaker argument, people can feel intellectually superior or morally justified without the discomfort of genuine critical engagement. Third, confirmation bias reinforces this behavior. When someone already disagrees with an opponent, they are more likely to interpret that opponent’s words in the least charitable way possible, confirming their preexisting beliefs. Lastly, social identity theory suggests people are motivated to defend their group’s beliefs or values. Misrepresenting the opposing side makes one’s own side look stronger and more righteous, strengthening in-group cohesion.

MANDATORY_QUESTION: Can you link the reasons why people use strawman arguments with the psychology behind strawman arguments?
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
                'title' => 'Step 5: Countering the Strawman Argument',
                'type' => 'text',
                'content' => <<<'EOD'
INSTRUCTION: Explain how to Counter a Strawman Argument. 1. Clarify your original point. 2. Calmly restate your argument in clear, concise terms. Use phrases like: “That’s not quite what I meant. My actual point is…” 
“Let me clarify so we’re on the same page.” 3. Expose the distortion. Point out how your argument was misrepresented. For instance: “You’ve exaggerated my claim. I never said X; I said Y.” 4. Ask questions. Encourage critical thinking by prompting your opponent to engage with your real argument: “Can you explain how my position implies that?” 5. Stay composed. The strawman fallacy often aims to provoke emotional responses. Maintaining composure helps you appear credible and rational.

MANDATORY_QUESTION: Based on a scenario, where two people are debating the environment, Person A: “We should regulate carbon emissions to combat climate change.” Person B: “So you want to shut down all factories and destroy the economy?”, provide Person A some advise on countering Person B`s strawman argument.
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
                'title' => 'Step 6: Scenario 3',
                'type' => 'text',
                'content' => <<<'EOD'
MANDATORY_QUESTION: Present the full transcript shown below which are statements by two politicians discussing immigration, and ask the user "what do you think is behind Politician B's argument, and why is this argument so attractive to a large portion of an electorate in the EU?. 

Politician A: "I think we should provide a clear, legal pathway for undocumented immigrants who have lived here for years, paid taxes, and contributed to their communities. It’s about fairness and practicality."
Politician B: "My opponent wants to throw open the borders and let anyone walk in, no questions asked. That would be chaos and destroy the rule of law."

Feedback Explanation:
Politician A argued for a limited, conditional pathway to legal status for existing undocumented immigrants. Politician B distorted that into “open borders” — a completely different, extreme position. That distortion is the strawman, because it misrepresents the real argument to make it easier to attack.
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
                'title' => 'Step 7: Scenario 4',
                'type' => 'text',
                'content' => <<<'EOD'
MANDATORY_QUESTION: Present the full transcript of the statements made by two politicians below on  universal healthcare, and ask the user "what do you think is the problem with this response and why do you think normal people vote against their interests regarding access to universal medical care? 

Politician A: "I believe healthcare should be treated as a basic right. A universal system would ensure everyone can access medical care without going bankrupt, while still allowing private options for those who prefer them."
Politician B: "My opponent wants the government to control every aspect of your healthcare — what doctor you can see, what treatments you can get, and when. That’s socialism, plain and simple."

Explanation of Feedback: Politician A proposed universal access to healthcare, with room for private options. Politician B twisted that into “government control of all healthcare decisions” — a misrepresentation. This distortion makes A’s proposal sound authoritarian or extreme, which is the essence of the strawman fallacy.
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
                'title' => 'Step 8: Goodbye',
                'type' => 'text',
                'content' => <<<'EOD'
This is last step of a journey. There are no actions required. Do not ask any question that requires a response.
EOD,
                'order' => 8,
                'ratepass' => 1,
                'expected_output' => PromptDefaults::getDefaultTextStepOutput(),
                'expected_output_followup' => PromptDefaults::getDefaultTextStepOutputFollowUp(),
                'expected_output_retry' => PromptDefaults::getDefaultTextStepOutputRetry(),
                'config' => PromptDefaults::getDefaultStepConfig(),
                'rating_prompt' => PromptDefaults::getDefaultRatePrompt(),
                'maxattempts' => 1,
                'is_required' => true,
            ]
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
