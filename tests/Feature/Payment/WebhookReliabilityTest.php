<?php

namespace Tests\Feature\Payment;

use App\Domain\Events\Models\WebhookEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * US2 — o webhook nunca é fonte única; duplicatas e forjados sem efeito
 * (quickstart §US2; SC-002/SC-004).
 */
class WebhookReliabilityTest extends PaymentTestCase
{
    use RefreshDatabase;

    public function test_webhook_duplicado_baixa_uma_unica_vez(): void
    {
        [$buyer, $order] = $this->pendingOrder();
        $this->createPixCharge($buyer, $order);

        $this->settleAndNotify($order)->assertJsonPath('data.result', 'ok');

        // Mesmo evento entregue de novo → ignorado, sem segunda baixa
        $payment = $order->payments()->latest('id')->first();
        $this->postJson('/api/webhooks/sicoob', [
            'txid' => $payment->provider_charge_id,
            'event' => 'pix.received',
        ], ['X-Webhook-Secret' => config('payments.sicoob.webhook_secret')])
            ->assertOk()
            ->assertJsonPath('data.result', 'ignored');

        $this->assertSame(1, $order->payments()->whereNotNull('paid_at')->count());
        $this->assertSame('paid', $order->fresh()->status->slug);
        $this->assertSame(1, WebhookEvent::query()->where('external_id', $payment->provider_charge_id)->count());
    }

    public function test_assinatura_invalida_rejeita_sem_efeito_e_registra(): void
    {
        [$buyer, $order] = $this->pendingOrder();
        $this->createPixCharge($buyer, $order);
        $payment = $order->payments()->latest('id')->first();
        $this->fakePix()->settle($payment->provider_charge_id);

        $this->postJson('/api/webhooks/sicoob', [
            'txid' => $payment->provider_charge_id,
        ], ['X-Webhook-Secret' => 'segredo-errado'])
            ->assertStatus(401);

        // Sem efeito no pedido; auditoria registrada
        $this->assertSame('pending', $order->fresh()->status->slug);
        $this->assertSame(1, WebhookEvent::query()->where('result', 'error')->count());
    }

    public function test_corpo_dizendo_pago_sem_confirmacao_do_provedor_nao_baixa(): void
    {
        [$buyer, $order] = $this->pendingOrder();
        $this->createPixCharge($buyer, $order);
        $payment = $order->payments()->latest('id')->first();

        // NÃO fazemos settle: o provedor ainda considera pendente
        $this->postJson('/api/webhooks/sicoob', [
            'txid' => $payment->provider_charge_id,
            'event' => 'pix.received',
            'status' => 'CONCLUIDA', // corpo mente
        ], ['X-Webhook-Secret' => config('payments.sicoob.webhook_secret')])
            ->assertOk()
            ->assertJsonPath('data.result', 'ignored');

        $this->assertSame('pending', $order->fresh()->status->slug, 'reconsulta manda');
    }

    public function test_payload_bruto_e_persistido_para_auditoria(): void
    {
        [$buyer, $order] = $this->pendingOrder();
        $this->createPixCharge($buyer, $order);

        $this->settleAndNotify($order);

        $event = WebhookEvent::query()->latest('id')->first();
        $this->assertSame('pix.received', $event->payload['event']);
        $this->assertNotNull($event->processed_at);
        $this->assertSame('ok', $event->result);
    }
}
