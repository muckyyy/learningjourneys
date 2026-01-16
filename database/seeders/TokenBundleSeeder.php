<?php

namespace Database\Seeders;

use App\Models\TokenBundle;
use Illuminate\Database\Seeder;

class TokenBundleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $bundles = [
            [
                'name' => 'Starter Sparks',
                'slug' => 'starter-sparks',
                'description' => 'Kickstart your practice with enough tokens for a full learning sprint.',
                'token_amount' => 250,
                'price_cents' => 1900,
                'currency' => 'USD',
                'expires_after_days' => 365,
                'is_active' => true,
                'metadata' => [
                    'tagline' => 'Perfect for new members',
                    'badge' => 'popular',
                ],
            ],
            [
                'name' => 'Growth Boost',
                'slug' => 'growth-boost',
                'description' => 'Designed for consistent learners who need a steady stream of guided journeys.',
                'token_amount' => 750,
                'price_cents' => 4900,
                'currency' => 'USD',
                'expires_after_days' => 365,
                'is_active' => true,
                'metadata' => [
                    'tagline' => 'Best monthly value',
                    'includes_support' => true,
                ],
            ],
            [
                'name' => 'Mastery Vault',
                'slug' => 'mastery-vault',
                'description' => 'High-volume bundle with extended expiry for teams and power learners.',
                'token_amount' => 2000,
                'price_cents' => 10900,
                'currency' => 'USD',
                'expires_after_days' => 730,
                'is_active' => true,
                'metadata' => [
                    'tagline' => 'Extended runway',
                    'priority_support' => true,
                ],
            ],
        ];

        foreach ($bundles as $bundle) {
            TokenBundle::updateOrCreate(
                ['slug' => $bundle['slug']],
                $bundle
            );
        }
    }
}
