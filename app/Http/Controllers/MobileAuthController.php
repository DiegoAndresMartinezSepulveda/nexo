<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\LoginProtection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;

class MobileAuthController extends Controller
{
    public function login(Request $request, LoginProtection $protection)
    {
        if ($blocked = $protection->blockedResponse($request)) {
            return $blocked;
        }

        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
            'captcha_id' => 'nullable|string|max:64',
            'captcha_answer' => 'nullable|string|max:20',
        ]);

        if (! $protection->verifyCaptchaIfRequired($request)) {
            return $protection->failed($request, 'captcha_answer', 'La verificación no es correcta o venció.');
        }

        $user = User::where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            return $protection->failed($request, 'email', 'El correo o la contraseña no coinciden.');
        }

        $protection->succeeded($request);

        return response()->json([
            'user' => $user->profilePayload(),
            'token' => $user->createToken('nexo-android')->plainTextToken,
        ]);
    }

    public function session(Request $request)
    {
        return response()->json([
            'user' => $request->user()->profilePayload(),
        ]);
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
