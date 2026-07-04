<?php

namespace App\Domain\Events\Services;

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

            try {
                $status = $this->gateways->forProvider($payment->provider)->getChargeStatus($payment);
            } catch (\Throwable $e) {
                $summary['errors']++;
                Log::error('Reconciliação: falha ao consultar o provedor', [
                    'payment' => $payment->id, 'error' => $e->getMessage(),
                ]);

                continue;
            }

            if ($status->isPaid()) {
                $this->registerPayment->register($payment, new PaymentEvidence(
                    source: PaymentEvidence::RECONCILIATION,
                    raw: ['provider_status' => $status->raw],
                    paidAmount: $status->paidAmount,
                    paidAt: $status->paidAt,
                ));
                $summary['settled']++;
            } elseif (in_array($status->state, [ChargeStatus::EXPIRED, ChargeStatus::CANCELLED], true)) {
                $payment->transitionTo(PaymentStatus::EXPIRED);
                $summary['expired']++;
            }
        }

        return $summary;
    }
}
