<?php

namespace Tests\Feature\Admin;

use App\Domain\Events\Models\Event;
use App\Domain\Events\Models\TicketLot;
use App\Domain\Events\Models\TicketType;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * US2 — tipos de ingresso e lotes (quickstart §US2).
 */
class TicketCatalogTest extends AdminTestCase
{
    use RefreshDatabase;

    public function test_crud_de_tipos_com_ordenacao(): void
    {
        $admin = $this->admin();
        $event = Event::factory()->create();

        $created = $this->actingAs($admin)->postJson("/api/admin/events/{$event->id}/ticket-types", [
            'name' => 'Individual',
            'price' => '250.00',
            'capacity' => 100,
        ])->assertCreated();

        $id = $created->json('data.id');

        $this->actingAs($admin)->putJson("/api/admin/events/{$event->id}/ticket-types/{$id}", [
            'name' => 'Individual Premium',
            'price' => '300.00',
            'is_active' => false,
        ])->assertOk()->assertJsonPath('data.isActive', false);

        $other = TicketType::factory()->create(['event_id' => $event->id]);

        $this->actingAs($admin)->patchJson("/api/admin/events/{$event->id}/ticket-types/reorder", [
            'ids' => [$other->id, $id],
        ])->assertOk();

        $this->assertSame(0, $other->fresh()->sort);
        $this->assertSame(1, TicketType::find($id)->sort);
    }

    public function test_excluir_tipo_ou_lote_com_vendas_recusa_e_desativar_funciona(): void
    {
        $admin = $this->admin();
        $event = Event::factory()->published()->create();
        $type = TicketType::factory()->create(['event_id' => $event->id]);
        $lot = TicketLot::factory()->create(['event_id' => $event->id]);
        $this->sellTicket($event, $type, ['ticket_lot_id' => $lot->id]);

        $this->actingAs($admin)
            ->deleteJson("/api/admin/events/{$event->id}/ticket-types/{$type->id}")
            ->assertStatus(409)->assertJsonPath('type', 'has_sales');

        $this->actingAs($admin)
            ->deleteJson("/api/admin/events/{$event->id}/lots/{$lot->id}")
            ->assertStatus(409)->assertJsonPath('type', 'has_sales');

        // Desativar continua possível
        $this->actingAs($admin)
            ->putJson("/api/admin/events/{$event->id}/ticket-types/{$type->id}", [
                'name' => $type->name, 'price' => $type->price, 'is_active' => false,
            ])->assertOk();

        // Sem vendas, excluir funciona (soft)
        $unsold = TicketType::factory()->create(['event_id' => $event->id]);
        $this->actingAs($admin)
            ->deleteJson("/api/admin/events/{$event->id}/ticket-types/{$unsold->id}")
            ->assertOk();
        $this->assertSoftDeleted('ticket_types', ['id' => $unsold->id]);
    }

    public function test_capacidade_do_tipo_abaixo_do_vendido_recusa(): void
    {
        $event = Event::factory()->published()->create();
        $type = TicketType::factory()->create(['event_id' => $event->id, 'capacity' => 50]);
        $this->sellTicket($event, $type);
        $this->sellTicket($event, $type);

        $this->actingAs($this->admin())
            ->putJson("/api/admin/events/{$event->id}/ticket-types/{$type->id}", [
                'name' => $type->name, 'price' => $type->price, 'capacity' => 1,
            ])
            ->assertStatus(409)->assertJsonPath('type', 'capacity_below_sold');
    }

    public function test_lote_expoe_derivacoes_da_fundacao(): void
    {
        $admin = $this->admin();
        $event = Event::factory()->published()->create();
        $type = TicketType::factory()->create(['event_id' => $event->id, 'price' => '250.00']);

        $this->actingAs($admin)->postJson("/api/admin/events/{$event->id}/lots", [
            'name' => '1º lote',
            'price_override' => '200.00',
            'starts_at' => now()->subDay()->toDateTimeString(),
            'ends_at' => now()->addDays(10)->toDateTimeString(),
            'sort' => 0,
        ])->assertCreated();

        $list = $this->actingAs($admin)
            ->getJson("/api/admin/events/{$event->id}/lots")
            ->assertOk();

        $lot = $list->json('data.0');
        $this->assertTrue($lot['isCurrent']);
        $this->assertFalse($lot['soldOut']);
        $this->assertSame('200.00', $lot['effectivePrice']);
    }

    public function test_preco_invalido_recusa(): void
    {
        $event = Event::factory()->create();

        $this->actingAs($this->admin())
            ->postJson("/api/admin/events/{$event->id}/ticket-types", [
                'name' => 'Inválido', 'price' => -10,
            ])->assertUnprocessable()->assertJsonValidationErrors(['price']);
    }
}
