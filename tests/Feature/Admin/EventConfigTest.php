<?php

namespace Tests\Feature\Admin;

use App\Domain\Events\Models\Event;
use App\Domain\Events\Models\EventStatus;
use App\Domain\Events\Models\TicketType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * US1 — configurar/publicar/cancelar/banner (quickstart §US1).
 */
class EventConfigTest extends AdminTestCase
{
    use RefreshDatabase;

    public function test_update_persiste_configuracao_e_registra_auditoria(): void
    {
        $admin = $this->admin();
        $event = Event::factory()->create();

        $this->actingAs($admin)->putJson("/api/admin/events/{$event->id}", [
            'name' => 'Seminário Renomeado',
            'allow_courtesy' => true,
            'courtesy_paid_threshold' => 10,
            'total_capacity' => 300,
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Seminário Renomeado')
            ->assertJsonPath('data.allowCourtesy', true);

        $this->assertSame($admin->id, $event->fresh()->updated_by);
    }

    public function test_publish_sem_tipo_ativo_recusa_listando_faltantes(): void
    {
        $event = Event::factory()->create(); // draft, sem tipos

        $response = $this->actingAs($this->admin())
            ->postJson("/api/admin/events/{$event->id}/publish");

        $response->assertStatus(409)
            ->assertJsonPath('type', 'publish_requirements');

        $this->assertContains(
            'ao menos um tipo de ingresso ativo',
            $response->json('errors.missing')
        );
    }

    public function test_publish_valido_publica(): void
    {
        $event = Event::factory()->create();
        TicketType::factory()->create(['event_id' => $event->id, 'is_active' => true]);

        $this->actingAs($this->admin())
            ->postJson("/api/admin/events/{$event->id}/publish")
            ->assertOk()
            ->assertJsonPath('data.status', EventStatus::PUBLISHED);
    }

    public function test_cancel_exige_motivo_e_registra_trilha(): void
    {
        $admin = $this->admin();
        $event = Event::factory()->published()->create();

        $this->actingAs($admin)
            ->postJson("/api/admin/events/{$event->id}/cancel", [])
            ->assertUnprocessable();

        $this->actingAs($admin)
            ->postJson("/api/admin/events/{$event->id}/cancel", ['reason' => 'Motivo de força maior'])
            ->assertOk()
            ->assertJsonPath('data.status', EventStatus::CANCELLED);

        $fresh = $event->fresh();
        $this->assertSame('Motivo de força maior', $fresh->cancel_reason);
        $this->assertSame($admin->id, $fresh->cancelled_by);
        $this->assertNotNull($fresh->cancelled_at);
    }

    public function test_evento_cancelado_rejeita_update_publish_e_cancel(): void
    {
        $event = Event::factory()->create([
            'status_id' => EventStatus::idFor(EventStatus::CANCELLED),
        ]);
        $admin = $this->admin();

        $this->actingAs($admin)->putJson("/api/admin/events/{$event->id}", ['name' => 'X'])
            ->assertStatus(409);
        $this->actingAs($admin)->postJson("/api/admin/events/{$event->id}/publish")
            ->assertStatus(409);
        $this->actingAs($admin)->postJson("/api/admin/events/{$event->id}/cancel", ['reason' => 'de novo'])
            ->assertStatus(409);
    }

    public function test_capacidade_abaixo_do_vendido_recusa(): void
    {
        $event = Event::factory()->published()->create(['total_capacity' => 100]);
        $type = TicketType::factory()->create(['event_id' => $event->id]);
        $this->sellTicket($event, $type);
        $this->sellTicket($event, $type);

        $this->actingAs($this->admin())
            ->putJson("/api/admin/events/{$event->id}", ['total_capacity' => 1])
            ->assertStatus(409)
            ->assertJsonPath('type', 'capacity_below_sold');
    }

    public function test_banner_valido_salva_e_invalido_recusa(): void
    {
        Storage::fake('public');
        $event = Event::factory()->create();
        $admin = $this->admin();

        $ok = $this->actingAs($admin)->post("/api/admin/events/{$event->id}/banner", [
            'banner' => UploadedFile::fake()->image('banner.jpg', 1200, 400),
        ], ['Accept' => 'application/json']);

        $ok->assertOk();
        $this->assertNotNull($ok->json('data.bannerUrl'));
        Storage::disk('public')->assertExists($event->fresh()->banner_path);

        $this->actingAs($admin)->post("/api/admin/events/{$event->id}/banner", [
            'banner' => UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertUnprocessable();

        $this->actingAs($admin)->post("/api/admin/events/{$event->id}/banner", [
            'banner' => UploadedFile::fake()->image('grande.jpg')->size(6000),
        ], ['Accept' => 'application/json'])->assertUnprocessable();
    }
}
