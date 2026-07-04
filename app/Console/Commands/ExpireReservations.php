<?php

namespace App\Console\Commands;

use App\Domain\Events\Models\Event;
use App\Domain\Events\Models\Order;
use App\Domain\Events\Models\OrderStatus;
use App\Domain\Events\Models\TicketStatus;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Reservas vencidas: pedido → expired; tickets vivos → cancelled ("Reserva
 * expirada"); disponibilidades liberadas via recount na MESMA transação, sob o
 * lock do evento (idempotente; corrida com pagamento: quem lockar primeiro vence).
 */
class ExpireReservations extends Command
{
    protected $signature = 'orders:expire';

    protected $description = 'Expira pedidos aguardando pagamento com reserva vencida';

    public function handle(): int
    {
        $expirable = Order::query()
            ->whereIn('status_id', OrderStatus::idsFor([OrderStatus::PENDING]))
            ->whereNotNull('reserved_until')
            ->where('reserved_until', '<', now())
            ->pluck('id');

        $expired = 0;

        foreach ($expirable as $orderId) {
            DB::transaction(function () use ($orderId, &$expired) {
                $order = Order::query()->whereKey($orderId)->lockForUpdate()->first();

                // Revalida sob o lock (pode ter sido pago/expirado nesse meio-tempo)
                if ($order === null
                    || $order->status?->slug !== OrderStatus::PENDING
                    || $order->reserved_until === null
                    || $order->reserved_until->isFuture()) {
                    return;
                }

                Event::query()->whereKey($order->event_id)->lockForUpdate()->first();

                $liveIds = TicketStatus::idsFor(TicketStatus::LIVE);
                $tickets = $order->tickets()->whereIn('status_id', $liveIds)->get();

                foreach ($tickets as $ticket) {
                    $ticket->forceFill([
                        'cancelled_at' => now(),
                        'cancel_reason' => 'Reserva expirada',
                    ]);
                    $ticket->transitionTo(TicketStatus::CANCELLED);
                }

                $order->transitionTo(OrderStatus::EXPIRED);

                // Cancela cobranças ativas no provedor (spec 005 — melhor esforço)
                app(\App\Domain\Events\Services\CreateCharge::class)->expirePendingPayments($order);

                // Libera caches de lote/estoque imediatamente
                $tickets->pluck('ticketLot')->filter()->unique('id')->each->recountSold();
                $tickets->pluck('shirtSize')->filter()->unique('id')->each->recountSold();
                $tickets->pluck('companionShirtSize')->filter()->unique('id')->each->recountSold();

                $expired++;
            });
        }

        $this->info("Pedidos expirados: $expired");

        return self::SUCCESS;
    }
}
