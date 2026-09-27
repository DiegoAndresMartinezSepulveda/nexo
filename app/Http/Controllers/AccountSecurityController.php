<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Notifications\PasswordChangedNotification;
use App\Support\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;
use Throwable;

class AccountSecurityController extends Controller
{
    public function updatePassword(Request $request)
    {
        $data = $request->validate([
            'current_password' => 'required|string|max:200',
            'password' => ['required', 'confirmed', 'different:current_password', PasswordRule::min(12)],
        ]);

        /** @var User $user */
        $user = $request->user();

        if (! Hash::check($data['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => 'La contraseña actual no coincide.',
            ]);
        }

        $user->forceFill([
            'password' => Hash::make($data['password']),
            'remember_token' => Str::random(60),
            'auth_version' => ((int) $user->auth_version) + 1,
        ])->save();

        $currentToken = $user->currentAccessToken();
        $tokens = $user->tokens();
        if ($currentToken instanceof PersonalAccessToken) {
            $tokens->where('id', '!=', $currentToken->getKey())->delete();
        } else {
            $tokens->delete();
        }

        $currentSessionId = $request->hasSession() ? $request->session()->getId() : null;
        $sessions = DB::table('sessions')->where('user_id', $user->id);
        if ($currentSessionId) {
            $sessions->where('id', '!=', $currentSessionId);
        }
        $sessions->delete();

        AuditLogger::record($request, 'security.password.changed', 'Contraseña actualizada', 'user', (int) $user->id, $user->name);

        if ($request->hasSession() && $request->user('web')) {
            $request->session()->regenerate();
            $request->session()->regenerateToken();
            $request->session()->put('nexo_auth_version', (int) $user->auth_version);
        }

        try {
            $user->notify(new PasswordChangedNotification);
        } catch (Throwable $exception) {
            Log::warning('Nexo password change notice could not be sent.', [
                'user_id' => $user->id,
                'exception' => $exception::class,
            ]);
        }

        return response()->json(['message' => 'Tu contraseña se actualizó. Las demás sesiones se cerraron.'])
            ->header('Cache-Control', 'no-store, private');
    }
}
