<?php
namespace App\Console\Commands;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\{Hash, Validator};
class CreateAccount extends Command {
    protected $signature = 'flujo:usuario';
    protected $aliases = ['nexo:usuario'];
    protected $description = 'Crear una cuenta privada para Nexo';
    public function handle(): int {
        $data = ['name' => $this->ask('Nombre'), 'email' => $this->ask('Correo'), 'password' => $this->secret('Contraseña (mínimo 12 caracteres)')];
        $validator = Validator::make($data, ['name' => 'required|string|max:100', 'email' => 'required|email|max:255|unique:users,email', 'password' => 'required|string|min:12']);
        if ($validator->fails()) { foreach ($validator->errors()->all() as $error) $this->error($error); return self::FAILURE; }
        \Illuminate\Support\Facades\DB::transaction(function()use($data){
            $first=!User::exists(); $user=User::create($data); $user->role=$first?'admin':'editor'; $user->save();
            \App\Support\Spaces::create($user,'Safin','blue',true);
            \App\Support\Spaces::create($user,'Sodimac','yellow');
            \App\Support\Spaces::create($user,'Personal','purple');
        });
        $this->info('Cuenta creada. Ya puedes iniciar sesión.'); return self::SUCCESS;
    }
}
