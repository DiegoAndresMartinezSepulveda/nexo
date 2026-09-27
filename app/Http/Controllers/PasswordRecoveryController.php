<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Notifications\PasswordChangedNotification;
use App\Support\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Throwable;

class PasswordRecoveryController extends Controller
{
    public function forgotForm()
    {
        return $this->privatePage('auth.password-forgot');
    }

    public function resetForm(Request $request, string $token)
    {
        return $this->privatePage('auth.password-reset', [
            'token' => $token,
            'email' => (string) $request->query('email', ''),
        ]);
    }

    public function sendResetLink(Request $request)
    {
        $data = $request->validate(['email' => 'required|email|max:255']);

        try {
            Password::sendResetLink(['email' => mb_strtolower(trim($data['email']))]);
        } catch (Throwable $exception) {
            // Keep the response identical for existing and unknown accounts.
            // Mail transport details belong in the private server log only.
            Log::warning('Nexo password reset email could not be sent.', [
                'exception' => $exception::class,
            ]);
        }

        return redirect()->route('password.request')
            ->with('status', 'Si el correo coincide con una cuenta, recibirás un enlace para cambiar la contraseña. Revisa también Spam.');
    }

    public function resetPassword(Request $request)
    {
        $data = $request->validate([
            'token' => 'required|string',
            'email' => 'required|email|max:255',
            'password' => ['required', 'confirmed', PasswordRule::min(12)],
        ]);

        $status = Password::reset(
            [
                'email' => mb_strtolower(trim($data['email'])),
                'password' => $data['password'],
                'password_confirmation' => $request->input('password_confirmation'),
                'token' => $data['token'],
            ],
            function (User $user, string $password) use ($request) {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                    'auth_version' => ((int) $user->auth_version) + 1,
                ])->save();

                $user->tokens()->delete();
                DB::table('sessions')->where('user_id', $user->id)->delete();
                AuditLogger::record($request, 'security.password.reset', 'Contraseña restablecida por correo', 'user', (int) $user->id, $user->name, actorOverride: $user);

                try {
                    $user->notify(new PasswordChangedNotification);
                } catch (Throwable $exception) {
                    Log::warning('Nexo password change notice could not be sent.', [
                        'user_id' => $user->id,
                        'exception' => $exception::class,
                    ]);
                }

            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return $this->privatePage('auth.password-reset', [
                'token' => '',
                'email' => $data['email'],
                'success' => 'La contraseña se actualizó. Ya puedes iniciar sesión con tu nueva clave.',
            ]);
        }

        $message = match ($status) {
            Password::INVALID_TOKEN => 'El enlace venció o ya se utilizó. Solicita uno nuevo.',
            Password::RESET_THROTTLED => 'Espera un minuto antes de volver a intentarlo.',
            default => 'No encontramos una cuenta con ese correo. Revisa la dirección o contacta a la persona administradora.',
        };

        return $this->privatePage('auth.password-reset', [
            'token' => $data['token'],
            'email' => $data['email'],
            'errorMessage' => $message,
        ])->setStatusCode($status === Password::RESET_THROTTLED ? 429 : 422);
    }

    private function privatePage(string $view, array $data = [])
    {
        return response()->view($view, $data)
            ->header('Cache-Control', 'no-store, private')
            ->header('Referrer-Policy', 'no-referrer')
            ->header('X-Content-Type-Options', 'nosniff');
    }
}
