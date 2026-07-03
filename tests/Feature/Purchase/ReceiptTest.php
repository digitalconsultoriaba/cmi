<?php

namespace Tests\Feature\Purchase;

use App\Domain\Events\Models\CourtesyVoucher;
use App\Domain\Events\Models\TicketType;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * US4 — comprovante PDF com QR (quickstart §US4).
 */
class ReceiptTest extends PurchaseTestCase
{
    use RefreshDatabase;

    private function confirmedTicketCode($buyer): string
    {
        TicketType::factory()->create([
            'event_id' => $this->event->id, 'is_courtesy' => true, 'price' => '0.00',
        ]);
        $voucher = CourtesyVoucher::query()->create([
            'event_id' => $this->event->id,
            'status' => CourtesyVoucher::DISTRIBUTED,
        ]);

        $response = $this->buy($buyer, [], ['voucher_code' => $voucher->code])->assertCreated();

        return $response->json('data.orders.0.tickets.0.code');
    }

    public function test_ingresso_confirmado_baixa_pdf_com_o_codigo(): void
    {
        $this->sellableEvent();
        $buyer = $this->buyer();
        $code = $this->confirmedTicketCode($buyer);

        $response = $this->actingAs($buyer)->get("/api/tickets/{$code}/receipt");

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('content-type'));
        $this->assertStringContainsString($code, $response->headers->get('content-disposition'));
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_ingresso_aguardando_pagamento_recusa_com_orientacao(): void
    {
        $this->sellableEvent();
        $buyer = $this->buyer();

        $code = $this->buy($buyer, [$this->item($this->individual)])
            ->json('data.orders.0.tickets.0.code');

        $this->actingAs($buyer)->getJson("/api/tickets/{$code}/receipt")
            ->assertStatus(409)->assertJsonPath('type', 'not_confirmed');
    }

    public function test_comprovante_de_outro_dono_da_403(): void
    {
        $this->sellableEvent();
        $buyer = $this->buyer();
        $code = $this->confirmedTicketCode($buyer);

        $this->actingAs($this->buyer())->getJson("/api/tickets/{$code}/receipt")
            ->assertStatus(403);
    }
}
