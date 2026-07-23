<?php

namespace Database\Seeders;

use App\Domain\Events\Models\Event;
use Illuminate\Database\Seeder;

/**
 * Config padrão do checkout do seminário (spec 014): 2 categorias de
 * participante + campos + afiliações de exemplo. Idempotente; standalone
 * (rótulos maçônicos são apenas dados). Não recria o banco.
 */
class SeminarioCheckoutSeeder extends Seeder
{
    public function run(): void
    {
        $event = Event::query()->orderBy('id')->first();
        if ($event === null) {
            $this->command?->warn('Nenhum evento encontrado — pulei a config do checkout.');

            return;
        }

        $this->category($event, 'glmees', 'Irmão da GLMEES', 0, [
            ['loja', 'Loja', 'affiliation', true, null],
            ['cargo', 'Cargo na loja', 'conditional', false, ['question' => 'Possui cargo na loja?']],
        ]);

        $this->category($event, 'outra_potencia', 'Irmão de outra potência', 1, [
            ['potencia', 'Potência', 'text', true, null],
            ['pais', 'País', 'country', true, null],
            ['cidade', 'Cidade', 'city', true, null],
            ['cargo', 'Cargo na potência', 'conditional', false, ['question' => 'Possui cargo na potência?']],
        ]);

        // Lista real de lojas da GLMEES (database/data/glmees_lojas.php). Sync:
        // remove afiliações fora da lista (ex.: exemplos antigos) e faz upsert
        // com a ordem — idempotente e auto-corretivo em re-seed.
        $lojas = require database_path('data/glmees_lojas.php');
        $event->affiliations()->whereNotIn('name', $lojas)->forceDelete();
        foreach ($lojas as $i => $name) {
            $event->affiliations()->updateOrCreate(['name' => $name], ['sort' => $i, 'is_active' => true]);
        }
    }

    private function category(Event $event, string $key, string $label, int $sort, array $fields): void
    {
        $category = $event->participantCategories()->firstOrCreate(
            ['key' => $key],
            ['label' => $label, 'sort' => $sort, 'is_active' => true],
        );

        foreach ($fields as $i => [$fkey, $flabel, $type, $required, $config]) {
            $category->fields()->firstOrCreate(
                ['key' => $fkey],
                ['label' => $flabel, 'type' => $type, 'required' => $required, 'sort' => $i, 'config' => $config],
            );
        }
    }
}
