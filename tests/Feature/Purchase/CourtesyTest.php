<?php

namespace Tests\Feature\Purchase;

use App\Domain\Events\Models\CourtesyVoucher;
use App\Domain\Events\Models\Order;
use App\Domain\Events\Models\TicketType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

/**
 * US3 — cortesias automáticas e voucher (quickstart §US3).
 */
class CourtesyTest extends PurchaseTestCase
{
    use RefreshDatabase;

    private TicketType $courtesyType;

    private function courtesyEvent(array $attrs = []): void
    {
        $this->sellableEvent(array_merge([
            'allow_courtesy' => true,
            'courtesy_paid_threshold' => 10,
            'courtesy_grant_per_threshold' => 1,
            'courtesy_limit_per_account' => null,
        ], $attrs));

        $this->courtesyType = TicketType::factory()->create([
            'event_id' => $this->event->id,
            'name' => 'Cortesia',
            'price' => '0.00',
            'is_courtesy' => true,
        ]);
    }

    private function tenItems(): array
    {
        return array_map(fn () => $this->item($this->individual), range(1, 10));
    }

    public function test_regra_dez_para_um_gera_cortesia_confirmada(): void
    {
        $this->courtesyEvent();

        $response = $this->buy($this->buyer(), $this->tenItems(), [
            'courtesy_participants' => [['participant_name' => 'Convidado Cortesia']],
        ])->assertCreated();

        $tickets = collect($response->json('data.orders.0.tickets'));
        $this->assertCount(11, $tickets);

        $courtesy = $tickets->firstWhere('isCourtesy', true);
        $this->assertSame('Convidado Cortesia', $courtesy['participantName']);
        $this->assertSame('0.00', $courtesy['unitPrice']);
        $this->assertSame('courtesy', $courtesy['status']);
        $this->assertTrue($courtesy['receiptAvailable'], 'cortesia nasce confirmada');

        // Total não inclui a cortesia
        $this->assertSame('2000.00', $response->json('data.orders.0.totalAmount'));
    }

    public function test_abaixo_do_gatilho_nao_gera_cortesia(): void
    {
        $this->courtesyEvent();

        $response = $this->buy(
            $this->buyer(),
            array_map(fn () => $this->item($this->individual), range(1, 9))
        )->assertCreated();

        $this->assertCount(9, $response->json('data.orders.0.tickets'));
    }

    public function test_limite_por_conta_considera_compras_anteriores(): void
    {
        $this->courtesyEvent(['courtesy_limit_per_account' => 1]);
        $buyer = $this->buyer();

        // 1ª compra: ganha a única cortesia permitida
        $first = $this->buy($buyer, $this->tenItems())->assertCreated();
        $this->assertCount(11, $first->json('data.orders.0.tickets'));

        // 2ª compra: limite atingido — nenhuma cortesia nova
        $second = $this->buy($buyer, $this->tenItems())->assertCreated();
        $this->assertCount(10, $second->json('data.orders.0.tickets'));
    }

    public function test_cortesia_que_nao_cabe_na_capacidade_recusa_o_pedido_inteiro(): void
    {
        // 10 pagáveis + 1 cortesia = 11 assentos > capacidade 10
        $this->courtesyEvent(['total_capacity' => 10]);

        $this->buy($this->buyer(), $this->tenItems())
            ->assertStatus(409)->assertJsonPath('type', 'sold_out');

        $this->assertSame(0, $this->event->orders()->count(), 'nunca meio-pedido');
    }

    public function test_voucher_distribuido_gera_pedido_proprio_pago(): void
    {
        $this->courtesyEvent();
        $voucher = CourtesyVoucher::query()->create([
            'event_id' => $this->event->id,
            'status' => CourtesyVoucher::DISTRIBUTED,
        ]);

        $response = $this->buy($this->buyer(), [$this->item($this->individual)], [
            'voucher_code' => $voucher->code,
            'courtesy_participants' => [['participant_name' => 'Vale Cortesia']],
        ])->assertCreated();

        $orders = $response->json('data.orders');
        $this->assertCount(2, $orders, 'carrinho + voucher = 2 pedidos');

        $voucherOrder = collect($orders)->firstWhere('totalAmount', '0.00');
        $this->assertSame('paid', $voucherOrder['status'], 'total 0 nasce pago');
        $this->assertNull($voucherOrder['reservedUntil'], 'pago não reserva');

        $fresh = $voucher->fresh();
        $this->assertSame(CourtesyVoucher::REDEEMED, $fresh->status);
        $this->assertNotNull($fresh->redeemed_ticket_id);
    }

    public function test_resgate_puro_sem_carrinho_funciona(): void
    {
        $this->courtesyEvent();
        $voucher = CourtesyVoucher::query()->create([
            'event_id' => $this->event->id,
            'status' => CourtesyVoucher::DISTRIBUTED,
        ]);

        $response = $this->buy($this->buyer(), [], ['voucher_code' => $voucher->code])
            ->assertCreated();

        $this->assertCount(1, $response->json('data.orders'));
        $this->assertSame('paid', $response->json('data.orders.0.status'));
    }

    public function test_vouchers_invalidos_recusam(): void
    {
        $this->courtesyEvent();
        $buyer = $this->buyer();

        // available (não distribuído)
        $available = CourtesyVoucher::query()->create(['event_id' => $this->event->id]);
        $this->buy($buyer, [], ['voucher_code' => $available->code])
            ->assertStatus(409)->assertJsonPath('type', 'invalid_voucher');

        // já resgatado
        $redeemed = CourtesyVoucher::query()->create([
            'event_id' => $this->event->id, 'status' => CourtesyVoucher::REDEEMED,
        ]);
        $this->buy($buyer, [], ['voucher_code' => $redeemed->code])
            ->assertStatus(409)->assertJsonPath('type', 'invalid_voucher');

        // inexistente
        $this->buy($buyer, [], ['voucher_code' => 'CTY-NAOEXISTE1'])
            ->assertStatus(409)->assertJsonPath('type', 'invalid_voucher');
    }

    public function test_expiracao_do_pedido_pago_nao_desfaz_o_voucher(): void
    {
        $this->courtesyEvent();
        $voucher = CourtesyVoucher::query()->create([
            'event_id' => $this->event->id,
            'status' => CourtesyVoucher::DISTRIBUTED,
        ]);

        $this->buy($this->buyer(), [$this->item($this->individual)], [
            'voucher_code' => $voucher->code,
        ])->assertCreated();

        Carbon::setTestNow(now()->addHours(2));
        $this->artisan('orders:expire')->assertSuccessful();
        Carbon::setTestNow();

        // Pedido do carrinho expirou; o do voucher permanece pago e resgatado
        $this->assertSame(CourtesyVoucher::REDEEMED, $voucher->fresh()->status);
        $paidOrders = Order::query()->where('total_amount', '0.00')->get();
        $this->assertSame('paid', $paidOrders->first()->status->slug);
    }
}
