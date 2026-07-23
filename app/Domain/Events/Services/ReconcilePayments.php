<?php

namespace App\Domain\Events\Services;

use App\Domain\Events\Models\Order;
use App\Domain\Events\Models\OrderStatus;
use App\Domain\Events\Models\Payment;
use App\Domain\Events\Models\PaymentStatus;
use App\Domain\Events\Payments\ChargeStatus;
use App\Domain\Events\Payments\PaymentGateways;
use Illuminate\Support\Facades\Log;

/**
 * Garantia de baixa (constituição, III): varre cobranças pendentes e concilia
 * direto com o provedor — obrigatória porque o webhook pode falhar/sumir.
 */
class ReconcilePayments
{
    public function __construct(
        private readonly PaymentGateways $gateways,
        private readonly RegisterPayment $registerPayment,
    ) {
    }

    /** @return array{checked: int, settled: int, expired: int, errors: int} */
    public function run(): array
    {
        $pending = Payment::query()
            ->whereIn('status_id', PaymentStatus::idsFor([PaymentStatus::PENDING]))
            ->whereNotNull('provider_charge_id')
            ->whereHas('order', fn ($q) => $q->whereIn(
                'status_id',
                OrderStatus::idsFor([OrderStatus::PENDING, OrderStatus::PARTIALLY_PAID])
            ))
            ->get();

        $summary = ['checked' => 0, 'settled' => 0, 'expired' => 0, 'errors' => 0];

        foreach ($pending as $payment) {
            $summary['checked']++;
            $this->reconcileOne($payment, $summary);
        }

        return $summary;
    }

    /**
     * Reconcilia na hora as cobranças pendentes de UM pedido — usado no polling
     * do status (PIX pelo microsserviço não manda webhook ao cmi, então a baixa
     * chega quando o comprador consulta o status na tela).
     */
    public function reconcileOrder(Order $order): void
    {
        $pending = $order->payments()
            ->whereIn('status_id', PaymentStatus::idsFor([PaymentStatus::PENDING]))
            ->whereNotNull('provider_charge_id')
            ->get();

        $summary = ['checked' => 0, 'settled' => 0, 'expired' => 0, 'errors' => 0];
        foreach ($pending as $payment) {
            $this->reconcileOne($payment, $summary);
        }
    }

    /**
     * Reconciliação de PIX (spec 015) — roda a cada 10 min pelo scheduler.
     * Varre cobranças PIX ainda `pending` (a verificação em tela pode ter falhado
     * — aba fechada, etc.) e reconsulta o microsserviço, que reflete o crédito no
     * SICOOB pelo `txid` (provider_charge_id). Por cobrança:
     *  - creditada (`concluida`) → RegisterPayment: baixa + e-mail ao comprador;
     *  - gateway expirado/removido → expira;
     *  - ainda pendente após $giveUpMinutes (1h) → "desistência" (expira e para
     *    de verificar — não é mais seleccionada, pois deixa de ser `pending`).
     *
     * @return array{checked:int, settled:int, expired:int, abandoned:int, errors:int}
     */
    public function reconcilePendingPix(int $giveUpMinutes = 60): array
    {
        $pending = Payment::query()
            ->where('method', 'pix')
            ->whereIn('status_id', PaymentStatus::idsFor([PaymentStatus::PENDING]))
            ->whereNotNull('provider_charge_id')
            ->get();

        $summary = ['checked' => 0, 'settled' => 0, 'expired' => 0, 'abandoned' => 0, 'errors' => 0];

        foreach ($pending as $payment) {
            $summary['checked']++;

            try {
                $status = $this->gateways->forProvider($payment->provider)->getChargeStatus($payment);
            } catch (\Throwable $e) {
                $summary['errors']++;
                Log::error('Reconciliação PIX: falha ao consultar o microsserviço', [
                    'payment' => $payment->id, 'error' => $e->getMessage(),
                ]);

                continue;
            }

            // Consta creditado no SICOOB → baixa (ponto único) + e-mail ao comprador.
            if ($status->isPaid()) {
                $this->registerPayment->register($payment, new PaymentEvidence(
                    source: PaymentEvidence::RECONCILIATION,
                    raw: ['provider_status' => $status->raw],
                    paidAmount: $status->paidAmount,
                    paidAt: $status->paidAt,
                ));
                $summary['settled']++;

                continue;
            }

            // Cobrança expirada/removida no provedor → encerra.
            if (in_array($status->state, [ChargeStatus::EXPIRED, ChargeStatus::CANCELLED], true)) {
                $payment->transitionTo(PaymentStatus::EXPIRED);
                $summary['expired']++;

                continue;
            }

            // Ainda pendente e já passou 1h desde a criação → desistência: encerra
            // e para de verificar (deixa de ser `pending`, não retorna nas rodadas).
            if ($payment->created_at !== null && $payment->created_at->lt(now()->subMinutes($giveUpMinutes))) {
                $payment->transitionTo(PaymentStatus::EXPIRED);
                $summary['abandoned']++;
            }
        }

        return $summary;
    }

    private function reconcileOne(Payment $payment, array &$summary): void
    {
        try {
            $status = $this->gateways->forProvider($payment->provider)->getChargeStatus($payment);
        } catch (\Throwable $e) {
            $summary['errors']++;
            Log::error('Reconciliação: falha ao consultar o provedor', [
                'payment' => $payment->id, 'error' => $e->getMessage(),
            ]);

            return;
        }

        if ($status->isPaid()) {
            $this->registerPayment->register($payment, new PaymentEvidence(
                source: PaymentEvidence::RECONCILIATION,
                raw: ['provider_status' => $status->raw],
                paidAmount: $status->paidAmount,
                paidAt: $status->paidAt,
                cardBrand: $status->cardBrand,
                cardLast4: $status->cardLast4,
                installments: $status->installments,
            ));
            $summary['settled']++;
        } elseif (in_array($status->state, [ChargeStatus::EXPIRED, ChargeStatus::CANCELLED], true)) {
            $payment->transitionTo(PaymentStatus::EXPIRED);
            $summary['expired']++;
        }
    }
}
