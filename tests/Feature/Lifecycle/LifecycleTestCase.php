<?php

namespace Tests\Feature\Lifecycle;

use App\Domain\Events\Models\Order;
use Tests\Feature\Payment\PaymentTestCase;

abstract class LifecycleTestCase extends PaymentTestCase
{
    /** Compra + paga com cartão fake → pedido pago com tickets confirmados. */
    protected function paidOrder(int $quantity = 1): array
    {
        $this->sellableEvent([
            'starts_at' => now()->addDays(60),
            'allow_user_cancel' => true,
            'allow_transfer' => true,
        ]);
        $buyer = $this->buyer();

        $items = array_map(fn () => $this->item($this->individual), range(1, $quantity));
        $code = $this->buy($buyer, $items)->json('data.orders.0.code');
        $order = Order::query()->where('code', $code)->firstOrFail();

        $this->actingAs($buyer)->postJson("/api/orders/{$order->code}/checkout/card", [
            'token' => 'tok_ok_4242',
            'installments' => 1,
        ])->assertOk();

        return [$buyer, $order->fresh()];
    }
}
