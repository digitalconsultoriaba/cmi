<?php

namespace Tests\Feature\Admin;

use App\Domain\Events\Models\Event;
use App\Domain\Events\Models\EventShirtModel;
use App\Domain\Events\Models\EventShirtSize;
use App\Domain\Events\Models\TicketType;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * US3 — camisas com estoque (quickstart §US3).
 */
class ShirtTest extends AdminTestCase
{
    use RefreshDatabase;

    public function test_crud_hierarquico_com_esgotamento_visivel(): void
    {
        $admin = $this->admin();
        $event = Event::factory()->create();

        $model = $this->actingAs($admin)
            ->postJson("/api/admin/events/{$event->id}/shirt-models", ['label' => 'Unissex'])
            ->assertCreated()->json('data.id');

        $this->actingAs($admin)
            ->postJson("/api/admin/events/{$event->id}/shirt-models/{$model}/sizes", [
                'label' => 'M', 'stock_quantity' => 10,
            ])->assertCreated();

        $this->actingAs($admin)
            ->postJson("/api/admin/events/{$event->id}/shirt-models/{$model}/sizes", [
                'label' => 'G', 'stock_quantity' => null,
            ])->assertCreated();

        $list = $this->actingAs($admin)
            ->getJson("/api/admin/events/{$event->id}/shirt-models")->assertOk();

        $sizes = collect($list->json('data.0.sizes'));
        $this->assertFalse($sizes->firstWhere('label', 'M')['soldOut']);
        $this->assertNull($sizes->firstWhere('label', 'G')['stockQuantity']);
    }

    public function test_estoque_abaixo_do_vendido_recusa(): void
    {
        $event = Event::factory()->published()->create();
        $type = TicketType::factory()->create(['event_id' => $event->id]);
        $model = EventShirtModel::factory()->create(['event_id' => $event->id]);
        $size = EventShirtSize::factory()->create([
            'shirt_model_id' => $model->id,
            'event_id' => $event->id,
            'stock_quantity' => 10,
            'sold_count' => 3,
        ]);

        $this->actingAs($this->admin())
            ->putJson("/api/admin/events/{$event->id}/shirt-models/{$model->id}/sizes/{$size->id}", [
                'label' => $size->label, 'stock_quantity' => 2,
            ])
            ->assertStatus(409)->assertJsonPath('type', 'stock_below_sold');
    }

    public function test_excluir_tamanho_com_vendas_recusa(): void
    {
        $event = Event::factory()->published()->create();
        $type = TicketType::factory()->create(['event_id' => $event->id]);
        $model = EventShirtModel::factory()->create(['event_id' => $event->id]);
        $size = EventShirtSize::factory()->create([
            'shirt_model_id' => $model->id,
            'event_id' => $event->id,
        ]);
        $this->sellTicket($event, $type, ['shirt_model_id' => $model->id, 'shirt_size_id' => $size->id]);

        $this->actingAs($this->admin())
            ->deleteJson("/api/admin/events/{$event->id}/shirt-models/{$model->id}/sizes/{$size->id}")
            ->assertStatus(409)->assertJsonPath('type', 'has_sales');

        $this->actingAs($this->admin())
            ->deleteJson("/api/admin/events/{$event->id}/shirt-models/{$model->id}")
            ->assertStatus(409)->assertJsonPath('type', 'has_sales');
    }
}
