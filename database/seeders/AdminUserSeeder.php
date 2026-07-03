<?php

namespace Database\Seeders;

use App\Domain\Events\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Usuários de desenvolvimento (NUNCA roda em produção — ver DatabaseSeeder).
 * Senhas dummy claramente de dev.
 */
class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            ['admin@dev.local', 'Admin Dev', Role::ADMIN],
            ['tesouraria@dev.local', 'Tesouraria Dev', Role::TREASURY],
            ['portaria@dev.local', 'Portaria Dev', Role::GATE],
        ];

        foreach ($users as [$email, $name, $role]) {
            $user = User::query()->updateOrCreate(
                ['email' => $email],
                ['name' => $name, 'password' => 'password', 'email_verified_at' => now()]
            );

            $user->roles()->syncWithoutDetaching([Role::idFor($role)]);
        }
    }
}
