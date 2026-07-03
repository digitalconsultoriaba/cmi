<?php

namespace Tests\Feature\Foundation;

use App\Domain\Events\Exceptions\DomainRuleViolation;
use App\Domain\Events\Models\CourtesyVoucher;
use App\Domain\Events\Models\Event;
use App\Domain\Events\Models\Order;
use App\Domain\Events\Models\OrderStatus;
use App\Domain\Events\Models\Ticket;
use App\Domain\Events\Models\TicketStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * US3 — cenário 7 de contracts/domain-derivations.md: situações terminais
 * rejeitam transição (DomainRuleViolation → 409).
 */
class StatusTransitionTest extends TestCase
{
    use RefreshDatabase;

    public function test_ticket_usado_rejeita_transicao(): void
    {
        $ticket = Ticket::factory()->status(TicketStatus::USED)->create();

        $this->expectException(DomainRuleViolation::class);
        $ticket->transitionTo(TicketStatus::CONFIRMED);
    }

    public function test_transicao_valida_de_ticket_funciona(): void
    {
        $ticket = Ticket::factory()->status(TicketStatus::RESERVED)->create();

        $ticket->transitionTo(TicketStatus::AWAITING_PAYMENT);

        $this->assertSame(TicketStatus::AWAITING_PAYMENT, $ticket->fresh()->status->slug);
    }

    public function test_pedido_cancelado_rejeita_transicao(): void
    {
        $order = Order::factory()->create([
            'status_id' => OrderStatus::idFor(OrderStatus::CANCELLED),
        ]);

        $this->expectException(DomainRuleViolation::class);
        $order->transitionTo(OrderStatus::PAID);
    }

    public function test_voucher_so_avanca_no_ciclo(): void
    {
        $event = Event::factory()->create();
        $voucher = CourtesyVoucher::query()->create([
            'event_id' => $event->id,
            'status' => CourtesyVoucher::DISTRIBUTED,
        ]);

        $voucher->transitionTo(CourtesyVoucher::REDEEMED);
        $this->assertSame(CourtesyVoucher::REDEEMED, $voucher->fresh()->status);

        $this->expectException(DomainRuleViolation::class);
        $voucher->transitionTo(CourtesyVoucher::AVAILABLE); // voltar é proibido
    }

    public function test_violacao_de_dominio_rende_409_na_shape_padrao(): void
    {
        Route::get('/api/_test/terminal', function () {
            throw new DomainRuleViolation('Situação terminal não permite transição.', 'terminal_status');
        });

        $this->getJson('/api/_test/terminal')
            ->assertStatus(409)
            ->assertJson([
                'message' => 'Situação terminal não permite transição.',
                'type' => 'terminal_status',
                'status' => 409,
            ]);
    }
}
