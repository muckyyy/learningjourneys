<?php

namespace App\Http\Middleware;

use App\Models\LegalConsent;
use Closure;
use Illuminate\Http\Request;

class CheckLegalConsent
{
    /**
     * Paths that should be accessible without consent (to avoid redirect loops).
     */
    protected array $excludedPaths = [
        'legal/*',
        'logout',
        'email/verify*',
    ];

    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        // Skip for guests, AJAX requests, or excluded paths
        if (! $user || $request->expectsJson() || $this->isExcluded($request)) {
            return $next($request);
        }

        if (! LegalConsent::hasAcceptedAllRequired($user)) {
            session(['url.intended' => $request->url()]);
            return redirect()->route('legal.consent.accept');
        }

        return $next($request);
    }

    protected function isExcluded(Request $request): bool
    {
        foreach ($this->excludedPaths as $pattern) {
            if ($request->is($pattern)) {
                return true;
            }
        }

        return false;
    }
}
