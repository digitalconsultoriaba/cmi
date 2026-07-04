<?php

namespace App\Http\Controllers\Api\Treasury;

use App\Domain\Events\Exceptions\DomainRuleViolation;
use App\Domain\Events\Models\Order;
use App\Domain\Events\Models\OrderStatus;
use App\Domain\Events\Models\Payment;
use App\Domain\Events\Models\PaymentStatus;
use App\Domain\Events\Services\PaymentEvidence;
use App\Domain\Events\Services\ReconcilePayments;
use App\Domain\Events\Services\RegisterPayment;
use App\Http\Controllers\Controller;
use App\Http\Requests\PayManualRequest;
use App\Support\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;

class TreasuryController extends Controller
{
    public function receivables(Request $request)
    {
        $query = Payment::query()
            ->with(['status', 'order.status', 'order.event'])
            ->latest('id');

        if ($status = $request->query('status')) {
            $query->whereIn('status_id', PaymentStatus::idsFor([$status]));
        }

        if ($method = $request->query('method')) {
            $query->where('method', $method);
        }

        $payments = $query->get()->map(function (Payment $payment) {
            $orderStatus = $payment->order?->status?->slug;

            return [
                'id' => $payment->id,
                'orderCode' => $payment->order?->code,
                'buyerName' => $payment->order?->buyer_name,
                'method' => $payment->method,
                'provider' => $payment->provider,
                'status' => $payment->status?->slug,
                'amount' => $payment->amount,
                'paidAt' => $payment->paid_at?->toISOString(),
                'source' => $payment->raw_response['source'] ?? null,
                'registeredBy' => $payment->registeredBy?->name,
                'orderStatus' => $orderStatus,
                // Pendência DERIVADA: pago mas o pedido não está pago
                // (expirado/cancelado/parcial) — princípio II, sem flag.
                'flagged' => $payment->status?->slug === PaymentStatus::PAID
                    && $orderStatus !== OrderStatus::PAID,
            ];
        });

        return ApiResponse::data($payments);
    }

    public function reconcile(ReconcilePayments $reconcile)
    {
        return ApiResponse::data($reconcile->run());
    }

    public function payManual(PayManualRequest $request, Order $order, RegisterPayment $registerPayment)
    {
        // Quem compra NUNCA dá a própria baixa (constituição, III) — nem com papel.
        if ($order->buyer_user_id === $request->user()->id) {
            throw new AuthorizationException;
        }

        if ($order->status?->slug === OrderStatus::PAID) {
            throw new DomainRuleViolation('Este pedido já está pago.', 'already_paid');
        }

        if (in_array($order->status?->slug, OrderStatus::TERMINAL, true)) {
            throw new DomainRuleViolation(
                'Pedido expirado/cancelado não recebe baixa manual — trate como pendência.',
                'terminal_status'
            );
        }

        $payment = $order->payments()->create([
            'amount' => $request->validated('amount') ?? $order->total_amount,
            'method' => 'manual',
            'provider' => 'manual',
            'status_id' => PaymentStatus::idFor(PaymentStatus::PENDING),
        ]);

        $payment = $registerPayment->register($payment, new PaymentEvidence(
            source: PaymentEvidence::MANUAL,
            raw: ['justification' => $request->validated('justification')],
            paidAmount: $request->validated('amount') ?? $order->total_amount,
            paidAt: $request->validated('paid_at') !== null
                ? \Illuminate\Support\Carbon::parse($request->validated('paid_at'))
                : now(),
            actorId: $request->user()->id,
            note: $request->validated('justification'),
        ));

        return ApiResponse::data([
            'orderCode' => $order->fresh()->code,
            'orderStatus' => $order->fresh()->status?->slug,
            'paymentStatus' => $payment->status?->slug,
        ]);
    }
}
