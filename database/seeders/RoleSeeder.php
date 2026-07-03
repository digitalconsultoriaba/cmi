<?php

namespace Database\Seeders;

use App\Domain\Events\Models\Role;
use Illuminate\Database\Seeder;

/**
 * Os 4 papéis do RBAC (specs/001-fundacao/contracts/rbac.md). Slugs estáveis.
 */
class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            Role::ADMIN => 'Administração',
            Role::TREASURY => 'Tesouraria',
            Role::GATE => 'Portaria',
            Role::ATTENDEE => 'Inscrito',
        ];

        foreach ($roles as $slug => $name) {
            Role::query()->updateOrCreate(['slug' => $slug], ['name' => $name, 'is_active' => true]);
        }
    }
}
