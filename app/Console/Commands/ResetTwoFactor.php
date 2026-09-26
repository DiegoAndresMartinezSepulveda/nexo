<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class ResetTwoFactor extends Command
{
    protected $signature = 'nexo:two-factor-reset {email : Correo de la cuenta que perdió el autenticador y sus códigos} {--force : Omitir la confirmación interactiva}';

    protected $description = 'Desactiva el segundo factor de una cuenta para recuperar el acceso';

    public function handle(): int
    {
        $user = User::query()->where('email', $this->argument('email'))->first();
        if (! $user) {
            $this->error('No se encontró esa cuenta.');

            return self::FAILURE;
        }

        if (! $this->option('force') && ! $this->confirm("¿Desactivar el segundo factor de {$user->email}? La contraseña no cambiará.")) {
            $this->warn('No se realizaron cambios.');

            return self::SUCCESS;
        }

        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_confirmed_at' => null,
            'two_factor_last_used_step' => null,
            'two_factor_recovery_codes' => null,
        ])->save();

        $this->info('Segundo factor desactivado. La contraseña y los datos de la cuenta siguen iguales.');

        return self::SUCCESS;
    }
}
