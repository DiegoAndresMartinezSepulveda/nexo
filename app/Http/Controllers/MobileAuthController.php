<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\LoginProtection;
use App\Support\TwoFactorAuth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;

class MobileAuthController extends Controller
{
    public function login(Request $request, LoginProtection $protection, TwoFactorAuth $twoFactor)
    {
        if ($blocked = $protection->blockedResponse($request)) {
            return $blocked;
        }

        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
            'captcha_id' => 'nullable|string|max:64',
            'captcha_answer' => 'nullable|string|max:20',
            'two_factor_code' => 'nullable|string|max:40',
        ]);

        if (! $protection->verifyCaptchaIfRequired($request)) {
            return $protection->failed($request, 'captcha_answer', 'La verificación no es correcta o venció.');
        }

        $user = User::where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            return $protection->failed($request, 'email', 'El correo o la contraseña no coinciden.');
        }

        if ($user->two_factor_confirmed_at) {
            if (empty($credentials['two_factor_code'])) {
                return response()->json([
                    'two_factor_required' => true,
                    'message' => 'Ingresa el código de tu aplicación Authenticator o un código de recuperación.',
                    'errors' => ['two_factor_code' => ['Ingresa el código de verificación.']],
                    ...$protection->challengeForRetry($request),
                ], 422)->header('Cache-Control', 'no-store, private');
            }

            if (! $twoFactor->verifyAndConsume($user, $credentials['two_factor_code'])) {
                return $protection->failed($request, 'two_factor_code', 'El código de verificación no es correcto o ya se usó.');
            }
        }

        $protection->succeeded($request);

        return response()->json([
            'user' => $user->profilePayload(),
            'token' => $user->createToken('nexo-android')->plainTextToken,
        ])->header('Cache-Control', 'no-store, private');
    }

    public function session(Request $request)
    {
        return response()->json([
            'user' => $request->user()->profilePayload(),
        ])->header('Cache-Control', 'no-store, private');
    }

    public function assetToken(Request $request)
    {
        $plain = Str::random(64);
        $accessToken = $request->user()->currentAccessToken();
        Cache::put('nexo-mobile-asset:'.hash('sha256', $plain), [
            'user_id' => $request->user()->id,
            'token_id' => $accessToken instanceof PersonalAccessToken ? $accessToken->id : null,
        ], now()->addMinutes(15));

        return response()->json(['token' => $plain]);
    }
}
