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
                SeminarioCheckoutSeeder::class,   // categorias/campos/afiliações do checkout (014)
                SeminarioDemoSeeder::class,        // inscrições demo (individuais + blocos) + financeiro
                BudgetSeeder::class,
            ]);
        }
    }
}
