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
     */
    private function overrideSiteConfig(): void
    {
        try {
            if (! Schema::hasTable('settings')) {
                return;
            }

            $signupEnabled = Setting::get('site.signup_enabled');
            if ($signupEnabled !== null) {
                config(['site.signup_enabled' => (bool) (int) $signupEnabled]);
            }

            $signupBundle = Setting::get('site.signup_token_bundle');
            if ($signupBundle !== null) {
                config(['site.signup_token_bundle' => (int) $signupBundle]);
            }

            $referalEnabled = Setting::get('site.referal_enabled');
            if ($referalEnabled !== null) {
                config(['site.referal_enabled' => (bool) (int) $referalEnabled]);
            }

            $referalFrequency = Setting::get('site.referal_frequency');
            if ($referalFrequency !== null) {
                config(['site.referal_frequency' => (int) $referalFrequency]);
            }

            $referalBundle = Setting::get('site.referal_token_bundle');
            if ($referalBundle !== null) {
                config(['site.referal_token_bundle' => (int) $referalBundle]);
            }
        } catch (\Throwable $e) {
            // Silently fail — DB may not be ready (migrations, testing, etc.)
        }
    }
}
