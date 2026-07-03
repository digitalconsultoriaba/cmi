<?php

namespace Database\Seeders;

use App\Domain\Events\Models\Event;
use App\Domain\Events\Models\EventShirtModel;
use App\Domain\Events\Models\EventShirtSize;
use App\Domain\Events\Models\EventStatus;
use App\Domain\Events\Models\EventType;
use App\Domain\Events\Models\LandingBlock;
use App\Domain\Events\Models\TicketLot;
use App\Domain\Events\Models\TicketType;
use Illuminate\Database\Seeder;

/**
 * Evento de demonstração (dev): publicado, com 3 tipos, 2 lotes, camisas com e
 * sem estoque e blocos de landing de todos os tipos. Evolui nas specs 002+.
 */
class SampleEventSeeder extends Seeder
{
    public function run(): void
    {
        $event = Event::query()->updateOrCreate(
            ['slug' => 'seminario-internacional-2026'],
            [
                'name' => 'Seminário Internacional 2026',
                'description' => 'Evento de demonstração criado pelo seeder de desenvolvimento.',
                'event_type_id' => EventType::query()->where('name', 'Seminário')->value('id'),
                'starts_at' => now()->addDays(60),
                'ends_at' => now()->addDays(62),
                'location' => 'Centro de Convenções',
                'total_capacity' => 500,
                'sales_start_at' => now()->subDays(5),
                'sales_end_at' => now()->addDays(55),
                'pricing_mode' => 'paid',
                'allow_courtesy' => true,
                'courtesy_paid_threshold' => 10,
                'status_id' => EventStatus::idFor(EventStatus::PUBLISHED),
            ]
        );

        $individual = TicketType::query()->updateOrCreate(
            ['event_id' => $event->id, 'name' => 'Individual'],
            ['price' => '250.00', 'capacity' => 400, 'includes_shirt' => true, 'sort' => 0]
        );

        TicketType::query()->updateOrCreate(
            ['event_id' => $event->id, 'name' => 'Casal'],
            ['price' => '450.00', 'capacity' => 100, 'seats_per_ticket' => 2,
                'is_couple' => true, 'includes_shirt' => true, 'sort' => 1]
        );

        TicketType::query()->updateOrCreate(
            ['event_id' => $event->id, 'name' => 'Cortesia'],
            ['price' => '0.00', 'is_courtesy' => true, 'audience' => 'guest', 'sort' => 2]
        );

        TicketLot::query()->updateOrCreate(
            ['event_id' => $event->id, 'name' => '1º lote'],
            ['price_override' => '200.00', 'starts_at' => now()->subDays(5),
                'ends_at' => now()->addDays(20), 'quantity' => 200, 'sort' => 0]
        );

        TicketLot::query()->updateOrCreate(
            ['event_id' => $event->id, 'name' => '2º lote'],
            ['starts_at' => now()->addDays(20), 'ends_at' => now()->addDays(55), 'sort' => 1]
        );

        $unisex = EventShirtModel::query()->updateOrCreate(
            ['event_id' => $event->id, 'label' => 'Unissex'], ['sort' => 0]
        );
        $babylook = EventShirtModel::query()->updateOrCreate(
            ['event_id' => $event->id, 'label' => 'Baby look'], ['sort' => 1]
        );

        foreach (['P', 'M', 'G', 'GG'] as $i => $size) {
            // Unissex com estoque finito; baby look ilimitada
            EventShirtSize::query()->updateOrCreate(
                ['event_id' => $event->id, 'shirt_model_id' => $unisex->id, 'label' => $size],
                ['stock_quantity' => 50, 'sort' => $i]
            );
            EventShirtSize::query()->updateOrCreate(
                ['event_id' => $event->id, 'shirt_model_id' => $babylook->id, 'label' => $size],
                ['stock_quantity' => null, 'sort' => $i]
            );
        }

        $blocks = [
            ['hero', ['title' => 'Seminário Internacional 2026', 'subtitle' => 'Inscrições abertas']],
            ['text', ['body' => 'Três dias de palestras e comunhão.']],
            ['schedule', ['items' => [['day' => 'Sexta', 'description' => 'Abertura']]]],
            ['speakers', ['items' => [['name' => 'Palestrante Convidado']]]],
            ['faq', ['items' => [['q' => 'Posso transferir meu ingresso?', 'a' => 'Sim, até a data do evento.']]]],
            ['location', ['address' => 'Centro de Convenções']],
            ['cta', ['label' => 'Inscreva-se', 'target' => 'checkout']],
        ];

        foreach ($blocks as $i => [$type, $payload]) {
            LandingBlock::query()->updateOrCreate(
                ['event_id' => $event->id, 'type' => $type],
                ['sort' => $i, 'payload' => $payload, 'is_active' => true]
            );
        }
    }
}
