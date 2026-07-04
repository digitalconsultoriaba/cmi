<?php

namespace Tests\Feature\Lifecycle;

use App\Domain\Events\Models\Order;
use App\Domain\Events\Models\Role;
use App\Domain\Events\Models\SupportCase;
use App\Notifications\EventCancelledPtBr;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

/**
 * US5 — cascata do cancelamento do evento (quickstart §US5).
 */
class EventCascadeTest extends LifecycleTestCase
{
    use RefreshDatabase;

    public function test_cascata_cancela_vivos_abre_devolucoes_e_avisa(): void
    {
        Notification::fake();

        // Pedido pago
        [$paidBuyer, $paidOrder] = $this->paidOrder();

        // Pedido pendente do mesmo evento
        $pendingBuyer = $this->buyer();
        $pendingCode = $this->buy($pendingBuyer, [$this->item($this->individual)])
            ->json('data.orders.0.code');

        // Pedido já cancelado (histórico intocado)
        $cancelledBuyer = $this->buyer();
        $cancelledCode = $this->buy($cancelledBuyer, [$this->item($this->individual)])
            ->json('data.orders.0.code');
        $this->actingAs($cancelledBuyer)->postJson("/api/orders/{$cancelledCode}/cancel")
            ->assertOk();

        // Admin cancela o EVENTO
        $admin = $this->buyer();
        $admin->assignRole(Role::ADMIN);
        $this->actingAs($admin)
            ->postJson("/api/admin/events/{$this->event->id}/cancel", [
                'reason' => 'Força maior',
            ])->assertOk();

        // Pago: cancelado + caso 100%
        $freshPaid = $paidOrder->fresh();
        $this->assertSame('cancelled', $freshPaid->status->slug);
        $this->assertSame('Evento cancelado', $freshPaid->tickets->first()->cancel_reason);

        $cases = SupportCase::query()->where('type', 'refund')->get();
        $this->assertCount(1, $cases, 'caso APENAS para o pago');
        $this->assertSame('200.00', $cases->first()->refund_amount, '100% do amountPaid');
        $this->assertSame($paidOrder->id, $cases->first()->order_id);

        // Pendente: cancelado sem caso
        $this->assertSame(
            'cancelled',
            Order::query()->where('code', $pendingCode)->first()->status->slug
        );

        // E-mails para os afetados pela cascata (pago + pendente)
        Notification::assertSentTo($paidBuyer, EventCancelledPtBr::class);
        Notification::assertSentTo($pendingBuyer, EventCancelledPtBr::class);
        Notification::assertNotSentTo($cancelledBuyer, EventCancelledPtBr::class);
    }

    public function test_apos_cascata_compra_e_pagamento_sao_recusados(): void
    {
        [, $order] = $this->paidOrder();

        $admin = $this->buyer();
        $admin->assignRole(Role::ADMIN);
        $this->actingAs($admin)
            ->postJson("/api/admin/events/{$this->event->id}/cancel", ['reason' => 'Motivo'])
            ->assertOk();

        // Comprar → 409 (evento não vendável)
        $this->buy($this->buyer(), [$this->item($this->individual)])
            ->assertStatus(409)->assertJsonPath('type', 'sales_closed');

        // Pagar pedido cancelado → 409
        $buyer = \App\Models\User::query()->whereKey($order->buyer_user_id)->first();
        $this->actingAs($buyer)
            ->postJson("/api/orders/{$order->code}/checkout/pix")
            ->assertStatus(409);
    }

    public function test_cascata_e_resiliente_a_falha_em_um_pedido(): void
    {
        Notification::fake();
        [, $paidOrder] = $this->paidOrder();

        // Segundo pedido pago legítimo
        $otherBuyer = $this->buyer();
        $otherCode = $this->buy($otherBuyer, [$this->item($this->individual)])
            ->json('data.orders.0.code');
        $this->actingAs($otherBuyer)->postJson("/api/orders/{$otherCode}/checkout/card", [
            'token' => 'tok_ok_4242', 'installments' => 1,
        ])->assertOk();

        // Injeta falha determinística: a política explode SÓ para o 1º pedido
        $targetId = $paidOrder->id;
        $this->mock(\App\Domain\Events\Services\RefundPolicy::class, function ($mock) use ($targetId) {
            $mock->shouldReceive('refundableForEventCancellation')
                ->andReturnUsing(function ($order) use ($targetId) {
                    if ($order->id === $targetId) {
                        throw new \RuntimeException('falha simulada na cascata');
                    }

                    return $order->amountPaid();
                });
            $mock->shouldReceive('refundableAmount')->andReturn('0.00');
            $mock->shouldReceive('refundableForOrder')->andReturn('0.00');
        });

        $admin = $this->buyer();
        $admin->assignRole(Role::ADMIN);

        // Cancelamento do evento NÃO explode; o pedido saudável é processado
        $this->actingAs($admin)
            ->postJson("/api/admin/events/{$this->event->id}/cancel", ['reason' => 'Motivo'])
            ->assertOk();

        $this->assertSame(
            'cancelled',
            Order::query()->where('code', $otherCode)->first()->status->slug,
            'pedido saudável processado apesar da falha no outro'
        );
    }
}
