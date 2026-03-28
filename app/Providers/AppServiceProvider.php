<?php

namespace App\Providers;

use App\Models\Setting;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Laravel\Pulse\Facades\Pulse;
use Opcodes\LogViewer\Facades\LogViewer;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        if (class_exists(LogViewer::class)) {
            LogViewer::auth(function ($request) {
                $user = $request->user();

                return $user && in_array($user->role, ['admin', 'administrator'], true);
            });
        }

        Gate::define('viewPulse', function ($user) {
            return $user && in_array($user->role, ['admin', 'administrator'], true);
        });

        // Override site config with database-backed settings
        $this->overrideSiteConfig();
    }

    /**
     * Override site.* config values with database-backed settings when available.
     * Uses a single batch query instead of 5 individual cache/DB lookups.
     */
    private function overrideSiteConfig(): void
    {
        try {
            if (! Schema::hasTable('settings')) {
                return;
            }

            $keys = [
                'site.signup_enabled',
                'site.signup_token_bundle',
                'site.referal_enabled',
                'site.referal_frequency',
                'site.referal_token_bundle',
            ];

            $settings = Setting::getMany($keys);

            $casts = [
                'site.signup_enabled'      => fn ($v) => (bool) (int) $v,
                'site.signup_token_bundle'  => fn ($v) => (int) $v,
                'site.referal_enabled'      => fn ($v) => (bool) (int) $v,
                'site.referal_frequency'    => fn ($v) => (int) $v,
                'site.referal_token_bundle' => fn ($v) => (int) $v,
            ];

            foreach ($casts as $key => $cast) {
                if (array_key_exists($key, $settings)) {
                    config([$key => $cast($settings[$key])]);
                }
            }
        } catch (\Throwable $e) {
            // Silently fail — DB may not be ready (migrations, testing, etc.)
        }
    }
}
