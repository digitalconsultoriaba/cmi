<?php

namespace Tests\Feature\Finance;

use App\Domain\Events\Models\FinancialEntry;
use App\Domain\Events\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * FR-020 — espelho de ingressos/patrocínios em contas a receber, sincronizado
 * e sem duplicidade (spec 010).
 */
class MirrorSyncTest extends FinanceTestCase
{
    use RefreshDatabase;

    public function test_pedido_espelha_conta_a_receber_sincronizada(): void
    {
        $this->sellableEvent();
        $buyer = $this->buyer();
        $code = $this->buy($buyer, [$this->item($this->individual)])->json('data.orders.0.code');
        $order = Order::query()->where('code', $code)->firstOrFail();

        // Pendente → conta a receber "em aberto" espelhada
        $mirror = FinancialEntry::query()->where('source_type', Order::class)
            ->where('source_id', $order->id)->firstOrFail();
        $this->assertSame('receivable', $mirror->direction);
        $this->assertSame('open', $mirror->status());
        $this->assertTrue($mirror->isMirror());

        // Pagar → recebido (sem criar segundo lançamento)
        $this->actingAs($buyer)->postJson("/api/orders/{$code}/checkout/card", [
            'token' => 'tok_ok_4242', 'installments' => 1,
        ])->assertOk();

        $this->assertSame(1, FinancialEntry::query()->where('source_type', Order::class)
            ->where('source_id', $order->id)->count(), 'sem duplicidade');
        $this->assertSame('settled', $mirror->fresh()->status());
    }

    public function test_espelho_e_read_only_e_cortesia_nao_gera_receita(): void
    {
        [, $order] = $this->paidOrder();
        $mirror = FinancialEntry::query()->where('source_type', Order::class)
            ->where('source_id', $order->id)->firstOrFail();

        // Editar espelho → 409
        $this->actingAs($this->finance())->putJson("/api/finance/entries/{$mirror->id}", [
            'description' => 'x',
        ])->assertStatus(409)->assertJsonPath('type', 'mirror_readonly');

        // Baixar espelho → 409
        $this->actingAs($this->finance())->postJson("/api/finance/entries/{$mirror->id}/settle", [
            'amount' => '10.00', 'settled_on' => now()->toDateString(),
        ])->assertStatus(409)->assertJsonPath('type', 'mirror_readonly');

        // Cortesia (pedido total zero) não gera receita: nenhum espelho com amount 0
        $this->assertFalse(FinancialEntry::query()->where('origin', 'ticket')
            ->where('amount', '0.00')->exists());
    }
}
