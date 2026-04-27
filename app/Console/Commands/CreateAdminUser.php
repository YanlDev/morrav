<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

#[Signature('app:create-admin {--email= : Email del administrador} {--name= : Nombre del administrador}')]
#[Description('Crea un usuario con rol Admin (acceso total al sistema).')]
class CreateAdminUser extends Command
{
    public function handle(): int
    {
        $name = $this->option('name') ?: text(
            label: 'Nombre completo',
            required: true,
            validate: fn (string $value) => strlen(trim($value)) < 2
                ? 'El nombre debe tener al menos 2 caracteres.'
                : null,
        );

        $email = $this->option('email') ?: text(
            label: 'Correo electrónico',
            required: true,
            validate: fn (string $value) => filter_var($value, FILTER_VALIDATE_EMAIL)
                ? null
                : 'El correo no es válido.',
        );

        $email = strtolower(trim($email));

        if (User::where('email', $email)->exists()) {
            $this->error("Ya existe un usuario con el correo {$email}.");

            return self::FAILURE;
        }

        $password = password(
            label: 'Contraseña (mínimo 8 caracteres)',
            required: true,
            validate: function (string $value) {
                $validator = Validator::make(
                    ['password' => $value],
                    ['password' => ['required', 'string', Password::min(8)]],
                );

                return $validator->fails()
                    ? $validator->errors()->first('password')
                    : null;
            },
        );

        $confirmation = password(label: 'Confirma la contraseña', required: true);

        if ($password !== $confirmation) {
            $this->error('Las contraseñas no coinciden.');

            return self::FAILURE;
        }

        $user = User::create([
            'name' => trim($name),
            'email' => $email,
            'password' => Hash::make($password),
            'role' => UserRole::Admin,
            'email_verified_at' => now(),
        ]);

        $this->info("Administrador creado: {$user->email} (id {$user->id}).");

        return self::SUCCESS;
    }
}
