<?php

namespace Tests\Feature\Site;

use App\Domain\Events\Models\Event;
use App\Domain\Events\Models\EventSite;
use App\Domain\Events\Models\SiteSectionType;

/**
 * US1 — criação sob demanda, config e publicação do Site (quickstart §Fluxo 1).
 */
class SiteConfigTest extends SiteTestCase
{
    public function test_get_cria_site_sob_demanda_com_todas_as_secoes(): void
    {
        $admin = $this->admin();
        $event = $this->publishedEvent();

        $site = $this->ensureSite($admin, $event);

        $this->assertNotEmpty($site['slug']);
        $this->assertFalse($site['isPublished']);
        $this->assertSame(count(SiteSectionType::all()), count($site['sections']));
        $this->assertDatabaseHas('event_sites', ['event_id' => $event->id]);

        // Idempotente: segundo GET não duplica.
        $this->ensureSite($admin, $event);
        $this->assertSame(1, EventSite::query()->where('event_id', $event->id)->count());
    }

    public function test_atualiza_config_slug_data_tema_e_idiomas(): void
    {
        $admin = $this->admin();
        $event = $this->publishedEvent();
        $this->ensureSite($admin, $event);

        $this->actingAs($admin)->putJson("/api/admin/events/{$event->id}/site", [
            'slug' => 'congresso-2026',
            'theme' => ['accent' => '#FFD700'],
            'countdownAt' => '2026-09-18T17:00:00Z',
            'activeLanguages' => ['pt', 'en'],
            'seo' => ['title' => ['pt' => 'Congresso']],
        ])->assertOk()
            ->assertJsonPath('data.slug', 'congresso-2026')
            ->assertJsonPath('data.theme.accent', '#FFD700')
            ->assertJsonPath('data.activeLanguages', ['pt', 'en']);
    }

    public function test_slug_duplicado_recusa(): void
    {
        $admin = $this->admin();
        $a = $this->publishedEvent();
        $b = $this->publishedEvent();
        $this->ensureSite($admin, $a);
        $this->ensureSite($admin, $b);

        $this->actingAs($admin)->putJson("/api/admin/events/{$a->id}/site", ['slug' => 'meu-site'])->assertOk();

        $this->actingAs($admin)->putJson("/api/admin/events/{$b->id}/site", ['slug' => 'meu-site'])
            ->assertUnprocessable()->assertJsonValidationErrors(['slug']);
    }

    public function test_activeLanguages_sempre_contem_o_base(): void
    {
        $admin = $this->admin();
        $event = $this->publishedEvent();
        $this->ensureSite($admin, $event);

        $this->actingAs($admin)->putJson("/api/admin/events/{$event->id}/site", [
            'activeLanguages' => ['en'],
        ])->assertOk()->assertJsonPath('data.activeLanguages', ['pt', 'en']);
    }

    public function test_publish_e_unpublish_sao_idempotentes(): void
    {
        $admin = $this->admin();
        $event = $this->publishedEvent();
        $this->ensureSite($admin, $event);

        $this->publishSite($admin, $event);
        $this->publishSite($admin, $event); // 2ª vez não quebra

        $this->actingAs($admin)->postJson("/api/admin/events/{$event->id}/site/unpublish")
            ->assertOk()->assertJsonPath('data.isPublished', false);
    }
}
