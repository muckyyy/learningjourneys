<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
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
    }
}
