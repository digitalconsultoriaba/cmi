<?php

namespace Tests\Feature\Foundation;

use App\Domain\Events\Models\Event;
use App\Domain\Events\Models\EventShirtModel;
use App\Domain\Events\Models\EventShirtSize;
use App\Domain\Events\Models\EventStatus;
use App\Domain\Events\Models\Order;
use App\Domain\Events\Models\Ticket;
use App\Domain\Events\Models\TicketLot;
use App\Domain\Events\Models\TicketType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * US3 — cenários 1–6 de contracts/domain-derivations.md. Nenhum campo de
 * status é editado manualmente: tudo deriva dos dados.
 */
class DerivationsTest extends TestCase
{
    use RefreshDatabase;

    private function publishedEventWithLot(array $eventAttrs = [], array $lotAttrs = []): Event
    {
        $event = Event::factory()->published()->create($eventAttrs);
        TicketLot::factory()->create(['event_id' => $event->id, ...$lotAttrs]);

        return $event;
    }

    public function test_cenario_1_janela_aberta_lote_com_saldo_e_vagas_abre_inscricoes(): void
    {
        $event = $this->publishedEventWithLot(['total_capacity' => 100]);

        $this->assertTrue($event->salesOpen());
    }

    public function test_cenario_2_janela_encerrada_ou_sem_lote_ou_sem_vagas_fecha_inscricoes(): void
    {
        // Janela encerrada
        $event = $this->publishedEventWithLot([
            'sales_start_at' => now()->subDays(10),
            'sales_end_at' => now()->subDay(),
        ]);
        $this->assertFalse($event->salesOpen(), 'janela encerrada');

        // Sem lote elegível
        $noLot = Event::factory()->published()->create();
        $this->assertFalse($noLot->salesOpen(), 'sem lote vigente');

        // Rascunho não vende
        $draft = Event::factory()->create();
        TicketLot::factory()->create(['event_id' => $draft->id]);
        $this->assertFalse($draft->salesOpen(), 'evento não publicado');

        // Capacidade atingida (2 vagas, 2 tickets vivos)
        $full = $this->publishedEventWithLot(['total_capacity' => 2]);
        $type = TicketType::factory()->create(['event_id' => $full->id]);
        $order = Order::factory()->create(['event_id' => $full->id]);
        Ticket::factory()->count(2)->create([
            'order_id' => $order->id,
            'event_id' => $full->id,
            'ticket_type_id' => $type->id,
        ]);

        $this->assertSame(0, $full->available());
        $this->assertTrue($full->soldOut());
        $this->assertFalse($full->salesOpen(), 'sem vagas');
    }

    public function test_cenario_3_lote_vira_por_quantidade_e_por_data_e_preco_efetivo_acompanha(): void
    {
        $event = Event::factory()->published()->create();
        $type = TicketType::factory()->create(['event_id' => $event->id, 'price' => '250.00']);

        $lot1 = TicketLot::factory()->create([
            'event_id' => $event->id,
            'name' => '1º lote',
            'price_override' => '200.00',
            'quantity' => 1,
            'sort' => 0,
        ]);
        $lot2 = TicketLot::factory()->create([
            'event_id' => $event->id,
            'name' => '2º lote',
            'price_override' => null,
            'sort' => 1,
        ]);

        $this->assertTrue($event->currentLot()->is($lot1));
        $this->assertSame('200.00', $event->currentLot()->effectivePrice($type));

        // Esgota por quantidade → vira para o lote 2 e o preço efetivo muda
        $lot1->forceFill(['sold_count' => 1])->save();
        $this->assertTrue($lot1->fresh()->soldOut());
        $this->assertTrue($event->currentLot()->is($lot2));
        $this->assertSame('250.00', $event->currentLot()->effectivePrice($type));

        // Expira por data (independente da quantidade)
        $lot1->forceFill(['sold_count' => 0, 'ends_at' => now()->subHour()])->save();
        $this->assertTrue($event->currentLot()->is($lot2));
    }

    public function test_cenario_4_janelas_sobrepostas_resolvem_por_sort_deterministico(): void
    {
        $event = Event::factory()->published()->create();

        $second = TicketLot::factory()->create(['event_id' => $event->id, 'sort' => 5]);
        $first = TicketLot::factory()->create(['event_id' => $event->id, 'sort' => 1]);

        $this->assertTrue($event->currentLot()->is($first), 'menor sort vence');
        $this->assertTrue($first->isCurrent());
        $this->assertFalse($second->isCurrent());
    }

    public function test_cenario_5_sem_override_preco_efetivo_e_o_do_tipo(): void
    {
        $event = Event::factory()->published()->create();
        $type = TicketType::factory()->create(['event_id' => $event->id, 'price' => '300.00']);
        $lot = TicketLot::factory()->create(['event_id' => $event->id, 'price_override' => null]);

        $this->assertSame('300.00', $lot->effectivePrice($type));

        // Lote específico do tipo tem precedência sobre o global
        $specific = TicketLot::factory()->create([
            'event_id' => $event->id,
            'ticket_type_id' => $type->id,
            'price_override' => '270.00',
            'sort' => 9,
        ]);
        $this->assertTrue($event->currentLot($type)->is($specific));
        $this->assertSame('270.00', $event->currentLot($type)->effectivePrice($type));
    }

    public function test_cenario_6_camisa_esgota_com_estoque_definido_e_nunca_com_estoque_nulo(): void
    {
        $event = Event::factory()->published()->create();
        $model = EventShirtModel::factory()->create(['event_id' => $event->id]);

        $finite = EventShirtSize::factory()->create([
            'event_id' => $event->id,
            'shirt_model_id' => $model->id,
            'stock_quantity' => 10,
            'sold_count' => 10,
        ]);
        $unlimited = EventShirtSize::factory()->create([
            'event_id' => $event->id,
            'shirt_model_id' => $model->id,
            'stock_quantity' => null,
            'sold_count' => 999,
        ]);

        $this->assertTrue($finite->soldOut());
        $this->assertFalse($unlimited->soldOut(), 'estoque null = ilimitado');
    }

    public function test_recount_recalcula_cache_a_partir_da_fonte_de_verdade(): void
    {
        $event = Event::factory()->published()->create();
        $type = TicketType::factory()->create(['event_id' => $event->id]);
        $lot = TicketLot::factory()->create(['event_id' => $event->id, 'sold_count' => 99]);
        $order = Order::factory()->create(['event_id' => $event->id]);

        Ticket::factory()->count(3)->create([
            'order_id' => $order->id,
            'event_id' => $event->id,
            'ticket_type_id' => $type->id,
            'ticket_lot_id' => $lot->id,
        ]);

        $this->assertSame(3, $lot->recountSold(), 'contagem real substitui cache defasado');
        $this->assertSame(3, $lot->fresh()->sold_count);
    }
}
