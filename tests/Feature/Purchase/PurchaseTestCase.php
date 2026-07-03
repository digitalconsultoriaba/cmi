<?php

namespace Tests\Feature\Purchase;

use App\Domain\Events\Models\Event;
use App\Domain\Events\Models\TicketLot;
use App\Domain\Events\Models\TicketType;
use App\Models\User;
use Tests\TestCase;

abstract class PurchaseTestCase extends TestCase
{
    protected Event $event;

    protected TicketType $individual;

    protected TicketType $couple;

    protected TicketLot $lot;

    /** Evento vendável padrão: capacidade 50, lote global com override 200,00. */
    protected function sellableEvent(array $eventAttrs = []): void
    {
        $this->event = Event::factory()->published()->create([
            'total_capacity' => 50,
            'reservation_ttl_minutes' => 30,
            ...$eventAttrs,
        ]);

        $this->individual = TicketType::factory()->create([
            'event_id' => $this->event->id,
            'name' => 'Individual',
            'price' => '250.00',
        ]);

        $this->couple = TicketType::factory()->create([
            'event_id' => $this->event->id,
            'name' => 'Casal',
            'price' => '450.00',
            'is_couple' => true,
            'seats_per_ticket' => 2,
        ]);

        $this->lot = TicketLot::factory()->create([
            'event_id' => $this->event->id,
            'name' => '1º lote',
            'price_override' => '200.00',
        ]);
    }

    protected function buyer(): User
    {
        return User::factory()->create();
    }

    protected function item(TicketType $type, array $overrides = []): array
    {
        return array_merge([
            'ticket_type_id' => $type->id,
            'participant_name' => fake()->name(),
        ], $overrides);
    }

    protected function buy(User $buyer, array $items, array $extra = [])
    {
        return $this->actingAs($buyer)->postJson('/api/orders', array_merge([
            'event_slug' => $this->event->slug,
            'items' => $items,
        ], $extra));
    }
}
