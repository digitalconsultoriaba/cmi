<?php

namespace App\Domain\Events\Services;

use App\Domain\Events\Exceptions\DomainRuleViolation;
use App\Domain\Events\Models\Payment;
use App\Domain\Events\Models\PaymentStatus;
use App\Domain\Events\Models\SupportCase;
use App\Domain\Events\Models\Ticket;
use App\Domain\Events\Payments\PaymentGateways;
use App\Domain\Events\Payments\RefundNotSupported;
use App\Models\User;
use App\Notifications\RefundCompletedPtBr;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Estorno — espelho do ponto único de baixa: fluxo único da tesouraria,
 * operador nunca no próprio pedido, evidência completa (princípios III e V).
 */
class RefundPayment
{
    public function __construct(private readonly PaymentGateways $gateways)
    {
    }

    public function execute(SupportCase $case, User $operator, string $justification, ?string $amount = null): SupportCase
    {
        $case = DB::transaction(function () use ($case, $operator, $justification, $amount) {
            $case = SupportCase::query()->whereKey($case->id)->lockForUpdate()->first();

            if ($case->type !== 'refund' || ! in_array($case->status, ['open', 'reopened'], true)) {
                throw new DomainRuleViolation('Este caso de reembolso não está aberto.', 'terminal_status');
            }

            $order = $case->order;

            // Espelho da regra da baixa: NUNCA no próprio pedido (403)
            if ($order->buyer_user_id === $operator->id) {
                throw new AuthorizationException;
            }

            $payment = $order->payments()
                ->whereIn('status_id', PaymentStatus::idsFor([PaymentStatus::PAID]))
                ->latest('paid_at')
                ->lockForUpdate()
                ->first();

            if ($payment === null) {
                throw new DomainRuleViolation(
                    'Não há pagamento confirmado para devolver neste pedido.',
                    'no_paid_payment'
                );
            }

            $refundAmount = $amount ?? $case->refund_amount ?? $payment->amount;
            $evidence = ['source' => 'refund', 'justification' => $justification, 'operator_id' => $operator->id];

            // Cartão: estorno via provedor (emenda do contrato); demais: operacional
            if ($payment->method === 'card') {
                $result = $this->gateways->forProvider($payment->provider)
                    ->refundCharge($payment, $refundAmount);

                if (! $result->refunded) {
                    throw new DomainRuleViolation(
                        'O provedor recusou o estorno — tente novamente ou trate operacionalmente.',
                        'refund_failed'
                    );
                }

                $evidence['provider_refund'] = $result->raw;
                $evidence['refund_external_id'] = $result->externalId;
            } else {
                try {
                    $this->gateways->forProvider($payment->provider)->refundCharge($payment, $refundAmount);
                } catch (RefundNotSupported) {
                    $evidence['operational'] = true; // devolução feita por fora, registrada
                }
            }

            // Registro da devolução nos ingressos do caso
            $tickets = $case->ticket_id !== null
                ? collect([$case->ticket])
                : $order->tickets;

            $tickets->each(function (Ticket $ticket) use ($refundAmount, $case) {
                $ticket->forceFill([
                    'refunded_at' => now(),
                    'refund_amount' => $case->ticket_id !== null ? $refundAmount : $ticket->unit_price,
                ])->save();
            });

            // Payment → refunded APENAS na devolução total (parcial mantém paid)
            $payment->forceFill([
                'raw_response' => array_merge($payment->raw_response ?? [], ['refund' => $evidence]),
                'note' => $justification,
            ]);

            if (bccomp($refundAmount, $payment->amount, 2) === 0) {
                $payment->transitionTo(PaymentStatus::REFUNDED);
            } else {
                $payment->save();
            }

            $case->notes()->create([
                'author_user_id' => $operator->id,
                'body' => 'Estorno efetuado: R$ '.number_format((float) $refundAmount, 2, ',', '.')
                    .' ('.($payment->method === 'card' ? 'via provedor' : 'operacional').'). '.$justification,
                'visible_to_attendee' => true,
                'from_attendee' => false,
            ]);

            $case->forceFill(['status' => 'finished'])->save();

            activity('payment.refunded')
                ->performedOn($payment)
                ->causedBy($operator)
                ->withProperties([
                    'reference' => $order->code,
                    'amount' => $refundAmount,
                    'partial' => bccomp($refundAmount, $payment->amount, 2) !== 0,
                ])
                ->log('Estorno de R$ '.number_format((float) $refundAmount, 2, ',', '.')
                    .' no pedido '.$order->code);

            return $case->fresh();
        });

        try {
            $case->order?->buyerUser?->notify(new RefundCompletedPtBr($case));
        } catch (\Throwable $e) {
            Log::warning('Falha ao enviar e-mail de estorno', ['case' => $case->id, 'error' => $e->getMessage()]);
        }

        return $case;
    }
}
