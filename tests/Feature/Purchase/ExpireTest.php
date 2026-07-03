<?php

namespace Tests\Feature\Purchase;

use App\Domain\Events\Exceptions\DomainRuleViolation;
use App\Domain\Events\Models\Order;
use App\Domain\Events\Models\OrderStatus;
use App\Domain\Events\Models\TicketStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

/**
 * US5 — expiração de reservas (quickstart §US5).
 */
class ExpireTest extends PurchaseTestCase
{
    use RefreshDatabase;

    public function test_pedido_vencido_expira_e_libera_as_vagas(): void
    {
        $this->sellableEvent(['total_capacity' => 1]);
        $this->lot->update(['quantity' => 1]);

        $code = $this->buy($this->buyer(), [$this->item($this->individual)])
            ->json('data.orders.0.code');

        // Capacidade tomada — nova compra recusa
        $this->buy($this->buyer(), [$this->item($this->individual)])->assertStatus(409);

        Carbon::setTestNow(now()->addMinutes(31));
        $this->artisan('orders:expire')->assertSuccessful();

        $order = Order::query()->where('code', $code)->firstOrFail();
        $this->assertSame(OrderStatus::EXPIRED, $order->status->slug);
        $this->assertSame(
            TicketStatus::CANCELLED,
            $order->tickets->first()->status->slug
        );
        $this->assertSame('Reserva expirada', $order->tickets->first()->cancel_reason);
        $this->assertSame(0, $this->lot->fresh()->sold_count, 'lote liberado');

        // Vaga liberada — nova compra passa
        $this->buy($this->buyer(), [$this->item($this->individual)])->assertCreated();
        Carbon::setTestNow();
    }

    public function test_pedido_dentro_do_prazo_fica_intacto(): void
    {
        $this->sellableEvent();

        $code = $this->buy($this->buyer(), [$this->item($this->individual)])
            ->json('data.orders.0.code');

        $this->artisan('orders:expire')->assertSuccessful();

        $this->assertSame(
            OrderStatus::PENDING,
            Order::query()->where('code', $code)->first()->status->slug
        );
    }

    public function test_expiracao_e_idempotente(): void
    {
        $this->sellableEvent();
        $code = $this->buy($this->buyer(), [$this->item($this->individual)])
            ->json('data.orders.0.code');

        Carbon::setTestNow(now()->addHours(1));
        $this->artisan('orders:expire')->assertSuccessful();
        $this->artisan('orders:expire')->assertSuccessful(); // 2ª execução inócua

        $order = Order::query()->where('code', $code)->first();
        $this->assertSame(OrderStatus::EXPIRED, $order->status->slug);
        Carbon::setTestNow();
    }

    public function test_pedido_expirado_e_terminal(): void
    {
        $this->sellableEvent();
        $code = $this->buy($this->buyer(), [$this->item($this->individual)])
            ->json('data.orders.0.code');

        Carbon::setTestNow(now()->addHours(1));
        $this->artisan('orders:expire')->assertSuccessful();
        Carbon::setTestNow();

        $order = Order::query()->where('code', $code)->first();

        $this->expectException(DomainRuleViolation::class);
        $order->transitionTo(OrderStatus::PAID);
    }
}
