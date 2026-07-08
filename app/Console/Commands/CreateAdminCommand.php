<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class CreateAdminCommand extends Command
{
    protected $signature = 'admin:create';

    protected $description = 'Crea una cuenta con rol de administrador';

    public function handle(): int
    {
        $name = $this->ask('Nombre completo');
        $email = $this->ask('Correo electrónico');

        $validator = Validator::make(
            ['name' => $name, 'email' => $email],
            [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'email', 'unique:users,email'],
            ]
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $password = Str::password(14);

        $admin = User::create([
            'name' => $name,
            'email' => $email,
            'password' => bcrypt($password),
            'must_change_password' => true,
        ]);

        $admin->assignRole('admin');

        $this->newLine();
        $this->info('Cuenta de administrador creada correctamente.');
        $this->line("Correo: {$email}");
        $this->line("Contraseña temporal: {$password}");
        $this->newLine();
        $this->warn('Guarda esta contraseña ahora — no se volverá a mostrar. Se pedirá cambiarla en el primer inicio de sesión.');

        return self::SUCCESS;
    }
}
