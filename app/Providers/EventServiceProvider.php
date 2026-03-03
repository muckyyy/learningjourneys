<?php

namespace App\Providers;

use App\Listeners\GrantSignupTokenBundle;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Event;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        // SendEmailVerificationNotification is auto-registered by the framework.
        // GrantSignupTokenBundle is auto-discovered from app/Listeners via the base provider.
    ];

    /**
     * Register any events for your application.
     *
     * @return void
     */
    public function boot()
    {
        //
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }

    /**
     * The base EventServiceProvider already calls this, so we no-op to prevent
     * a second SendEmailVerificationNotification listener being registered.
     */
    protected function configureEmailVerification(): void
    {
        // Handled by the base Illuminate EventServiceProvider
    }
}
