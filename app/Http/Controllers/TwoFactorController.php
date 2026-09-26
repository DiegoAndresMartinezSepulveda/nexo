<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\TwoFactorAuth;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class TwoFactorController extends Controller
{
    public function setup(Request $request, TwoFactorAuth $twoFactor): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $data = $request->validate(['password' => ['required', 'string', 'max:200']]);

        if (! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages(['password' => 'La contraseña no coincide.']);
        }
        if ($user->two_factor_confirmed_at) {
            throw ValidationException::withMessages(['two_factor' => 'La verificación en dos pasos ya está activa.']);
        }

        $secret = $twoFactor->newSecret();
        Cache::put($this->pendingSecretKey($user), $twoFactor->encryptedSecret($secret), now()->addMinutes(10));

        return response()->json([
            'secret' => $secret,
            'otpauth_uri' => $twoFactor->provisioningUri($user, $secret),
            'expires_in' => 600,
        ])->header('Cache-Control', 'no-store, private');
    }

    public function confirm(Request $request, TwoFactorAuth $twoFactor): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $data = $request->validate(['code' => ['required', 'string', 'size:6', 'regex:/^\d{6}$/']]);
        $encryptedSecret = Cache::get($this->pendingSecretKey($user));
        try {
            $secret = is_string($encryptedSecret) ? $twoFactor->decryptSecret($encryptedSecret) : null;
        } catch (\Throwable) {
            $secret = null;
        }
        $step = is_string($secret) ? $twoFactor->matchingStep($secret, $data['code']) : null;

        if (! is_string($secret) || $step === null) {
            throw ValidationException::withMessages(['code' => 'El código no coincide o venció el tiempo de configuración.']);
        }

        if ($user->two_factor_confirmed_at) {
            throw ValidationException::withMessages(['two_factor' => 'La verificación en dos pasos ya está activa.']);
        }

        $codes = $twoFactor->makeRecoveryCodes();
        DB::transaction(function () use ($user, $twoFactor, $secret, $step, $codes): void {
            $locked = User::query()->lockForUpdate()->findOrFail($user->id);
            if ($locked->two_factor_confirmed_at) {
                throw ValidationException::withMessages(['two_factor' => 'La verificación en dos pasos ya está activa.']);
            }
            $locked->forceFill([
                'two_factor_secret' => $twoFactor->encryptedSecret($secret),
                'two_factor_confirmed_at' => now(),
                'two_factor_last_used_step' => $step,
                'two_factor_recovery_codes' => $twoFactor->hashRecoveryCodes($codes),
            ])->save();
        });
        Cache::forget($this->pendingSecretKey($user));

        return response()->json([
            'enabled' => true,
            'recovery_codes' => $codes,
        ])->header('Cache-Control', 'no-store, private');
    }

    public function disable(Request $request, TwoFactorAuth $twoFactor): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $data = $request->validate([
            'password' => ['required', 'string', 'max:200'],
            'code' => ['required', 'string', 'max:40'],
        ]);

        if (! Hash::check($data['password'], $user->password)
            || ! $twoFactor->verifyAndConsume($user, $data['code'])) {
            throw ValidationException::withMessages(['code' => 'La contraseña o el código no coinciden.']);
        }

        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_confirmed_at' => null,
            'two_factor_last_used_step' => null,
            'two_factor_recovery_codes' => null,
        ])->save();

        return response()->json(['enabled' => false])->header('Cache-Control', 'no-store, private');
    }

    private function pendingSecretKey(User $user): string
    {
        return 'nexo:two-factor:setup:'.$user->id;
    }
}
