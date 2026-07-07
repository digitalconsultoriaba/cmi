<?php

namespace Tests\Feature\Site;

use App\Domain\Events\Models\Event;

/**
 * US1 — visibilidade pública derivada (quickstart §Fluxo 1).
 */
class PublicSiteVisibilityTest extends SiteTestCase
{
    public function test_site_publicado_e_evento_visivel_aparece(): void
    {
        $admin = $this->admin();
        $event = $this->publishedEvent();
        $site = $this->ensureSite($admin, $event);
        $this->publishSite($admin, $event);

        $this->getJson("/api/public/sites/{$site['slug']}")
            ->assertOk()
            ->assertJsonPath('data.slug', $site['slug'])
            ->assertJsonPath('data.lang', 'pt');
    }

    public function test_rascunho_retorna_404(): void
    {
        $admin = $this->admin();
        $event = $this->publishedEvent();
        $site = $this->ensureSite($admin, $event); // não publica

        $this->getJson("/api/public/sites/{$site['slug']}")->assertNotFound();
    }

    public function test_evento_oculto_retorna_404_mesmo_publicado(): void
    {
        $admin = $this->admin();
        $event = $this->publishedEvent(['visible_on_site' => false]);
        $site = $this->ensureSite($admin, $event);
        $this->publishSite($admin, $event);

        $this->getJson("/api/public/sites/{$site['slug']}")->assertNotFound();
    }

    public function test_evento_rascunho_retorna_404(): void
    {
        $admin = $this->admin();
        $event = Event::factory()->create(['visible_on_site' => true]); // status draft
        $site = $this->ensureSite($admin, $event);
        $this->publishSite($admin, $event);

        $this->getJson("/api/public/sites/{$site['slug']}")->assertNotFound();
    }

    public function test_slug_inexistente_retorna_404(): void
    {
        $this->getJson('/api/public/sites/nao-existe')->assertNotFound();
    }
}
