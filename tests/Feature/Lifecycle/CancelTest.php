<?php

namespace Tests\Feature\Lifecycle;

use App\Domain\Events\Models\Order;
use App\Domain\Events\Models\SupportCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

/**
 * US1 — cancelamento pelo inscrito com a política de reembolso
 * (100% até D-7; piso CDC 7 dias da compra).
 */
class CancelTest extends LifecycleTestCase
{
    use RefreshDatabase;

    public function test_pedido_pendente_cancela_liberando_vagas_sem_caso(): void
    {
        $this->sellableEvent(['total_capacity' => 1, 'allow_user_cancel' => true]);
        $buyer = $this->buyer();
        $code = $this->buy($buyer, [$this->item($this->individual)])->json('data.orders.0.code');

        // Capacidade tomada
        $this->buy($this->buyer(), [$this->item($this->individual)])->assertStatus(409);

        $this->actingAs($buyer)->postJson("/api/orders/{$code}/cancel")->assertOk();

        $order = Order::query()->where('code', $code)->first();
        $this->assertSame('cancelled', $order->status->slug);
        $this->assertSame(0, SupportCase::query()->count(), 'não pago = sem caso');

        // Vaga liberada
        $this->buy($this->buyer(), [$this->item($this->individual)])->assertCreated();
    }

    public function test_ingresso_pago_longe_do_evento_gera_caso_de_reembolso_integral(): void
    {
        [$buyer, $order] = $this->paidOrder(); // evento a 60 dias
        $ticket = $order->tickets->first();

        $this->actingAs($buyer)
            ->postJson("/api/tickets/{$ticket->code}/cancel", ['reason' => 'Imprevisto'])
            ->assertOk();

        $fresh = $ticket->fresh();
        $this->assertSame('cancelled', $fresh->status->slug);
        $this->assertSame($buyer->id, $fresh->cancel_requested_by);
        $this->assertSame('Imprevisto', $fresh->cancel_reason);

        $case = SupportCase::query()->where('type', 'refund')->firstOrFail();
        $this->assertSame('open', $case->status);
        $this->assertSame('200.00', $case->refund_amount, '100% do snapshot');
        $this->assertSame($ticket->id, $case->ticket_id);
    }

    public function test_piso_cdc_compra_recente_devolve_mesmo_perto_do_evento(): void
    {
        $this->sellableEvent([
            'starts_at' => now()->addDays(3), // evento a <7 dias
            'sales_end_at' => now()->addDays(2),
            'allow_user_cancel' => true,
        ]);
        $buyer = $this->buyer();
        $code = $this->buy($buyer, [$this->item($this->individual)])->json('data.orders.0.code');
        $this->actingAs($buyer)->postJson("/api/orders/{$code}/checkout/card", [
            'token' => 'tok_ok_4242', 'installments' => 1,
        ])->assertOk();

        // Compra tem minutos — dentro dos 7 dias do CDC → 100%
        $order = Order::query()->where('code', $code)->first();
        $this->actingAs($buyer)
            ->postJson("/api/tickets/{$order->tickets->first()->code}/cancel")
            ->assertOk();

        $this->assertSame('200.00', SupportCase::query()->first()->refund_amount);
    }

    public function test_fora_da_janela_exige_confirmacao_e_cancela_sem_devolucao(): void
    {
        [$buyer, $order] = $this->paidOrder();
        $ticket = $order->tickets->first();

        // Avança: compra >7 dias atrás E evento a <7 dias
        Carbon::setTestNow(now()->addDays(55)); // evento estava a 60 dias

        $this->actingAs($buyer)
            ->postJson("/api/tickets/{$ticket->code}/cancel")
            ->assertStatus(409)
            ->assertJsonPath('type', 'refund_confirmation_required');

        $this->actingAs($buyer)
            ->postJson("/api/tickets/{$ticket->code}/cancel", ['confirm_no_refund' => true])
            ->assertOk();

        $this->assertSame('cancelled', $ticket->fresh()->status->slug);
        $this->assertSame(0, SupportCase::query()->count(), 'sem devolução = sem caso');
        Carbon::setTestNow();
    }

    public function test_guardas_de_cancelamento(): void
    {
        [$buyer, $order] = $this->paidOrder();
        $ticket = $order->tickets->first();

        // Flag desabilitada
        $this->event->update(['allow_user_cancel' => false]);
        $this->actingAs($buyer)->postJson("/api/tickets/{$ticket->code}/cancel")
            ->assertStatus(409)->assertJsonPath('type', 'cancel_disabled');
        $this->event->update(['allow_user_cancel' => true]);

        // Dono de outro → 403
        $this->actingAs($this->buyer())->postJson("/api/tickets/{$ticket->code}/cancel")
            ->assertStatus(403);

        // Cancela; segunda tentativa → terminal
        $this->actingAs($buyer)->postJson("/api/tickets/{$ticket->code}/cancel")->assertOk();
        $this->actingAs($buyer)->postJson("/api/tickets/{$ticket->code}/cancel")
            ->assertStatus(409)->assertJsonPath('type', 'terminal_status');
    }

    public function test_cancelar_pedido_pago_inteiro_gera_um_caso_com_amount_paid(): void
    {
        [$buyer, $order] = $this->paidOrder(3); // 3 × 200,00

        $this->actingAs($buyer)->postJson("/api/orders/{$order->code}/cancel")->assertOk();

        $this->assertSame('cancelled', $order->fresh()->status->slug);
        $order->fresh()->tickets->each(
            fn ($ticket) => $this->assertSame('cancelled', $ticket->status->slug)
        );

        $cases = SupportCase::query()->where('type', 'refund')->get();
        $this->assertCount(1, $cases, 'UM caso por pedido');
        $this->assertSame('600.00', $cases->first()->refund_amount);
        $this->assertNull($cases->first()->ticket_id);
    }
}
