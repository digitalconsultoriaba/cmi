<?php

namespace Tests\Feature\Payment;

use App\Domain\Events\Models\Order;
use App\Domain\Events\Models\OrderStatus;
use App\Domain\Events\Models\Payment;
use App\Domain\Events\Models\PaymentStatus;
use App\Domain\Events\Services\ReconcilePayments;
use App\Notifications\PaymentConfirmedPtBr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\Feature\Checkout\CheckoutTestCase;

/**
 * PIX via microsserviço (Boletos SICOOB V2): o endpoint público gera a cobrança
 * (QR/copia-e-cola) e a baixa chega no polling do status (reconsulta). Spec 015.
 */
class PixCheckoutTest extends CheckoutTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'payments.pix_driver' => 'boletos',
            'payments.boletos.base_url' => 'http://pix.test',
            'payments.boletos.token' => 'test-token',
            'payments.boletos.pix_expiration' => 3600,
        ]);
    }

    private function pendingOrderCode(): string
    {
        $this->seminarEvent(['allow_pix' => true]);

        return $this->postJson('/api/public/orders', $this->guestPayload([$this->item()]))
            ->assertCreated()->json('data.order.code');
    }

    public function test_gera_cobranca_pix_e_cria_payment_pendente(): void
    {
        Http::fake([
            'pix.test/api/pix/cobranca' => Http::response(['data' => [
                'txid' => 'tx_1', 'status' => 'ativa',
                'copiaECola' => '00020126580014br.gov.bcb.pix5303986',
                'location' => 'pix.sicoob.com.br/qr/1', 'expiracao' => 3600,
            ]]),
        ]);

        $code = $this->pendingOrderCode();

        $this->postJson("/api/public/orders/{$code}/checkout/pix")
            ->assertCreated()
            ->assertJsonPath('data.method', 'pix')
            ->assertJsonPath('data.pixQrCode', '00020126580014br.gov.bcb.pix5303986');

        $order = Order::query()->where('code', $code)->firstOrFail();
        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id, 'provider' => 'sicoob', 'method' => 'pix',
            'provider_charge_id' => 'tx_1',
        ]);

        // valor (float) + Bearer token vão ao microsserviço; nunca ao SICOOB direto.
        Http::assertSent(fn ($r) => str_contains($r->url(), '/api/pix/cobranca')
            && $r->method() === 'POST'
            && (float) $r['valor'] === 250.0
            && $r->hasHeader('Authorization', 'Bearer test-token'));
    }

    public function test_status_reconsulta_e_baixa_quando_concluida(): void
    {
        Notification::fake();

        Http::fake(function ($request) {
            if ($request->method() === 'POST') {
                return Http::response(['data' => [
                    'txid' => 'tx_9', 'status' => 'ativa', 'copiaECola' => '00020126...5303986',
                ]]);
            }

            // GET status → concluida (pago) reflete a confirmação do SICOOB.
            return Http::response(['data' => [
                'txid' => 'tx_9', 'status' => 'concluida', 'valor' => 250.00,
                'endToEndId' => 'E123', 'paidAt' => now()->toISOString(),
            ]]);
        });

        $code = $this->pendingOrderCode();
        $this->postJson("/api/public/orders/{$code}/checkout/pix")->assertCreated();

        // O polling do status reconsulta o microsserviço e baixa o pedido.
        $this->getJson("/api/public/orders/{$code}/payment-status")
            ->assertOk()->assertJsonPath('data.status', 'paid');

        $this->assertDatabaseHas('orders', [
            'code' => $code,
            'status_id' => OrderStatus::idFor(OrderStatus::PAID),
        ]);
    }

    /** Cria uma cobrança PIX pendente e devolve o code do pedido + o payment. */
    private function pendingPix(): array
    {
        $code = $this->pendingOrderCode();
        $this->postJson("/api/public/orders/{$code}/checkout/pix")->assertCreated();
        $payment = Payment::query()->where('method', 'pix')->latest('id')->firstOrFail();

        return [$code, $payment];
    }

    public function test_reconcile_baixa_e_notifica_quando_creditado(): void
    {
        Notification::fake();

        Http::fake(function ($request) {
            if ($request->method() === 'POST') {
                return Http::response(['data' => ['txid' => 'tx_ok', 'status' => 'ativa', 'copiaECola' => '000...986']]);
            }

            return Http::response(['data' => [
                'txid' => 'tx_ok', 'status' => 'concluida', 'valor' => 250.00,
                'endToEndId' => 'E1', 'paidAt' => now()->toISOString(),
            ]]);
        });

        [$code] = $this->pendingPix();

        $summary = app(ReconcilePayments::class)->reconcilePendingPix();

        $this->assertSame(1, $summary['settled']);
        $this->assertDatabaseHas('orders', [
            'code' => $code, 'status_id' => OrderStatus::idFor(OrderStatus::PAID),
        ]);
        Notification::assertSentTo($this->event->orders()->first()->buyerUser, PaymentConfirmedPtBr::class);
    }

    public function test_reconcile_marca_desistencia_apos_uma_hora(): void
    {
        Http::fake(function ($request) {
            if ($request->method() === 'POST') {
                return Http::response(['data' => ['txid' => 'tx_old', 'status' => 'ativa', 'copiaECola' => '000...986']]);
            }

            // Ainda não creditado no SICOOB.
            return Http::response(['data' => ['txid' => 'tx_old', 'status' => 'ativa', 'paidAt' => null]]);
        });

        [, $payment] = $this->pendingPix();
        // Cobrança criada há mais de 1h, ainda pendente → desistência.
        Payment::query()->whereKey($payment->id)->update(['created_at' => now()->subMinutes(75)]);

        $summary = app(ReconcilePayments::class)->reconcilePendingPix();

        $this->assertSame(1, $summary['abandoned']);
        $this->assertSame(PaymentStatus::EXPIRED, $payment->fresh()->status?->slug);
    }

    public function test_reconcile_mantem_pendente_dentro_de_uma_hora(): void
    {
        Http::fake(function ($request) {
            if ($request->method() === 'POST') {
                return Http::response(['data' => ['txid' => 'tx_new', 'status' => 'ativa', 'copiaECola' => '000...986']]);
            }

            return Http::response(['data' => ['txid' => 'tx_new', 'status' => 'ativa', 'paidAt' => null]]);
        });

        [, $payment] = $this->pendingPix(); // created_at = agora

        $summary = app(ReconcilePayments::class)->reconcilePendingPix();

        $this->assertSame(0, $summary['abandoned']);
        $this->assertSame(PaymentStatus::PENDING, $payment->fresh()->status?->slug);
    }

    public function test_status_pendente_nao_baixa(): void
    {
        Http::fake(function ($request) {
            if ($request->method() === 'POST') {
                return Http::response(['data' => ['txid' => 'tx_5', 'status' => 'ativa', 'copiaECola' => '000...986']]);
            }

            return Http::response(['data' => ['txid' => 'tx_5', 'status' => 'ativa', 'paidAt' => null]]);
        });

        $code = $this->pendingOrderCode();
        $this->postJson("/api/public/orders/{$code}/checkout/pix")->assertCreated();

        $this->getJson("/api/public/orders/{$code}/payment-status")
            ->assertOk()->assertJsonPath('data.status', OrderStatus::PENDING);
    }
}
