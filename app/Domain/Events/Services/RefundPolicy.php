<?php

namespace App\Domain\Events\Services;

use App\Domain\Events\Models\Order;
use App\Domain\Events\Models\Ticket;
use Illuminate\Support\Carbon;

/**
 * Política de reembolso definida pelo organizador (2026-07-03):
 * 100% até N dias antes do evento; sem reembolso depois. Pisos permanentes:
 * 100% nos N dias após a compra (CDC art. 49) e 100% no cancelamento do EVENTO.
 */
class RefundPolicy
{
    /** Valor devolvível AGORA para um ingresso pago. */
    public function refundableAmount(Ticket $ticket): string
    {
        return $this->withinWindows($ticket->order, $ticket->event->starts_at)
            ? $ticket->unit_price
            : '0.00';
    }

    /** Valor devolvível AGORA para o pedido inteiro. */
    public function refundableForOrder(Order $order): string
    {
        return $this->withinWindows($order, $order->event->starts_at)
            ? $order->amountPaid()
            : '0.00';
    }

    /** Cancelamento do EVENTO: devolução integral, sempre. */
    public function refundableForEventCancellation(Order $order): string
    {
        return $order->amountPaid();
    }

    private function withinWindows(Order $order, ?Carbon $eventStartsAt): bool
    {
        $now = Carbon::now();

        // Piso legal: N dias após a compra (CDC art. 49)
        $grace = (int) config('events.refund_purchase_grace_days');
        if ($now->lte($order->created_at->copy()->addDays($grace))) {
            return true;
        }

        // Política: 100% até N dias antes do evento
        $fullUntil = (int) config('events.refund_full_until_days');
        if ($eventStartsAt !== null && $now->lte($eventStartsAt->copy()->subDays($fullUntil))) {
            return true;
        }

        return false;
    }
}
