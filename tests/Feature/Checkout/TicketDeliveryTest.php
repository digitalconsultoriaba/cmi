<?php

namespace Tests\Feature\Checkout;

use App\Notifications\OrderAccessPtBr;
use App\Notifications\TicketIssuedPtBr;
use App\Models\User;
use Illuminate\Support\Facades\Notification;

/** US5 — entrega de ingresso por participante após pagamento (quickstart §Fluxo 1/5). */
class TicketDeliveryTest extends CheckoutTestCase
{
    public function test_pagamento_dispara_ingresso_por_participante_e_acesso_do_comprador(): void
    {
        Notification::fake();
        $this->seminarEvent();

        $code = $this->postJson('/api/public/orders', $this->guestPayload([
            $this->item(['participant_name' => 'Irmão 1', 'participant_email' => 'i1@ex.com']),
            $this->item(['participant_name' => 'Irmão 2', 'participant_email' => 'i2@ex.com']),
        ]))->assertCreated()->json('data.order.code');

        // Paga no cartão (gateway de teste).
        $this->postJson("/api/public/orders/{$code}/checkout/card", [
            'token' => 'tok_ok_4242', 'installments' => 1,
        ])->assertOk();

        Notification::assertSentTimes(TicketIssuedPtBr::class, 2);

        $buyer = User::query()->where('email', 'comprador@ex.com')->firstOrFail();
        Notification::assertSentTo($buyer, OrderAccessPtBr::class);
    }

    public function test_reenvio_de_acesso(): void
    {
        Notification::fake();
        $this->seminarEvent();

        $code = $this->postJson('/api/public/orders', $this->guestPayload([
            $this->item(['participant_email' => 'i1@ex.com']),
        ]))->json('data.order.code');

        $this->postJson("/api/public/orders/{$code}/resend-access")
            ->assertOk()->assertJsonPath('data.sent', true);
    }
}
