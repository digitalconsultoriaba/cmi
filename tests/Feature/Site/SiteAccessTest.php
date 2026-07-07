<?php

namespace Tests\Feature\Site;

/**
 * US1 — RBAC: gestão do Site é admin/treasury; landing pública é aberta.
 */
class SiteAccessTest extends SiteTestCase
{
    public function test_gate_nao_acessa_admin_do_site(): void
    {
        $event = $this->publishedEvent();

        $this->actingAs($this->gate())
            ->getJson("/api/admin/events/{$event->id}/site")
            ->assertForbidden();
    }

    public function test_attendee_nao_acessa_admin_do_site(): void
    {
        $event = $this->publishedEvent();

        $this->actingAs($this->attendee())
            ->getJson("/api/admin/events/{$event->id}/site")
            ->assertForbidden();
    }

    public function test_landing_publica_aberta_sem_auth(): void
    {
        $admin = $this->admin();
        $event = $this->publishedEvent();
        $site = $this->ensureSite($admin, $event);
        $this->publishSite($admin, $event);

        // Sem actingAs → visitante anônimo.
        $this->getJson("/api/public/sites/{$site['slug']}")->assertOk();
    }
}
