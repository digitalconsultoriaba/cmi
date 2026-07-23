<?php

namespace App\Domain\Events\Services;

use App\Domain\Events\Models\Order;
use App\Domain\Events\Models\OrderStatus;
use App\Domain\Events\Models\Payment;
use App\Domain\Events\Models\PaymentStatus;
use App\Domain\Events\Models\TicketStatus;
use App\Notifications\PaymentConfirmedPtBr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * PONTO ÚNICO DE BAIXA (constituição, princípio III — NÃO NEGOCIÁVEL).
 * Toda confirmação — webhook, reconciliação, gateway síncrono ou baixa manual —
 * passa por aqui. Idempotente: o mesmo evento externo nunca baixa duas vezes.
 */
class RegisterPayment
{
    public function register(Payment $payment, PaymentEvidence $evidence): Payment
    {
        [$payment, $confirmedOrder] = DB::transaction(function () use ($payment, $evidence) {
            $payment = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();

            // Idempotência: já pago → nenhum efeito (SC-002)
            if ($payment->status?->slug === PaymentStatus::PAID) {
                return [$payment, null];
            }

            $order = Order::query()->whereKey($payment->order_id)->lockForUpdate()->first();
            $paidAmount = $evidence->paidAmount ?? $payment->amount;

            // Metadados do cartão capturados na confirmação (só quando vierem —
            // ex.: parcelas reais do ASAAS, escolhidas pelo comprador na página).
            $cardMeta = array_filter([
                'card_brand' => $evidence->cardBrand,
                'card_last4' => $evidence->cardLast4,
                'installments' => $evidence->installments,
            ], fn ($v) => $v !== null);

            $payment->forceFill(array_merge([
                'paid_at' => $evidence->paidAt ?? now(),
                'registered_by' => $evidence->actorId,
                'raw_response' => array_merge(
                    ['source' => $evidence->source, 'paid_amount' => $paidAmount],
                    $evidence->raw
                ),
                'note' => $evidence->note ?? $payment->note,
            ], $cardMeta));
            $payment->transitionTo(PaymentStatus::PAID);

            // Trilha (spec 008): a baixa é do operador quando manual; do
            // SISTEMA quando a evidência vem de gateway/webhook/conciliação —
            // nunca do usuário autenticado por acaso na request.
            $logger = activity('payment.registered')
                ->performedOn($payment)
                ->withProperties([
                    'reference' => $order->code,
                    'amount' => $paidAmount,
                    'method' => $payment->method,
                    'source' => $evidence->source,
                ]);
            $evidence->actorId !== null
                ? $logger->causedBy(\App\Models\User::query()->find($evidence->actorId))
                : $logger->causedByAnonymous();
            $logger->log('Pagamento de R$ '.number_format((float) $paidAmount, 2, ',', '.')
                .' confirmado no pedido '.$order->code.' ('.$evidence->source.')');

            // Pedido em situação terminal: registra sem reativar (FR-012) —
            // a pendência aparece derivada na tesouraria.
            if (in_array($order->status?->slug, OrderStatus::TERMINAL, true)
                || $order->status?->slug === OrderStatus::PAID) {
                return [$payment->fresh(), null];
            }

            // Valor divergente: nunca confirma por valor errado (FR-011)
            if (bccomp($paidAmount, $order->total_amount, 2) !== 0) {
                $order->transitionTo(OrderStatus::PARTIALLY_PAID);

                return [$payment->fresh(), null];
            }

            // Caminho feliz: pedido pago + ingressos confirmados
            $order->transitionTo(OrderStatus::PAID);

            $reservedIds = TicketStatus::idsFor([TicketStatus::RESERVED, TicketStatus::AWAITING_PAYMENT]);
            $order->tickets()->whereIn('status_id', $reservedIds)->get()
                ->each(fn ($ticket) => $ticket->transitionTo(TicketStatus::CONFIRMED));

            return [$payment->fresh(), $order->fresh()];
        });

        // E-mail fora da transação; falha NUNCA bloqueia a baixa (FR-015)
        if ($confirmedOrder !== null) {
            try {
                $confirmedOrder->buyerUser?->notify(new PaymentConfirmedPtBr($confirmedOrder));
            } catch (\Throwable $e) {
                Log::warning('Falha ao enviar e-mail de confirmação', [
                    'order' => $confirmedOrder->code, 'error' => $e->getMessage(),
                ]);
            }

            // Entrega por participante + acesso do comprador guest (spec 014).
            app(OrderConfirmedNotifier::class)->notify($confirmedOrder);
        }

        return $payment;
    }
}
