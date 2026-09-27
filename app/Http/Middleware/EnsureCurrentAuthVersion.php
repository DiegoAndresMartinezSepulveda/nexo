<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EnsureCurrentAuthVersion
{
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::guard('web')->user();

        if (! $user || ! $request->hasSession()) {
            return $next($request);
        }

        $currentVersion = (int) $user->auth_version;
        $sessionVersion = $request->session()->get('nexo_auth_version');

        // Existing sessions are adopted once. A later password reset increments
        // the account version, so sessions created before the reset are rejected.
        if ($sessionVersion === null && $currentVersion === 1) {
            $request->session()->put('nexo_auth_version', $currentVersion);

            return $next($request);
        }

        if ((int) $sessionVersion !== $currentVersion) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return response()->json(['message' => 'Tu sesión terminó porque se actualizó la contraseña. Inicia sesión nuevamente.'], 401)
                ->header('Cache-Control', 'no-store, private');
        }

        return $next($request);
    }
}
