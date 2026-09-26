<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;

class VerifyCsrfToken extends ValidateCsrfToken
{
    public function handle($request, Closure $next)
    {
        // Native Capacitor requests authenticate with a Sanctum bearer token.
        // Keep CSRF protection for the existing browser session flow.
        if ($request->bearerToken()) {
            return $next($request);
        }

        return parent::handle($request, $next);
    }
}
