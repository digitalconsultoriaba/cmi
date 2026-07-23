<?php

namespace Tests\Feature\Payment;

use App\Domain\Events\Models\Order;
use App\Notifications\PaymentConfirmedPtBr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\Feature\Checkout\CheckoutTestCase;

/**
 * Webhook ASAAS: origem por `asaas-access-token`, dedupe por id do evento,
 * correlação por checkoutSession e RECONSULTA antes da baixa — o corpo do webhook
 * nunca é fonte de verdade. Captura bandeira/final/parcelas do cartão. Spec 015.
 */
class AsaasWebhookTest extends CheckoutTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'payments.card_driver' => 'asaas',
            'payments.asaas.api_key' => 'test-key',
            'payments.asaas.base_url' => 'https://asaas.test/v3',
            'payments.asaas.frontend_url' => 'https://front.test',
            'payments.asaas.webhook_secret' => 'wh-secret',
        ]);
    }

    /**
     * Um único Http::fake por teste (evita empilhar handlers): POST /checkouts
     * cria; /v3/installments/{id} é a consulta do parcelamento; /v3/payments é a
     * reconsulta. $paid define o pagamento devolvido na reconsulta.
     */
    private function fakeAsaas(?array $paid = null, ?array $installment = null): void
    {
        $paid ??= [
            'id' => 'pay_1', 'status' => 'CONFIRMED', 'value' => 250.00,
            'confirmedDate' => now()->toDateString(), 'checkoutSession' => 'chk_1',
        ];

        Http::fake(function ($request) use ($paid, $installment) {
            if ($request->method() === 'POST' && str_contains($request->url(), '/v3/checkouts')) {
                return Http::response(['id' => 'chk_1', 'link' => 'https://asaas.test/pay/chk_1']);
            }
            if ($installment !== null && str_contains($request->url(), '/v3/installments/')) {
                return Http::response($installment);
            }
            if (str_contains($request->url(), '/v3/payments')) {
                return Http::response(['object' => 'list', 'data' => [$paid]]);
            }

            return Http::response([], 404);
        });
    }

    /** Cria pedido pendente + checkout de cartão e devolve [code, Order]. */
    private function orderWithCheckout(): array
    {
        $this->seminarEvent(['allow_card' => true]);

        $code = $this->postJson('/api/public/orders', $this->guestPayload([$this->item()]))
            ->assertCreated()->json('data.order.code');

        $this->postJson("/api/public/orders/{$code}/checkout/card", ['installments' => 1])->assertOk();

        return [$code, Order::query()->where('code', $code)->firstOrFail()];
    }

    private function webhookPayload(string $eventId = 'evt_1'): array
    {
        // O ASAAS liga o pagamento ao checkout por checkoutSession (= chk_1, o
        // provider_charge_id gravado). externalReference vem null no pagamento.
        return [
            'id' => $eventId,
            'event' => 'PAYMENT_CONFIRMED',
            'payment' => [
                'id' => 'pay_1', 'checkoutSession' => 'chk_1',
                'externalReference' => null, 'status' => 'CONFIRMED',
            ],
        ];
    }

    public function test_webhook_valido_baixa_pedido_e_notifica(): void
    {
        Notification::fake();
        $this->fakeAsaas();
        [, $order] = $this->orderWithCheckout();

        $this->postJson('/api/webhooks/asaas', $this->webhookPayload(), ['asaas-access-token' => 'wh-secret'])
            ->assertOk()
            ->assertJsonPath('data.result', 'ok');

        $this->assertSame('paid', $order->fresh()->status->slug);
        Notification::assertSentTo($order->fresh()->buyerUser, PaymentConfirmedPtBr::class);
    }

    public function test_token_invalido_rejeita_com_401(): void
    {
        $this->fakeAsaas();
        $this->orderWithCheckout();

        $this->postJson('/api/webhooks/asaas', $this->webhookPayload(), ['asaas-access-token' => 'errado'])
            ->assertStatus(401);

        $this->assertDatabaseHas('webhook_events', ['provider' => 'asaas', 'result' => 'error']);
    }

    public function test_evento_duplicado_e_ignorado(): void
    {
        $this->fakeAsaas();
        $this->orderWithCheckout();

        $this->postJson('/api/webhooks/asaas', $this->webhookPayload(), ['asaas-access-token' => 'wh-secret'])
            ->assertOk()->assertJsonPath('data.result', 'ok');

        // Mesmo id de evento → dedupe estrutural (unique provider, external_id).
        $this->postJson('/api/webhooks/asaas', $this->webhookPayload(), ['asaas-access-token' => 'wh-secret'])
            ->assertOk()->assertJsonPath('data.result', 'ignored');
    }

    public function test_webhook_captura_bandeira_e_parcelas(): void
    {
        Notification::fake();
        // Cartão parcelado: o pagamento traz o id do parcelamento; a contagem
        // vem da consulta /v3/installments/{id}.
        $this->fakeAsaas(
            paid: [
                'id' => 'pay_1', 'status' => 'CONFIRMED', 'value' => 250.00,
                'confirmedDate' => now()->toDateString(), 'checkoutSession' => 'chk_1',
                'creditCard' => ['creditCardBrand' => 'VISA', 'creditCardNumber' => '4444'],
                'installment' => 'inst_9',
            ],
            installment: ['id' => 'inst_9', 'installmentCount' => 3, 'installmentValue' => 83.34],
        );
        [, $order] = $this->orderWithCheckout();

        $this->postJson('/api/webhooks/asaas', $this->webhookPayload(), ['asaas-access-token' => 'wh-secret'])
            ->assertOk()->assertJsonPath('data.result', 'ok');

        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id, 'card_brand' => 'VISA', 'card_last4' => '4444', 'installments' => 3,
        ]);
    }

    public function test_webhook_a_vista_registra_uma_parcela(): void
    {
        // Sem `installment` → à vista (1 parcela).
        $this->fakeAsaas(paid: [
            'id' => 'pay_1', 'status' => 'CONFIRMED', 'value' => 250.00,
            'confirmedDate' => now()->toDateString(), 'checkoutSession' => 'chk_1',
            'creditCard' => ['creditCardBrand' => 'MASTERCARD', 'creditCardNumber' => '1234'],
        ]);
        [, $order] = $this->orderWithCheckout();

        $this->postJson('/api/webhooks/asaas', $this->webhookPayload(), ['asaas-access-token' => 'wh-secret'])
            ->assertOk();

        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id, 'card_brand' => 'MASTERCARD', 'card_last4' => '1234', 'installments' => 1,
        ]);
    }
}
