<?php

namespace Tests\Feature\Payment;

use App\Domain\Events\Models\TicketStatus;
use App\Notifications\PaymentConfirmedPtBr;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

/**
 * US1 — webhook confirma o Pix ponta a ponta (quickstart §US1).
 */
class WebhookHappyPathTest extends PaymentTestCase
{
    use RefreshDatabase;

    public function test_pagamento_no_banco_confirma_pedido_ingressos_e_email(): void
    {
        Notification::fake();
        [$buyer, $order] = $this->pendingOrder();
        $this->createPixCharge($buyer, $order)->assertCreated();

        $this->settleAndNotify($order)
            ->assertOk()
            ->assertJsonPath('data.result', 'ok');

        $fresh = $order->fresh();
        $this->assertSame('paid', $fresh->status->slug);
        $this->assertSame(
            TicketStatus::CONFIRMED,
            $fresh->tickets->first()->status->slug
        );

        $payment = $fresh->payments()->latest('id')->first();
        $this->assertSame('paid', $payment->status->slug);
        $this->assertNotNull($payment->paid_at);
        $this->assertSame('webhook', $payment->raw_response['source']);

        Notification::assertSentTo($buyer, PaymentConfirmedPtBr::class);

        // Polling do comprador reflete
        $this->actingAs($buyer)
            ->getJson("/api/orders/{$order->code}/payment-status")
            ->assertJsonPath('data.status', 'paid');
    }
}
