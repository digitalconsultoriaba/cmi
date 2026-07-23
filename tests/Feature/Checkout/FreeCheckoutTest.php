<?php

namespace Tests\Feature\Checkout;

use App\Domain\Events\Models\CourtesyVoucher;
use App\Notifications\AccessCreatedPtBr;
use App\Notifications\TicketIssuedPtBr;
use Illuminate\Support\Facades\Notification;

/** US3 — checkout 100% gratuito (quickstart §Fluxo 3). */
class FreeCheckoutTest extends CheckoutTestCase
{
    public function test_total_zero_confirma_sem_pagamento(): void
    {
        Notification::fake();
        $this->seminarEvent();
        $v1 = $this->voucher(CourtesyVoucher::DISTRIBUTED);
        $v2 = $this->voucher(CourtesyVoucher::DISTRIBUTED);

        $resp = $this->postJson('/api/public/orders', $this->guestPayload([
            $this->item(['voucher_code' => $v1->code]),
            $this->item(['voucher_code' => $v2->code]),
        ]))->assertCreated();

        $resp->assertJsonPath('data.payment.required', false)
            ->assertJsonPath('data.order.status', 'paid')
            ->assertJsonPath('data.order.totalAmount', '0.00');

        foreach ($resp->json('data.order.tickets') as $t) {
            $this->assertTrue($t['isCourtesy']);
            $this->assertSame('courtesy', $t['status']);
        }

        // Entrega imediata: participantes recebem ingresso; comprador + participantes
        // recebem o acesso (conta + senha).
        Notification::assertSentTimes(TicketIssuedPtBr::class, 2);
        Notification::assertSentTimes(AccessCreatedPtBr::class, 3);
    }
}
