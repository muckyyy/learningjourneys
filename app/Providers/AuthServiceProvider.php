<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        \App\Models\Journey::class => \App\Policies\JourneyPolicy::class,
        \App\Models\JourneyCollection::class => \App\Policies\JourneyCollectionPolicy::class,
        \App\Models\Institution::class => \App\Policies\InstitutionPolicy::class,
        \App\Models\JourneyAttempt::class => \App\Policies\JourneyAttemptPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerPolicies();

        //
    }
}
