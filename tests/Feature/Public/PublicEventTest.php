<?php

namespace Tests\Feature\Public;

use App\Domain\Events\Models\Event;
use App\Domain\Events\Models\EventStatus;
use App\Domain\Events\Models\LandingBlock;
use App\Domain\Events\Models\TicketLot;
use App\Domain\Events\Models\TicketType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * US1 — catálogo público (quickstart §US1).
 */
class PublicEventTest extends TestCase
{
    use RefreshDatabase;

    public function test_evento_publicado_expoe_blocos_e_catalogo_do_lote_vigente(): void
    {
        $event = Event::factory()->published()->create();
        $type = TicketType::factory()->create(['event_id' => $event->id, 'price' => '250.00']);
        TicketLot::factory()->create([
            'event_id' => $event->id, 'name' => '1º lote', 'price_override' => '200.00',
        ]);

        LandingBlock::factory()->create([
            'event_id' => $event->id, 'type' => 'hero', 'sort' => 0,
            'payload' => ['title' => 'Bem-vindos'],
        ]);
        LandingBlock::factory()->create([
            'event_id' => $event->id, 'type' => 'cta', 'sort' => 1,
            'payload' => ['label' => 'Inscreva-se'], 'is_active' => false,
        ]);

        $response = $this->getJson("/api/public/events/{$event->slug}")->assertOk();

        $this->assertCount(1, $response->json('data.blocks'), 'bloco inativo fica fora');
        $this->assertSame('hero', $response->json('data.blocks.0.type'));
        $this->assertSame('open', $response->json('data.salesState'));

        $catalogType = collect($response->json('data.ticketTypes'))->firstWhere('id', $type->id);
        $this->assertSame('200.00', $catalogType['effectivePrice']);
        $this->assertSame('1º lote', $catalogType['currentLotName']);
        $this->assertTrue($catalogType['purchasable']);
    }

    public function test_sales_state_cobre_os_quatro_cenarios(): void
    {
        // soon — janela futura
        $soon = Event::factory()->published()->create([
            'sales_start_at' => now()->addDays(5),
            'sales_end_at' => now()->addDays(30),
        ]);
        TicketLot::factory()->create(['event_id' => $soon->id, 'starts_at' => null, 'ends_at' => null]);
        $this->getJson("/api/public/events/{$soon->slug}")
            ->assertOk()->assertJsonPath('data.salesState', 'soon');

        // closed — janela encerrada
        $closed = Event::factory()->published()->create([
            'sales_start_at' => now()->subDays(30),
            'sales_end_at' => now()->subDay(),
        ]);
        $this->getJson("/api/public/events/{$closed->slug}")
            ->assertOk()->assertJsonPath('data.salesState', 'closed');

        // soldOut — capacidade zero disponível
        $full = Event::factory()->published()->create(['total_capacity' => 1]);
        $type = TicketType::factory()->create(['event_id' => $full->id]);
        TicketLot::factory()->create(['event_id' => $full->id]);
        $order = \App\Domain\Events\Models\Order::factory()->create(['event_id' => $full->id]);
        \App\Domain\Events\Models\Ticket::factory()->create([
            'order_id' => $order->id, 'event_id' => $full->id, 'ticket_type_id' => $type->id,
        ]);
        $this->getJson("/api/public/events/{$full->slug}")
            ->assertOk()->assertJsonPath('data.salesState', 'soldOut');
    }

    public function test_rascunho_e_inexistente_dao_404_e_cancelado_informa(): void
    {
        $draft = Event::factory()->create();
        $this->getJson("/api/public/events/{$draft->slug}")->assertNotFound();

        $this->getJson('/api/public/events/nao-existe')->assertNotFound();

        $cancelled = Event::factory()->create([
            'status_id' => EventStatus::idFor(EventStatus::CANCELLED),
            'cancel_reason' => 'Motivo público',
        ]);
        $this->getJson("/api/public/events/{$cancelled->slug}")
            ->assertOk()
            ->assertJsonPath('data.cancelled', true)
            ->assertJsonPath('data.cancelReason', 'Motivo público')
            ->assertJsonMissingPath('data.ticketTypes');
    }
}
