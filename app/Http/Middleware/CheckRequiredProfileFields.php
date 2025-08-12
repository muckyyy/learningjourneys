<?php

namespace App\Http\Middleware;

use App\Models\ProfileField;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CheckRequiredProfileFields
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        // Skip if user is not authenticated
        if (!Auth::check()) {
            return $next($request);
        }

        // Skip if already on profile edit page or updating profile
        if ($request->routeIs('profile.edit') || $request->routeIs('profile.update')) {
            return $next($request);
        }

        // Skip for AJAX requests
        if ($request->ajax() || $request->wantsJson()) {
            return $next($request);
        }

        // Skip for logout route
        if ($request->routeIs('logout')) {
            return $next($request);
        }

        $user = Auth::user();
        
        // Check if user has completed all required profile fields
        if (!$user->hasCompletedRequiredProfileFields()) {
            $missingFields = $user->getMissingRequiredProfileFields();
            
            return redirect()->route('profile.edit')
                ->with('warning', 'Please complete the following required profile fields: ' . implode(', ', $missingFields));
        }

        return $next($request);
    }
}
