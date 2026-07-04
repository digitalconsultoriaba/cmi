<?php

namespace Tests\Feature\Payment;

use App\Domain\Events\Models\PaymentStatus;
use App\Domain\Events\Models\TicketStatus;
use App\Domain\Events\Services\PaymentEvidence;
use App\Domain\Events\Services\RegisterPayment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

/**
 * US2 — o ponto único de baixa (quickstart §US2; princípio III).
 */
class RegisterPaymentTest extends PaymentTestCase
{
    use RefreshDatabase;

    private function chargedOrder(): array
    {
        [$buyer, $order] = $this->pendingOrder();
        $this->createPixCharge($buyer, $order);

        return [$buyer, $order->fresh(), $order->payments()->latest('id')->first()];
    }

    public function test_registrar_duas_vezes_produz_uma_transicao_so(): void
    {
        [, $order, $payment] = $this->chargedOrder();
        $register = app(RegisterPayment::class);

        $register->register($payment, new PaymentEvidence(source: 'webhook', raw: ['n' => 1]));
        $firstPaidAt = $payment->fresh()->paid_at;

        Carbon::setTestNow(now()->addMinutes(5));
        $register->register($payment, new PaymentEvidence(source: 'reconciliation', raw: ['n' => 2]));
        Carbon::setTestNow();

        $fresh = $payment->fresh();
        $this->assertEquals($firstPaidAt, $fresh->paid_at, 'segunda chamada é no-op');
        $this->assertSame(1, $fresh->raw_response['n'], 'evidência original preservada');
        $this->assertSame('paid', $order->fresh()->status->slug);
    }

    public function test_valor_divergente_marca_parcial_sem_confirmar_ingressos(): void
    {
        [, $order, $payment] = $this->chargedOrder();

        app(RegisterPayment::class)->register($payment, new PaymentEvidence(
            source: 'webhook',
            paidAmount: '150.00', // pedido vale 200,00
        ));

        $fresh = $order->fresh();
        $this->assertSame('partially_paid', $fresh->status->slug);
        $this->assertSame('paid', $payment->fresh()->status->slug);
        $this->assertSame(
            TicketStatus::RESERVED,
            $fresh->tickets->first()->status->slug,
            'nunca confirma por valor errado'
        );
    }

    public function test_pedido_expirado_registra_pagamento_sem_reativar(): void
    {
        [, $order, $payment] = $this->chargedOrder();

        // Expira o pedido
        Carbon::setTestNow(now()->addHours(2));
        $this->artisan('orders:expire')->assertSuccessful();
        Carbon::setTestNow();
        $this->assertSame('expired', $order->fresh()->status->slug);

        // Pagamento tardio chega mesmo assim
        $payment = $payment->fresh();
        // A expiração marcou o payment como expired; um pagamento tardio chega
        // como novo registro do provedor — reconsulta acha a cobrança paga.
        // Simulamos direto no ponto único:
        $late = $order->payments()->create([
            'amount' => $order->total_amount,
            'method' => 'pix',
            'provider' => 'sicoob',
            'provider_charge_id' => 'late-'.uniqid(),
            'status_id' => PaymentStatus::idFor(PaymentStatus::PENDING),
        ]);

        app(RegisterPayment::class)->register($late, new PaymentEvidence(source: 'webhook'));

        $this->assertSame('paid', $late->fresh()->status->slug, 'pagamento registrado');
        $this->assertSame('expired', $order->fresh()->status->slug, 'pedido NÃO reativa');
    }

    public function test_baixa_manual_grava_autor_e_justificativa(): void
    {
        [, $order, $payment] = $this->chargedOrder();
        $operator = $this->buyer();

        app(RegisterPayment::class)->register($payment, new PaymentEvidence(
            source: 'manual',
            raw: ['justification' => 'Pagamento comprovado por transferência'],
            actorId: $operator->id,
            note: 'Pagamento comprovado por transferência',
        ));

        $fresh = $payment->fresh();
        $this->assertSame($operator->id, $fresh->registered_by);
        $this->assertSame('manual', $fresh->raw_response['source']);
        $this->assertSame('paid', $order->fresh()->status->slug);
    }
}
