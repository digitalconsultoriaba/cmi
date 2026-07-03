<?php

namespace App\Policies;

use App\Domain\Events\Models\Order;
use App\Models\User;

class OrderPolicy
{
    /** Só o comprador vê o próprio pedido (FR-011/FR-012 da spec 004). */
    public function view(User $user, Order $order): bool
    {
        return $order->buyer_user_id === $user->id;
    }
}
