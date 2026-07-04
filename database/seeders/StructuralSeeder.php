<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Seeders estruturais — rodam SEMPRE (qualquer ambiente): lookups + papéis.
 */
class StructuralSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            LookupSeeder::class,
            RoleSeeder::class,
            FinancialSeeder::class,
        ]);
    }
}
