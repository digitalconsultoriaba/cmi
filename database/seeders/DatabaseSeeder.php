<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Estruturais: sempre, em qualquer ambiente.
        $this->call(StructuralSeeder::class);

        // Demonstração: nunca em produção.
        if (! app()->environment('production')) {
            $this->call([
                AdminUserSeeder::class,
                SampleEventSeeder::class,
            ]);
        }
    }
}
