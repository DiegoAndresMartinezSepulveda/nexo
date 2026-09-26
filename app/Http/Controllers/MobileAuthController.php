<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;
use Illuminate\Validation\ValidationException;

class MobileAuthController extends Controller
{
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => 'El correo o la contraseña no coinciden.',
            ]);
        }

        return response()->json([
            'user' => $user->only('id', 'name', 'email', 'role'),
            'token' => $user->createToken('nexo-android')->plainTextToken,
        ]);
    }

    public function session(Request $request)
    {
        return response()->json([
            'user' => $request->user()->only('id', 'name', 'email', 'role'),
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
