<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Institution;
use App\Models\Journey;
use App\Models\JourneyCollection;
use App\Models\User;
use App\Services\MembershipService;
use App\Services\PromptDefaults;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Laravel\Prompts\Prompt;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        $membershipService = app(MembershipService::class);

        // Create a default institution
        $institution = Institution::create([
            'name' => 'Default Institution',
            'description' => 'Default institution for The Thinking Course platform',
            'contact_email' => 'admin@learningjourneys.com',
            'contact_phone' => '+1-555-0123',
            'address' => '123 Education Street, Learning City, LC 12345',
            'is_active' => true,
        ]);

        // Create an administrator user (ID 1)
        $admin = User::create([
            'name' => 'System Administrator',
            'email' => 'admin@learningjourneys.com',
            'password' => Hash::make('password'),
            'active_institution_id' => null,
            'email_verified_at' => now(),
        ]);
        $membershipService->syncAdministrator($admin);

        // Create an institution manager
        $institutionUser = User::create([
            'name' => 'Institution Manager',
            'email' => 'institution@learningjourneys.com',
            'password' => Hash::make('password'),
            'active_institution_id' => $institution->id,
            'email_verified_at' => now(),
        ]);
        $membershipService->assign($institutionUser, $institution, UserRole::INSTITUTION, true, $admin);

        // Create an editor user
        $editor = User::create([
            'name' => 'Content Editor',
            'email' => 'editor@learningjourneys.com',
            'password' => Hash::make('password'),
            'active_institution_id' => $institution->id,
            'email_verified_at' => now(),
        ]);
        $membershipService->assign($editor, $institution, UserRole::EDITOR, true, $admin);

        // Create a regular user
        $regularUser = User::create([
            'name' => 'John Learner',
            'email' => 'user@learningjourneys.com',
            'password' => Hash::make('password'),
            'active_institution_id' => $institution->id,
            'email_verified_at' => now(),
        ]);
        $membershipService->assign($regularUser, $institution, UserRole::REGULAR, true, $admin);

        // Create a sample journey collection
        $collection = JourneyCollection::create([
            'name' => 'Programming Fundamentals',
            'description' => 'A comprehensive collection covering the basics of programming',
            'certificate_prompt' => PromptDefaults::getDefaultCollectionCertPrompt(),
            'certificate_id' => null,
            'institution_id' => $institution->id,
            'is_active' => true,
        ]);

        // Create a sample journey collection
        $collection = JourneyCollection::create([
            'name' => 'Test Collection',
            'description' => 'A comprehensive collection covering the basics of programming',
            'certificate_prompt' => PromptDefaults::getDefaultCollectionCertPrompt(),
            'certificate_id' => null,
            'institution_id' => $institution->id,
            'is_active' => true,
        ]);

        $collection->editors()->syncWithoutDetaching([
            $editor->id => [
                'role' => 'editor',
                'assigned_by' => $admin->id,
            ],
        ]);
        /*
        // Create sample journeys with default prompts
        $journey1 = Journey::create([
            'title' => 'Introduction to Variables and Data Types',
            'description' => 'Learn the fundamental concepts of variables and data types in programming.',
            'master_prompt' => PromptDefaults::getDefaultMasterPrompt(),
            'report_prompt' => PromptDefaults::getDefaultReportPrompt(),
            'journey_collection_id' => $collection->id,
            'created_by' => $editor->id,
            'is_published' => true,
            'difficulty_level' => 'beginner',
            'estimated_duration' => 45,
        ]);

        $journey2 = Journey::create([
            'title' => 'Control Structures: Loops and Conditionals',
            'description' => 'Master the art of controlling program flow with loops and conditional statements.',
            'master_prompt' => PromptDefaults::getDefaultMasterPrompt(),
            'report_prompt' => PromptDefaults::getDefaultReportPrompt(),
            'journey_collection_id' => $collection->id,
            'created_by' => $editor->id,
            'is_published' => true,
            'difficulty_level' => 'intermediate',
            'estimated_duration' => 60,
        ]);

        $journey3 = Journey::create([
            'title' => 'Functions and Modular Programming',
            'description' => 'Understand how to create reusable code through functions and modular design principles.',
            'master_prompt' => PromptDefaults::getDefaultMasterPrompt(),
            'report_prompt' => PromptDefaults::getDefaultReportPrompt(),
            'journey_collection_id' => $collection->id,
            'created_by' => $editor->id,
            'is_published' => false, // Draft journey
            'difficulty_level' => 'advanced',
            'estimated_duration' => 90,
        ]); */

        // Seed Profile Fields and the Critical Thinking Journey
        $this->call([
            TokenBundleSeeder::class,
            ProfileFieldSeeder::class,
            JourneyCriticalThinkingSeeder::class,
            Logic101Seeder::class,
            FactOrFictionSeeder::class,
            TheAssumptionTrapSeeder::class,
            WhyCriticalThinkingSeeder::class,
            StrawManArgumentSeeder::class,
        ]);

        $this->command->info('Initial users created successfully!');
        $this->command->info('Sample journey collection and journeys created!');
        $this->command->info('Profile fields and journeys seeded!');
        $this->command->info('Admin: admin@learningjourneys.com / password');
        $this->command->info('Institution: institution@learningjourneys.com / password');
        $this->command->info('Editor: editor@learningjourneys.com / password');
        $this->command->info('User: user@learningjourneys.com / password');
    }
}
