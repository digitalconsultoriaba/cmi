<?php

namespace Tests\Feature\Checkout;

use App\Domain\Events\Models\CourtesyVoucher;

/** US2 — validação de voucher sem resgate (POST /public/vouchers/validate). */
class VoucherValidationTest extends CheckoutTestCase
{
    public function test_available_e_distributed_sao_validos(): void
    {
        $this->seminarEvent();

        foreach ([CourtesyVoucher::AVAILABLE, CourtesyVoucher::DISTRIBUTED] as $status) {
            $v = $this->voucher($status);
            $this->postJson('/api/public/vouchers/validate', [
                'event_slug' => $this->event->slug, 'code' => $v->code,
            ])->assertOk()->assertJsonPath('data.valid', true);
        }
    }

    public function test_redeemed_ou_inexistente_invalido(): void
    {
        $this->seminarEvent();
        $v = $this->voucher(CourtesyVoucher::REDEEMED);

        $this->postJson('/api/public/vouchers/validate', [
            'event_slug' => $this->event->slug, 'code' => $v->code,
        ])->assertOk()->assertJsonPath('data.valid', false);

        $this->postJson('/api/public/vouchers/validate', [
            'event_slug' => $this->event->slug, 'code' => 'CTY-NADA',
        ])->assertOk()->assertJsonPath('data.valid', false);
    }

    public function test_voucher_de_tipo_diferente_nao_e_elegivel(): void
    {
        $this->seminarEvent();
        $other = \App\Domain\Events\Models\TicketType::factory()->create([
            'event_id' => $this->event->id, 'name' => 'VIP', 'price' => '400.00',
        ]);
        $v = $this->voucher(CourtesyVoucher::AVAILABLE, $other->id); // vinculado ao VIP

        $this->postJson('/api/public/vouchers/validate', [
            'event_slug' => $this->event->slug, 'code' => $v->code, 'ticket_type_id' => $this->type->id,
        ])->assertOk()->assertJsonPath('data.valid', false);
    }
}
