<?php

namespace Tests\Feature\Purchase;

use App\Domain\Events\Models\EventShirtModel;
use App\Domain\Events\Models\EventShirtSize;
use App\Domain\Events\Models\TicketLot;
use App\Domain\Events\Models\TicketStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * US2 — contenção determinística: os limites NUNCA são ultrapassados
 * (SC-002; invariante 1 do data-model). Paralelismo real no smoke do quickstart.
 */
class PurchaseContentionTest extends PurchaseTestCase
{
    use RefreshDatabase;

    public function test_ultima_vaga_do_evento_so_atende_um_pedido(): void
    {
        $this->sellableEvent(['total_capacity' => 1]);

        $this->buy($this->buyer(), [$this->item($this->individual)])->assertCreated();
        $this->buy($this->buyer(), [$this->item($this->individual)])
            ->assertStatus(409)->assertJsonPath('type', 'sold_out');

        $this->assertSame(1, $this->event->fresh()->ticketsSold(), 'limite intacto');
    }

    public function test_casal_com_uma_vaga_restante_recusa(): void
    {
        $this->sellableEvent(['total_capacity' => 1]);

        $this->buy($this->buyer(), [
            $this->item($this->couple, ['companion_name' => 'Par']),
        ])->assertStatus(409)->assertJsonPath('type', 'sold_out');

        $this->assertSame(0, $this->event->fresh()->ticketsSold());
    }

    public function test_capacidade_do_tipo_e_respeitada(): void
    {
        $this->sellableEvent();
        $this->individual->update(['capacity' => 1]);

        $this->buy($this->buyer(), [$this->item($this->individual)])->assertCreated();
        $this->buy($this->buyer(), [$this->item($this->individual)])
            ->assertStatus(409)->assertJsonPath('type', 'sold_out');
    }

    public function test_ultima_unidade_do_lote_esgota_e_proximo_lote_assume_o_preco(): void
    {
        $this->sellableEvent();
        $this->lot->update(['quantity' => 1, 'sort' => 0]);
        TicketLot::factory()->create([
            'event_id' => $this->event->id,
            'name' => '2º lote',
            'price_override' => null, // preço do tipo: 250,00
            'sort' => 1,
        ]);

        $first = $this->buy($this->buyer(), [$this->item($this->individual)])->assertCreated();
        $this->assertSame('200.00', $first->json('data.orders.0.tickets.0.unitPrice'));

        // Lote 1 esgotado → lote 2 vigente, preço do tipo
        $second = $this->buy($this->buyer(), [$this->item($this->individual)])->assertCreated();
        $this->assertSame('250.00', $second->json('data.orders.0.tickets.0.unitPrice'));

        $this->assertSame(1, $this->lot->fresh()->sold_count, 'recount do cache');
    }

    public function test_estoque_de_camisa_conta_titular_e_acompanhante(): void
    {
        $this->sellableEvent();
        $model = EventShirtModel::factory()->create(['event_id' => $this->event->id]);
        $size = EventShirtSize::factory()->create([
            'shirt_model_id' => $model->id,
            'event_id' => $this->event->id,
            'stock_quantity' => 2,
        ]);

        // Casal: titular + acompanhante com o mesmo tamanho = 2 unidades → ok
        $this->buy($this->buyer(), [
            $this->item($this->couple, [
                'companion_name' => 'Par',
                'shirt_model_id' => $model->id, 'shirt_size_id' => $size->id,
                'companion_shirt_model_id' => $model->id, 'companion_shirt_size_id' => $size->id,
            ]),
        ])->assertCreated();

        $this->assertSame(2, $size->fresh()->sold_count);

        // Estoque zerado → próxima compra com essa camisa recusa
        $this->buy($this->buyer(), [
            $this->item($this->individual, [
                'shirt_model_id' => $model->id, 'shirt_size_id' => $size->id,
            ]),
        ])->assertStatus(409)->assertJsonPath('type', 'sold_out');

        $this->assertSame(2, $size->fresh()->sold_count, 'estoque nunca excedido');
    }

    public function test_pedido_recusado_nao_deixa_rastro(): void
    {
        $this->sellableEvent(['total_capacity' => 1]);

        // 2 itens numa capacidade 1 → recusa total, nada parcial
        $this->buy($this->buyer(), [
            $this->item($this->individual),
            $this->item($this->individual),
        ])->assertStatus(409);

        $this->assertSame(0, $this->event->orders()->count());
        $this->assertSame(
            0,
            $this->event->tickets()
                ->whereIn('status_id', TicketStatus::idsFor(TicketStatus::COUNTS_CAPACITY))
                ->count()
        );
    }
}
