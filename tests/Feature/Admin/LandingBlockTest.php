<?php

namespace Tests\Feature\Admin;

use App\Domain\Events\Models\Event;
use App\Domain\Events\Models\LandingBlock;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * US4 — editor da landing por blocos (quickstart §US4).
 */
class LandingBlockTest extends AdminTestCase
{
    use RefreshDatabase;

    private array $validPayloads = [
        'hero' => ['title' => 'Grande Seminário'],
        'text' => ['body' => 'Um texto qualquer.'],
        'schedule' => ['items' => [['day' => 'Sexta', 'description' => 'Abertura']]],
        'speakers' => ['items' => [['name' => 'Palestrante']]],
        'faq' => ['items' => [['q' => 'Pergunta?', 'a' => 'Resposta.']]],
        'location' => ['address' => 'Centro de Convenções'],
        'cta' => ['label' => 'Inscreva-se'],
    ];

    public function test_cria_bloco_de_cada_tipo(): void
    {
        $admin = $this->admin();
        $event = Event::factory()->create();

        foreach ($this->validPayloads as $type => $payload) {
            $this->actingAs($admin)
                ->postJson("/api/admin/events/{$event->id}/landing-blocks", [
                    'type' => $type,
                    'payload' => $payload,
                ])->assertCreated()->assertJsonPath('data.type', $type);
        }

        $this->assertSame(7, $event->landingBlocks()->count());
    }

    public function test_payload_invalido_por_tipo_recusa(): void
    {
        $admin = $this->admin();
        $event = Event::factory()->create();

        $this->actingAs($admin)
            ->postJson("/api/admin/events/{$event->id}/landing-blocks", [
                'type' => 'hero', 'payload' => ['subtitle' => 'sem título'],
            ])->assertUnprocessable()->assertJsonValidationErrors(['payload.title']);

        $this->actingAs($admin)
            ->postJson("/api/admin/events/{$event->id}/landing-blocks", [
                'type' => 'faq', 'payload' => ['items' => []],
            ])->assertUnprocessable()->assertJsonValidationErrors(['payload.items']);

        $this->actingAs($admin)
            ->postJson("/api/admin/events/{$event->id}/landing-blocks", [
                'type' => 'inexistente', 'payload' => ['x' => 1],
            ])->assertUnprocessable()->assertJsonValidationErrors(['type']);
    }

    public function test_reorder_em_massa_persiste(): void
    {
        $admin = $this->admin();
        $event = Event::factory()->create();
        $hero = LandingBlock::factory()->create(['event_id' => $event->id, 'type' => 'hero', 'sort' => 0]);
        $cta = LandingBlock::factory()->create(['event_id' => $event->id, 'type' => 'cta', 'sort' => 1]);

        $this->actingAs($admin)
            ->patchJson("/api/admin/events/{$event->id}/landing-blocks/reorder", [
                'ids' => [$cta->id, $hero->id],
            ])->assertOk();

        $this->assertSame(0, $cta->fresh()->sort);
        $this->assertSame(1, $hero->fresh()->sort);
    }

    public function test_desativar_e_excluir_soft(): void
    {
        $admin = $this->admin();
        $event = Event::factory()->create();
        $block = LandingBlock::factory()->create([
            'event_id' => $event->id, 'type' => 'hero',
            'payload' => ['title' => 'Título'],
        ]);

        $this->actingAs($admin)
            ->putJson("/api/admin/events/{$event->id}/landing-blocks/{$block->id}", [
                'type' => 'hero', 'payload' => ['title' => 'Título'], 'is_active' => false,
            ])->assertOk()->assertJsonPath('data.isActive', false);

        $this->actingAs($admin)
            ->deleteJson("/api/admin/events/{$event->id}/landing-blocks/{$block->id}")
            ->assertOk();
        $this->assertSoftDeleted('landing_blocks', ['id' => $block->id]);
    }
}
