<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RedirectToCanonicalDomain
{
    public function handle(Request $request, Closure $next)
    {
        $canonicalHost = parse_url(config('app.url'), PHP_URL_HOST);

        if ($canonicalHost && $request->getHost() !== $canonicalHost) {
            $url = config('app.url') . $request->getRequestUri();

            return redirect()->away($url, 301);
        }

        return $next($request);
    }
}
