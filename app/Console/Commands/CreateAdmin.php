<?php

namespace App\Console\Commands;

use App\Enums\UserStatus;
use App\Models\User;
use App\Modules\Identity\Domain\Models\Role;
use Database\Seeders\AccessControlSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class CreateAdmin extends Command
{
    protected $signature = 'library:admin:create
        {--name= : Nama administrator}
        {--email= : Email administrator}';

    protected $description = 'Membuat akun Super Admin Digital Library secara aman.';

    public function handle(): int
    {
        if (! Role::query()->where('slug', 'super-admin')->exists()) {
            $this->call('db:seed', [
                '--class' => AccessControlSeeder::class,
                '--force' => true,
            ]);
        }

        $name = (string) ($this->option('name') ?: $this->ask('Nama'));
        $email = (string) ($this->option('email') ?: $this->ask('Email'));
        $password = (string) $this->secret('Password');
        $confirmation = (string) $this->secret('Ulangi password');

        $validator = Validator::make([
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'password_confirmation' => $confirmation,
        ], [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:255', 'unique:users,email'],
            'password' => [
                'required',
                'confirmed',
                Password::min(10)->mixedCase()->numbers()->symbols(),
            ],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $user = User::query()->create([
            'name' => $name,
            'email' => mb_strtolower($email),
            'password' => $password,
            'status' => UserStatus::Active,
            'password_changed_at' => now(),
        ]);

        $superAdmin = Role::query()->where('slug', 'super-admin')->firstOrFail();
        $user->roles()->syncWithoutDetaching([$superAdmin->getKey()]);

        $this->info("Super Admin {$user->email} berhasil dibuat.");

        return self::SUCCESS;
    }
}
