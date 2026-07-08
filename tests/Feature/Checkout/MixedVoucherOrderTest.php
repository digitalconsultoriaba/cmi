<?php

namespace Tests\Feature\Checkout;

use App\Domain\Events\Models\CourtesyVoucher;

/** US2 — voucher por participante em pedido misto (quickstart §Fluxo 2). */
class MixedVoucherOrderTest extends CheckoutTestCase
{
    public function test_voucher_por_item_gera_pedido_misto(): void
    {
        $this->seminarEvent();
        $voucher = $this->voucher(CourtesyVoucher::DISTRIBUTED);

        $resp = $this->postJson('/api/public/orders', $this->guestPayload([
            $this->item(['participant_name' => 'Pagante']),
            $this->item(['participant_name' => 'Isento', 'voucher_code' => $voucher->code]),
        ]))->assertCreated();

        // Total = só a inscrição paga.
        $resp->assertJsonPath('data.order.totalAmount', '250.00');

        $tickets = collect($resp->json('data.order.tickets'));
        $isento = $tickets->firstWhere('participantName', 'Isento');
        $this->assertTrue($isento['isCourtesy']);
        $this->assertSame('0.00', $isento['unitPrice']);
        $this->assertSame('courtesy', $isento['status']);

        // Voucher resgatado e ligado ao ingresso.
        $this->assertSame(CourtesyVoucher::REDEEMED, $voucher->fresh()->status);
        $this->assertNotNull($voucher->fresh()->redeemed_ticket_id);
    }

    public function test_voucher_apenas_gerado_nao_distribuido_recusa(): void
    {
        $this->seminarEvent();
        $voucher = $this->voucher(); // available: gerado mas não distribuído

        $this->postJson('/api/public/orders', $this->guestPayload([
            $this->item(['voucher_code' => $voucher->code]),
        ]))->assertStatus(409);

        // Continua disponível (transação revertida).
        $this->assertSame(CourtesyVoucher::AVAILABLE, $voucher->fresh()->status);
    }

    public function test_mesmo_voucher_em_duas_inscricoes_recusa(): void
    {
        $this->seminarEvent();
        $voucher = $this->voucher(CourtesyVoucher::DISTRIBUTED);

        $this->postJson('/api/public/orders', $this->guestPayload([
            $this->item(['voucher_code' => $voucher->code]),
            $this->item(['voucher_code' => $voucher->code]),
        ]))->assertStatus(409);

        // Nada resgatado (transação revertida).
        $this->assertSame(CourtesyVoucher::DISTRIBUTED, $voucher->fresh()->status);
    }

    public function test_voucher_invalido_recusa(): void
    {
        $this->seminarEvent();

        $this->postJson('/api/public/orders', $this->guestPayload([
            $this->item(['voucher_code' => 'CTY-INEXISTENTE']),
        ]))->assertStatus(409);
    }
}
