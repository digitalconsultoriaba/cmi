<?php

namespace Tests\Feature\Lifecycle;

use App\Domain\Events\Models\Role;
use App\Domain\Events\Models\SupportCase;
use App\Notifications\RefundCompletedPtBr;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

/**
 * US3 — estorno pela tesouraria (quickstart §US3).
 */
class RefundTest extends LifecycleTestCase
{
    use RefreshDatabase;

    private function treasurer()
    {
        $user = $this->buyer();
        $user->assignRole(Role::TREASURY);

        return $user;
    }

    /** Cancela um ingresso pago → caso de reembolso aberto. */
    private function refundCase(): array
    {
        [$buyer, $order] = $this->paidOrder();
        $this->actingAs($buyer)
            ->postJson("/api/tickets/{$order->tickets->first()->code}/cancel")
            ->assertOk();

        return [$buyer, $order, SupportCase::query()->where('type', 'refund')->firstOrFail()];
    }

    public function test_fila_lista_casos_abertos_e_exige_papel(): void
    {
        [, , $case] = $this->refundCase();

        $this->actingAs($this->buyer())->getJson('/api/treasury/refunds')->assertStatus(403);

        $response = $this->actingAs($this->treasurer())
            ->getJson('/api/treasury/refunds')->assertOk();

        $this->assertSame($case->id, $response->json('data.0.id'));
        $this->assertSame('200.00', $response->json('data.0.refundAmount'));
        $this->assertSame('card', $response->json('data.0.paymentMethod'));
    }

    public function test_estorno_de_cartao_via_provedor_fecha_o_caso(): void
    {
        Notification::fake();
        [$buyer, $order, $case] = $this->refundCase();

        $this->actingAs($this->treasurer())
            ->postJson("/api/treasury/refunds/{$case->id}/execute", [
                'justification' => 'Cancelamento dentro da política — devolução integral',
            ])->assertOk()
            ->assertJsonPath('data.status', 'finished');

        $ticket = $order->tickets()->first()->fresh();
        $this->assertNotNull($ticket->refunded_at);
        $this->assertSame('200.00', $ticket->refund_amount);

        // Devolução total → payment refunded
        $payment = $order->payments()->latest('paid_at')->first();
        $this->assertSame('refunded', $payment->fresh()->status->slug);
        $this->assertArrayHasKey('refund', $payment->fresh()->raw_response);

        Notification::assertSentTo($buyer, RefundCompletedPtBr::class);
    }

    public function test_estorno_parcial_mantem_payment_pago_com_registro(): void
    {
        [, $order, $case] = $this->refundCase();

        $this->actingAs($this->treasurer())
            ->postJson("/api/treasury/refunds/{$case->id}/execute", [
                'justification' => 'Devolução parcial acordada com o inscrito',
                'amount' => '100.00',
            ])->assertOk();

        $payment = $order->payments()->latest('paid_at')->first()->fresh();
        $this->assertSame('paid', $payment->status->slug, 'parcial não transiciona');
        $this->assertSame('100.00', $order->tickets()->first()->fresh()->refund_amount);
    }

    public function test_justificativa_obrigatoria_e_caso_fechado_recusa(): void
    {
        [, , $case] = $this->refundCase();
        $treasurer = $this->treasurer();

        $this->actingAs($treasurer)
            ->postJson("/api/treasury/refunds/{$case->id}/execute", [])
            ->assertUnprocessable()->assertJsonValidationErrors(['justification']);

        $this->actingAs($treasurer)
            ->postJson("/api/treasury/refunds/{$case->id}/execute", [
                'justification' => 'Devolução integral pela política',
            ])->assertOk();

        // Repetir sobre caso fechado → 409
        $this->actingAs($treasurer)
            ->postJson("/api/treasury/refunds/{$case->id}/execute", [
                'justification' => 'Tentativa duplicada de estorno',
            ])->assertStatus(409);
    }

    public function test_operador_comprador_nunca_estorna_o_proprio_pedido(): void
    {
        [$buyer, , $case] = $this->refundCase();
        $buyer->assignRole(Role::TREASURY); // mesmo com o papel!

        $this->actingAs($buyer)
            ->postJson("/api/treasury/refunds/{$case->id}/execute", [
                'justification' => 'Tentando estornar meu próprio pedido',
            ])
            ->assertStatus(403)
            ->assertJsonPath('type', 'forbidden');

        $this->assertSame('open', $case->fresh()->status);
    }

    public function test_estorno_operacional_de_pix_registra_origem(): void
    {
        Notification::fake();
        // Pedido pago via pix (settle + reconcile)
        $this->sellableEvent(['starts_at' => now()->addDays(60), 'allow_user_cancel' => true]);
        $buyer = $this->buyer();
        $code = $this->buy($buyer, [$this->item($this->individual)])->json('data.orders.0.code');
        $this->actingAs($buyer)->postJson("/api/orders/{$code}/checkout/pix")->assertCreated();
        $order = \App\Domain\Events\Models\Order::query()->where('code', $code)->first();
        $this->fakePix()->settle($order->payments()->latest('id')->first()->provider_charge_id);
        $this->artisan('payments:reconcile')->assertSuccessful();

        // Cancela → caso; estorna operacionalmente
        $this->actingAs($buyer)
            ->postJson("/api/tickets/{$order->tickets()->first()->code}/cancel")->assertOk();
        $case = SupportCase::query()->where('type', 'refund')->firstOrFail();

        $this->actingAs($this->treasurer())
            ->postJson("/api/treasury/refunds/{$case->id}/execute", [
                'justification' => 'Devolvido por transferência bancária em 03/07',
            ])->assertOk();

        $payment = $order->payments()->whereNotNull('paid_at')->latest('paid_at')->first()->fresh();
        $this->assertTrue($payment->raw_response['refund']['operational'] ?? false);
        $this->assertSame('refunded', $payment->status->slug);
    }
}
