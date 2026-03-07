<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Spatie\Permission\PermissionRegistrar;

class SetPermissionsTeam
{
    public function handle(Request $request, Closure $next)
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);

        return $next($request);
    }
}
