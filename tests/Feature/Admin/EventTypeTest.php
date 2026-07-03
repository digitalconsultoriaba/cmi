<?php

namespace Tests\Feature\Admin;

use App\Domain\Events\Models\Event;
use App\Domain\Events\Models\EventType;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Polish — lookup de tipos de evento (FR-006).
 */
class EventTypeTest extends AdminTestCase
{
    use RefreshDatabase;

    public function test_crud_do_lookup(): void
    {
        $admin = $this->admin();

        $created = $this->actingAs($admin)
            ->postJson('/api/admin/event-types', ['name' => 'Retiro'])
            ->assertCreated();

        $id = $created->json('data.id');

        $this->actingAs($admin)
            ->putJson("/api/admin/event-types/{$id}", ['name' => 'Retiro Espiritual', 'is_active' => false])
            ->assertOk()->assertJsonPath('data.isActive', false);

        $this->actingAs($admin)
            ->deleteJson("/api/admin/event-types/{$id}")
            ->assertOk();
        $this->assertSoftDeleted('event_types', ['id' => $id]);
    }

    public function test_tipo_em_uso_nao_pode_ser_excluido(): void
    {
        $event = Event::factory()->create();

        $this->actingAs($this->admin())
            ->deleteJson("/api/admin/event-types/{$event->event_type_id}")
            ->assertStatus(409)->assertJsonPath('type', 'in_use');
    }

    public function test_nome_duplicado_recusa(): void
    {
        EventType::query()->create(['name' => 'Retiro']);

        $this->actingAs($this->admin())
            ->postJson('/api/admin/event-types', ['name' => 'Retiro'])
            ->assertUnprocessable()->assertJsonValidationErrors(['name']);
    }
}
