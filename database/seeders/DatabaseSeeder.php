<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Journey;
use App\Models\JourneyCollection;
use App\Models\User;
use App\Services\PromptDefaults;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        // Reset cached roles and permissions
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // Create permissions
        $permissionsMap = UserRole::permissionsMap();
        $allPermissions = collect($permissionsMap)->flatten()->unique();
        foreach ($allPermissions as $permissionName) {
            Permission::firstOrCreate(['name' => $permissionName, 'guard_name' => 'web']);
        }

        // Create roles and assign permissions
        foreach ($permissionsMap as $roleName => $permissions) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
            $role->syncPermissions($permissions);
        }

        // Create an administrator user (ID 1)
        $admin = User::create([
            'name' => 'System Administrator',
            'email' => 'admin@learningjourneys.com',
            'password' => Hash::make('password'),
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $admin->assignRole(UserRole::ADMINISTRATOR);

        // Create a regular user
        $regularUser = User::create([
            'name' => 'John Learner',
            'email' => 'user@learningjourneys.com',
            'password' => Hash::make('password'),
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $regularUser->assignRole(UserRole::REGULAR);

        // Create a sample journey collection
        $collection = JourneyCollection::create([
            'name' => 'Programming Fundamentals',
            'description' => 'A comprehensive collection covering the basics of programming',
            'certificate_prompt' => PromptDefaults::getDefaultCollectionCertPrompt(),
            'certificate_id' => null,
            'is_active' => true,
        ]);

        // Create a sample journey collection
        $collection = JourneyCollection::create([
            'name' => 'Test Collection',
            'description' => 'A comprehensive collection covering the basics of programming',
            'certificate_prompt' => PromptDefaults::getDefaultCollectionCertPrompt(),
            'certificate_id' => null,
            'is_active' => true,
        ]);

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
            LegalDocumentSeeder::class,
        ]);

        $this->command->info('Initial users created successfully!');
        $this->command->info('Sample journey collection and journeys created!');
        $this->command->info('Profile fields and journeys seeded!');
        $this->command->info('Admin: admin@learningjourneys.com / password');
        $this->command->info('User: user@learningjourneys.com / password');
    }
}
