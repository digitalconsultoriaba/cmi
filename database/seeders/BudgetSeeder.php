<?php

namespace Database\Seeders;

use App\Domain\Events\Models\BudgetPlan;
use App\Domain\Events\Models\Event;
use Illuminate\Database\Seeder;

/**
 * Orçamento de demonstração (dev) para o evento de exemplo — massa da spec 011.
 */
class BudgetSeeder extends Seeder
{
    public function run(): void
    {
        $event = Event::query()->where('slug', 'seminario-internacional-2026')->first();

        if ($event === null || BudgetPlan::query()->where('event_id', $event->id)->exists()) {
            return; // sem evento demo ou já semeado (idempotente)
        }

        $plan = BudgetPlan::query()->create([
            'event_id' => $event->id,
            'expected_paying' => 500,
            'expected_courtesy' => 50,
            'expected_staff' => 20,
            'expected_speakers' => 5,
            'other_revenue' => '0.00',
            'safety_margin_pct' => '10.00',
            'notes' => 'Orçamento de exemplo do seminário.',
        ]);

        foreach ([
            ['Locação do hotel', 'Espaço', '80000.00', 'contracted'],
            ['Sonorização', 'Som e iluminação', '26000.00', 'quoted'],
            ['Coffee break', 'Alimentação', '35000.00', 'planned'],
            ['Material gráfico', 'Gráfica', '12000.00', 'approved'],
            ['Fotografia e filmagem', 'Fotografia e filmagem', '18000.00', 'planned'],
        ] as [$desc, $cat, $total, $status]) {
            $plan->costItems()->create([
                'description' => $desc, 'category' => $cat, 'total_amount' => $total, 'status' => $status,
            ]);
        }

        foreach ([
            ['Primeiro lote', '250.00', 200],
            ['Segundo lote', '300.00', 200],
            ['Terceiro lote', '350.00', 200],
        ] as [$name, $price, $qty]) {
            $plan->ticketLots()->create([
                'name' => $name, 'unit_price' => $price, 'expected_quantity' => $qty,
            ]);
        }

        $plan->sponsorships()->create(['name' => 'Patrocínio Master', 'unit_value' => '100000.00', 'quantity' => 1, 'status' => 'confirmed']);
        $plan->sponsorships()->create(['name' => 'Patrocínio Ouro', 'unit_value' => '30000.00', 'quantity' => 1, 'status' => 'negotiating']);

        $plan->scenarios()->create(['key' => 'realistic', 'paying' => 500, 'avg_ticket' => '300.00', 'sponsorship' => '100000.00', 'cost' => '171000.00', 'other_revenue' => '0.00']);
    }
}
