<?php

namespace Tests\Feature\Payment;

use App\Notifications\BoletoIssuedPtBr;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;

/**
 * US3 — boleto híbrido (quickstart §US3).
 */
class CheckoutBoletoTest extends PaymentTestCase
{
    use RefreshDatabase;

    public function test_cobranca_hibrida_com_linha_e_qr_pix_e_email(): void
    {
        Notification::fake();
        [$buyer, $order] = $this->pendingOrder();

        $response = $this->actingAs($buyer)
            ->postJson("/api/orders/{$order->code}/checkout/boleto")
            ->assertCreated();

        $response->assertJsonPath('data.method', 'boleto');
        $this->assertNotNull($response->json('data.boletoLine'));
        $this->assertNotNull($response->json('data.boletoBarcode'));
        $this->assertNotNull($response->json('data.pixQrCode'), 'híbrido: QR pix junto');

        // Vencimento respeita a reserva
        $this->assertLessThanOrEqual(
            Carbon::parse($order->reserved_until)->timestamp + 5,
            Carbon::parse($response->json('data.dueDate'))->timestamp
        );

        Notification::assertSentTo($buyer, BoletoIssuedPtBr::class);
    }

    public function test_liquidacao_via_reconciliacao_confirma(): void
    {
        [$buyer, $order] = $this->pendingOrder();
        $this->actingAs($buyer)->postJson("/api/orders/{$order->code}/checkout/boleto");
        $payment = $order->payments()->latest('id')->first();

        // Compensou dias depois — sem webhook
        $this->fakePix()->settle($payment->provider_charge_id);
        $this->artisan('payments:reconcile')->assertSuccessful();

        $this->assertSame('paid', $order->fresh()->status->slug);
    }

    public function test_meio_desabilitado_recusa(): void
    {
        [$buyer, $order] = $this->pendingOrder();
        $this->event->update(['allow_boleto' => false]);

        $this->actingAs($buyer)
            ->postJson("/api/orders/{$order->code}/checkout/boleto")
            ->assertStatus(409)->assertJsonPath('type', 'method_disabled');
    }
}
