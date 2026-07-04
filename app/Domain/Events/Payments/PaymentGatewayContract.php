<?php

namespace App\Domain\Events\Payments;

use App\Domain\Events\Models\Order;
use App\Domain\Events\Models\Payment;

/**
 * Fronteira dos provedores (constituição, IV): trocar de banco/gateway é trocar
 * de driver — o fluxo de pagamento nunca muda. Drivers NUNCA tocam em
 * orders/tickets: quem baixa é o RegisterPayment.
 */
interface PaymentGatewayContract
{
    /** Nome gravado em payments.provider (dedupe/webhooks usam o mesmo). */
    public function providerName(): string;

    public function createPixCharge(Order $order): ChargeData;

    public function createBoletoCharge(Order $order): ChargeData;

    /** Token opaco do provedor — NUNCA PAN/CVV/validade. Síncrono. */
    public function chargeCard(Order $order, string $token, int $installments): CardResult;

    /** Fonte de verdade da baixa — sempre reconsultada antes de confirmar. */
    public function getChargeStatus(Payment $payment): ChargeStatus;

    /** Melhor esforço; falha não propaga. */
    public function cancelCharge(Payment $payment): void;

    /**
     * Estorno via provedor (emenda da spec 006). Provedores sem devolução por
     * API lançam RefundNotSupported → fluxo operacional da tesouraria.
     */
    public function refundCharge(Payment $payment, string $amount): RefundResult;
}
