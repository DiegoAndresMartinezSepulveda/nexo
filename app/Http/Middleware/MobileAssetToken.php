<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\PersonalAccessToken;

class MobileAssetToken
{
    public function handle(Request $request, Closure $next)
    {
        if (! $request->is('api/media/*') && ! $request->is('api/attachments/*')) {
            return $next($request);
        }

        $plain = $request->query('asset_token');
        if (! is_string($plain) || $plain === '') {
            return $next($request);
        }

        $data = Cache::get('nexo-mobile-asset:'.hash('sha256', $plain));
        $user = is_array($data) ? User::find($data['user_id'] ?? 0) : null;
        if ($user && ! empty($data['token_id'])) {
            abort_unless(PersonalAccessToken::find($data['token_id']), 401, 'El enlace del archivo expiró.');
        }

        abort_unless($user, 401, 'El enlace del archivo expiró.');

        Auth::setUser($user);
        // Asset URLs are requested by <img>/<a> from the native WebView, so
        // they cannot carry the Bearer header used by Angular's HttpClient.
        // Seed the Sanctum request guard as well; otherwise auth:sanctum runs
        // after this middleware and rejects the otherwise valid asset token.
        Auth::guard('sanctum')->setUser($user);
        $request->setUserResolver(fn () => $user);

        return $next($request);
    }
}
