<?php

namespace Tests\Feature\Payment;

use App\Domain\Events\Models\Order;
use App\Domain\Events\Payments\FakePixGateway;
use App\Models\User;
use Tests\Feature\Purchase\PurchaseTestCase;

abstract class PaymentTestCase extends PurchaseTestCase
{
    protected function fakePix(): FakePixGateway
    {
        return app(FakePixGateway::class);
    }

    /** Compra 1 individual (200,00) e retorna [buyer, Order]. */
    protected function pendingOrder(): array
    {
        $this->sellableEvent();
        $buyer = $this->buyer();

        $code = $this->buy($buyer, [$this->item($this->individual)])
            ->json('data.orders.0.code');

        return [$buyer, Order::query()->where('code', $code)->firstOrFail()];
    }

    protected function createPixCharge(User $buyer, Order $order)
    {
        return $this->actingAs($buyer)
            ->postJson("/api/orders/{$order->code}/checkout/pix");
    }

    /** Simula "pagou no banco" + webhook assinado (fluxo real do provedor). */
    protected function settleAndNotify(Order $order, ?string $amount = null)
    {
        $payment = $order->payments()->latest('id')->first();

        $this->fakePix()->settle($payment->provider_charge_id, $amount);

        return $this->postJson('/api/webhooks/sicoob', [
            'txid' => $payment->provider_charge_id,
            'event' => 'pix.received',
        ], ['X-Webhook-Secret' => config('payments.sicoob.webhook_secret')]);
    }
}
