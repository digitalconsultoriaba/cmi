<?php

namespace Tests\Feature\Panel;

use App\Domain\Events\Models\EventShirtModel;
use App\Domain\Events\Models\EventShirtSize;
use App\Domain\Events\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Lifecycle\LifecycleTestCase;

/**
 * US4 — camisas com estoque na tela: disponível = estoque − vendidas
 * (null = ilimitado), spec 009.
 */
class ShirtStockTest extends LifecycleTestCase
{
    use RefreshDatabase;

    private function admin()
    {
        $user = $this->buyer();
        $user->assignRole(Role::ADMIN);

        return $user;
    }

    public function test_disponivel_derivado_e_ilimitado(): void
    {
        $this->sellableEvent();
        $model = EventShirtModel::factory()->create([
            'event_id' => $this->event->id, 'label' => 'Masculina',
        ]);
        // Tamanho M com estoque 60 e 2 vendidas → disponível 58
        $m = EventShirtSize::factory()->create([
            'shirt_model_id' => $model->id, 'event_id' => $this->event->id,
            'label' => 'M', 'stock_quantity' => 60, 'sold_count' => 2,
        ]);
        // Tamanho G ilimitado (estoque null)
        EventShirtSize::factory()->create([
            'shirt_model_id' => $model->id, 'event_id' => $this->event->id,
            'label' => 'G', 'stock_quantity' => null, 'sold_count' => 0,
        ]);

        $response = $this->actingAs($this->admin())
            ->getJson("/api/admin/events/{$this->event->id}/shirt-models")->assertOk();

        $sizes = collect($response->json('data.0.sizes'));
        $this->assertSame(58, $sizes->firstWhere('label', 'M')['available']);
        $this->assertNull($sizes->firstWhere('label', 'G')['available'], 'ilimitado');
    }

    public function test_nunca_disponivel_negativo(): void
    {
        $this->sellableEvent();
        $model = EventShirtModel::factory()->create(['event_id' => $this->event->id]);
        // Vendas acima do estoque (dado legado) → disponível piso 0, nunca negativo
        EventShirtSize::factory()->create([
            'shirt_model_id' => $model->id, 'event_id' => $this->event->id,
            'label' => 'P', 'stock_quantity' => 5, 'sold_count' => 8,
        ]);

        $response = $this->actingAs($this->admin())
            ->getJson("/api/admin/events/{$this->event->id}/shirt-models")->assertOk();

        $this->assertSame(0, $response->json('data.0.sizes.0.available'));
    }
}
