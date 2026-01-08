<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string  ...$roles
     * @return mixed
     */
    public function handle(Request $request, Closure $next, ...$roles)
    {
        if (!auth()->check()) {
            return redirect()->route('login');
        }

        $user = auth()->user();

        $normalizedRoles = [];
        foreach ($roles as $role) {
            foreach (explode(',', $role) as $segment) {
                $normalizedRoles[] = trim($segment);
            }
        }

        $normalizedRoles = array_filter($normalizedRoles);

        if ($user->isAdministrator()) {
            return $next($request);
        }

        foreach ($normalizedRoles as $role) {
            if ($role === UserRole::ADMINISTRATOR && $user->isAdministrator()) {
                return $next($request);
            }

            if ($user->hasRole($role)) {
                return $next($request);
            }
        }

        abort(403, 'You do not have permission to access this resource.');
    }
}
