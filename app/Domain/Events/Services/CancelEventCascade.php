<?php

namespace App\Domain\Events\Services;

use App\Domain\Events\Models\Event;
use App\Domain\Events\Models\Order;
use App\Domain\Events\Models\OrderStatus;
use App\Domain\Events\Models\Ticket;
use App\Domain\Events\Models\TicketStatus;
use App\Models\User;
use App\Notifications\EventCancelledPtBr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Cascata do cancelamento de evento: resiliente (falha em um pedido não
 * interrompe os demais — FR-014); devolução 100% para pagos (fila da
 * tesouraria — sem estorno automático em massa).
 */
class CancelEventCascade
{
    public function __construct(
        private readonly RefundPolicy $policy,
        private readonly CreateCharge $createCharge,
        private readonly TicketLifecycleService $lifecycle,
    ) {
    }

    /** @return array{cancelled: int, refundCases: int, failures: int} */
    public function run(Event $event, User $actor): array
    {
        $orders = $event->orders()
            ->whereIn('status_id', OrderStatus::idsFor([
                OrderStatus::PENDING, OrderStatus::PAID, OrderStatus::PARTIALLY_PAID,
            ]))
            ->pluck('id');

        $summary = ['cancelled' => 0, 'refundCases' => 0, 'failures' => 0];

        foreach ($orders as $orderId) {
            try {
                $notify = DB::transaction(function () use ($orderId, $event, $actor, &$summary) {
                    $order = Order::query()->whereKey($orderId)->lockForUpdate()->first();

                    if ($order === null
                        || in_array($order->status?->slug, OrderStatus::TERMINAL, true)) {
                        return null;
                    }

                    $liveIds = TicketStatus::idsFor(TicketStatus::LIVE);
                    $order->tickets()->whereIn('status_id', $liveIds)->get()
                        ->each(function (Ticket $ticket) use ($actor) {
                            $ticket->forceFill([
                                'cancelled_at' => now(),
                                'cancelled_by' => $actor->id,
                                'cancel_reason' => 'Evento cancelado',
                            ]);
                            $ticket->transitionTo(TicketStatus::CANCELLED);
                        });

                    $this->createCharge->expirePendingPayments($order);

                    $refund = $this->policy->refundableForEventCancellation($order);
                    $order->transitionTo(OrderStatus::CANCELLED);

                    if (bccomp($refund, '0.00', 2) === 1) {
                        $this->lifecycle->openRefundCase($order, null, $refund, $actor,
                            'Evento cancelado — devolução integral.');
                        $summary['refundCases']++;
                    }

                    $summary['cancelled']++;

                    return $order->fresh();
                });

                if ($notify !== null) {
                    try {
                        $notify->buyerUser?->notify(new EventCancelledPtBr($notify));
                    } catch (\Throwable $e) {
                        Log::warning('Falha no e-mail de evento cancelado', ['order' => $notify->code]);
                    }
                }
            } catch (\Throwable $e) {
                $summary['failures']++;
                Log::error('Cascata de cancelamento: falha em pedido', [
                    'order_id' => $orderId, 'error' => $e->getMessage(),
                ]);
            }
        }

        return $summary;
    }
}
