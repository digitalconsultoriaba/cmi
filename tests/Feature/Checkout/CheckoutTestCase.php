<?php

namespace Tests\Feature\Checkout;

use App\Domain\Events\Models\CourtesyVoucher;
use App\Domain\Events\Models\Event;
use App\Domain\Events\Models\ParticipantCategory;
use App\Domain\Events\Models\TicketLot;
use App\Domain\Events\Models\TicketType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

abstract class CheckoutTestCase extends TestCase
{
    use RefreshDatabase;

    protected Event $event;
    protected TicketType $type;
    protected TicketLot $lot;

    /** Evento do seminário: 1 tipo (R$250), 1 categoria com campo obrigatório. */
    protected function seminarEvent(array $eventAttrs = []): void
    {
        $this->event = Event::factory()->published()->create([
            'total_capacity' => 50,
            'reservation_ttl_minutes' => 30,
            'visible_on_site' => true,
            ...$eventAttrs,
        ]);

        $this->type = TicketType::factory()->create([
            'event_id' => $this->event->id, 'name' => 'Individual', 'price' => '250.00',
        ]);

        $this->lot = TicketLot::factory()->create([
            'event_id' => $this->event->id, 'name' => '1º lote', 'price_override' => '250.00',
        ]);

        $cat = $this->event->participantCategories()->create([
            'key' => 'glmees', 'label' => 'Irmão da GLMEES', 'sort' => 0, 'is_active' => true,
        ]);
        $cat->fields()->create([
            'key' => 'loja', 'label' => 'Loja', 'type' => 'affiliation', 'required' => true, 'sort' => 0,
        ]);
        $this->event->affiliations()->create(['name' => 'Loja A', 'sort' => 0, 'is_active' => true]);
    }

    protected function item(array $overrides = []): array
    {
        return array_merge([
            'ticket_type_id' => $this->type->id,
            'participant_name' => fake()->name(),
            'participant_email' => fake()->unique()->safeEmail(),
            'category_key' => 'glmees',
            'fields' => ['loja' => 'Loja A'],
        ], $overrides);
    }

    protected function guestPayload(array $items, array $buyer = []): array
    {
        return [
            'event_slug' => $this->event->slug,
            'buyer' => array_merge(['name' => 'Comprador', 'email' => 'comprador@ex.com'], $buyer),
            'items' => $items,
        ];
    }

    protected function voucher(string $status = CourtesyVoucher::AVAILABLE, ?int $ticketTypeId = null): CourtesyVoucher
    {
        return CourtesyVoucher::query()->create([
            'event_id' => $this->event->id,
            'status' => $status,
            'ticket_type_id' => $ticketTypeId,
        ]);
    }
}
