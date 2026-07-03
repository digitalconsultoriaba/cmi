<?php

namespace Tests\Feature\Foundation;

use App\Domain\Events\Models\Event;
use App\Domain\Events\Models\Order;
use App\Domain\Events\Models\Ticket;
use App\Domain\Events\Models\TicketLot;
use App\Domain\Events\Models\TicketType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * US2 — snapshot: mudanças no catálogo não alteram ingressos já emitidos
 * (constituição, princípio II).
 */
class TicketSnapshotTest extends TestCase
{
    use RefreshDatabase;

    public function test_alterar_catalogo_nao_muda_snapshot_do_ticket(): void
    {
        $event = Event::factory()->published()->create();
        $type = TicketType::factory()->create(['event_id' => $event->id, 'price' => '200.00']);
        $lot = TicketLot::factory()->create([
            'event_id' => $event->id,
            'price_override' => '180.00',
        ]);
        $order = Order::factory()->create(['event_id' => $event->id]);

        $ticket = Ticket::factory()->create([
            'order_id' => $order->id,
            'event_id' => $event->id,
            'ticket_type_id' => $type->id,
            'ticket_lot_id' => $lot->id,
            'unit_price' => '180.00', // snapshot do preço efetivo na compra
            'participant_name' => 'Ana Participante',
        ]);

        // Catálogo muda depois da compra
        $type->update(['price' => '999.00', 'name' => 'Tipo renomeado']);
        $lot->update(['price_override' => '888.00']);

        $fresh = $ticket->fresh();
        $this->assertSame('180.00', $fresh->unit_price, 'preço snapshot não acompanha catálogo');
        $this->assertSame('Ana Participante', $fresh->participant_name);

        // Snapshot do comprador no pedido
        $order->buyerUser->update(['name' => 'Comprador Renomeado']);
        $this->assertNotSame('Comprador Renomeado', $order->fresh()->buyer_name);
    }
}
