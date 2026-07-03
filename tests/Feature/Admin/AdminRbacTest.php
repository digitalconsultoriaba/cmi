<?php

namespace Tests\Feature\Admin;

use App\Domain\Events\Models\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Fase 2 — RBAC do painel: anônimo 401, sem papel 403, admin nunca 403.
 */
class AdminRbacTest extends AdminTestCase
{
    use RefreshDatabase;

    public function test_rotas_admin_exigem_sessao_e_papel(): void
    {
        $event = Event::factory()->create();

        $sample = [
            ['get', '/api/admin/events'],
            ['get', "/api/admin/events/{$event->id}/ticket-types"],
            ['get', "/api/admin/events/{$event->id}/landing-blocks"],
            ['get', "/api/admin/events/{$event->id}/sponsorships"],
            ['get', '/api/admin/event-types'],
        ];

        foreach ($sample as [$method, $url]) {
            $this->json($method, $url)
                ->assertStatus(401)
                ->assertJsonPath('type', 'unauthenticated');
        }

        $attendee = $this->attendee();
        foreach ($sample as [$method, $url]) {
            $this->actingAs($attendee)->json($method, $url)
                ->assertStatus(403)
                ->assertJsonPath('type', 'forbidden');
        }

        $admin = $this->admin();
        foreach ($sample as [$method, $url]) {
            $this->actingAs($admin)->json($method, $url)->assertOk();
        }
    }
}
