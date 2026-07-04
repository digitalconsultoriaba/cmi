<?php

namespace Tests\Feature\Payment;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

/**
 * US2 — reconciliação: a garantia de baixa quando o webhook falha
 * (quickstart §US2; SC-003).
 */
class ReconcileTest extends PaymentTestCase
{
    use RefreshDatabase;

    public function test_webhook_perdido_reconciliacao_baixa(): void
    {
        [$buyer, $order] = $this->pendingOrder();
        $this->createPixCharge($buyer, $order);
        $payment = $order->payments()->latest('id')->first();

        // Pagou no banco, mas o webhook NUNCA chegou
        $this->fakePix()->settle($payment->provider_charge_id);

        $this->artisan('payments:reconcile')->assertSuccessful();

        $this->assertSame('paid', $order->fresh()->status->slug);
        $this->assertSame('reconciliation', $payment->fresh()->raw_response['source']);
    }

    public function test_cobranca_expirada_no_provedor_expira_o_payment(): void
    {
        [$buyer, $order] = $this->pendingOrder();
        $this->createPixCharge($buyer, $order);
        $payment = $order->payments()->latest('id')->first();

        // Cobrança sumiu/expirou no provedor (fake devolve expired p/ desconhecidas)
        \Illuminate\Support\Facades\Cache::forget('fakepix:'.$payment->provider_charge_id);

        $this->artisan('payments:reconcile')->assertSuccessful();

        $this->assertSame('expired', $payment->fresh()->status->slug);
        $this->assertSame('pending', $order->fresh()->status->slug, 'pedido pode gerar nova cobrança');
    }

    public function test_reconciliacao_e_idempotente(): void
    {
        [$buyer, $order] = $this->pendingOrder();
        $this->createPixCharge($buyer, $order);
        $payment = $order->payments()->latest('id')->first();
        $this->fakePix()->settle($payment->provider_charge_id);

        $this->artisan('payments:reconcile')->assertSuccessful();
        $paidAt = $payment->fresh()->paid_at;

        Carbon::setTestNow(now()->addMinutes(10));
        $this->artisan('payments:reconcile')->assertSuccessful();
        Carbon::setTestNow();

        $this->assertEquals($paidAt, $payment->fresh()->paid_at, '2ª execução inócua');
        $this->assertSame(1, $order->payments()->whereNotNull('paid_at')->count());
    }
}
