<?php

namespace Tests\Feature\Public;

use App\Domain\Events\Models\Order;
use App\Domain\Events\Models\Ticket;
use App\Domain\Events\Models\TicketStatus;
use Tests\Feature\Checkout\CheckoutTestCase;

/** Autenticação pública do ingresso (read-only, sem check-in) — spec 014. */
class TicketVerifyTest extends CheckoutTestCase
{
    private function ticket(string $status): Ticket
    {
        $this->seminarEvent();
        $code = $this->postJson('/api/public/orders', $this->guestPayload([$this->item()]))
            ->assertCreated()->json('data.order.code');
        $ticket = Order::query()->where('code', $code)->firstOrFail()->tickets()->firstOrFail();
        $ticket->update(['status_id' => TicketStatus::idFor($status)]);

        return $ticket->fresh();
    }

    public function test_ingresso_valido_autentica_sem_pii_alem_do_esperado(): void
    {
        $ticket = $this->ticket(TicketStatus::CONFIRMED);

        $this->getJson("/api/public/tickets/{$ticket->code}/verify")
            ->assertOk()
            ->assertJsonPath('data.valid', true)
            ->assertJsonPath('data.code', $ticket->code)
            ->assertJsonPath('data.participantName', $ticket->participant_name)
            ->assertJsonPath('data.eventName', $ticket->event->name);
    }

    public function test_codigo_inexistente_nao_autentica(): void
    {
        $this->seminarEvent();

        $this->getJson('/api/public/tickets/TCK-NAOEXISTE/verify')
            ->assertOk()
            ->assertJsonPath('data.valid', false)
            ->assertJsonMissingPath('data.participantName');
    }

    public function test_ingresso_cancelado_nao_autentica(): void
    {
        $ticket = $this->ticket(TicketStatus::CANCELLED);

        $this->getJson("/api/public/tickets/{$ticket->code}/verify")
            ->assertOk()
            ->assertJsonPath('data.valid', false);
    }
}
