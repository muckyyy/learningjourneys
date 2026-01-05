<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Providers\RouteServiceProvider;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class SocialLoginController extends Controller
{
    private const PROVIDERS = ['google', 'facebook', 'linkedin', 'apple', 'microsoft'];

    public function __construct()
    {
        $this->middleware('guest');
    }

    public function redirect(string $provider): RedirectResponse
    {
        $provider = $this->normalizeProvider($provider);
        $this->ensureEnabled($provider);

        $driver = Socialite::driver($provider)->stateless();

        if ($provider === 'google') {
            $driver->scopes(['profile', 'email']);
        }

        if ($provider === 'facebook') {
            $driver->scopes(['email']);
        }

        if ($provider === 'linkedin') {
            $driver->scopes(['r_liteprofile', 'r_emailaddress']);
        }

        if ($provider === 'apple') {
            $driver->scopes(['name', 'email']);
        }

        if ($provider === 'microsoft') {
            $driver->scopes(['User.Read']);
        }

        return $driver->redirect();
    }

    public function callback(string $provider): RedirectResponse
    {
        $provider = $this->normalizeProvider($provider);
        $this->ensureEnabled($provider);

        try {
            $socialUser = Socialite::driver($provider)->stateless()->user();
        } catch (\Throwable $exception) {
            Log::error('Social OAuth callback failed', [
                'provider' => $provider,
                'message' => $exception->getMessage(),
            ]);

            return redirect()->route('login')
                ->with('error', 'Unable to sign in with ' . $this->displayName($provider) . ' right now. Please try again.');
        }

        if (! $socialUser->getEmail()) {
            return redirect()->route('login')
                ->with('error', 'Your ' . $this->displayName($provider) . ' account does not have an email address we can use.');
        }

        $user = User::where('email', $socialUser->getEmail())->first();

        if (! $user) {
            $user = User::create([
                'name' => $socialUser->getName() ?: $socialUser->getNickname() ?: $this->displayName($provider) . ' User',
                'email' => $socialUser->getEmail(),
                'password' => Hash::make(Str::random(32)),
                'email_verified_at' => now(),
            ]);

            event(new Registered($user));
        } else {
            if (isset($user->is_active) && ! $user->is_active) {
                return redirect()->route('login')
                    ->with('error', 'Your account is inactive. Please contact support.');
            }

            if (! $user->email_verified_at) {
                $user->forceFill(['email_verified_at' => now()])->save();
            }
        }

        Auth::login($user, true);

        return redirect()->intended(RouteServiceProvider::HOME);
    }

    private function ensureEnabled(string $provider): void
    {
        abort_unless($this->providerEnabled($provider), 404);
    }

    private function providerEnabled(string $provider): bool
    {
        return (bool) (
            config("services.$provider.enabled")
            && config("services.$provider.client_id")
            && config("services.$provider.client_secret")
            && config("services.$provider.redirect")
        );
    }

    private function normalizeProvider(string $provider): string
    {
        $provider = strtolower($provider);

        if (! in_array($provider, self::PROVIDERS, true)) {
            abort(404);
        }

        return $provider;
    }

    private function displayName(string $provider): string
    {
        return [
            'google' => 'Google',
            'facebook' => 'Facebook',
            'linkedin' => 'LinkedIn',
            'apple' => 'Apple',
            'microsoft' => 'Microsoft',
        ][$provider] ?? ucfirst($provider);
    }
}
