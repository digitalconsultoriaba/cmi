<?php

namespace App\Domain\Events\Observers;

use App\Domain\Events\Models\Order;
use App\Domain\Events\Services\FinancialSyncService;

/** Espelha o pedido numa conta a receber (spec 010, FR-020). */
class OrderObserver
{
    public function __construct(private readonly FinancialSyncService $sync) {}

    public function saved(Order $order): void
    {
        $this->sync->syncOrder($order->fresh(['status']) ?? $order);
    }
}
