<?php

namespace Tests\Feature\Site;

use App\Domain\Events\Models\SiteSectionType;

/**
 * US2 — seções simples: editar payload, ativar/desativar, reordenar.
 */
class SiteSectionsTest extends SiteTestCase
{
    public function test_atualiza_payload_de_secao_simples(): void
    {
        $admin = $this->admin();
        $event = $this->publishedEvent();
        $site = $this->ensureSite($admin, $event);
        $heroId = $this->sectionId($site, SiteSectionType::HERO);

        $this->actingAs($admin)->putJson("/api/admin/events/{$event->id}/site/sections/{$heroId}", [
            'payload' => ['titleLine1' => ['pt' => 'Bem-vindos'], 'primaryHref' => '/inscricao'],
        ])->assertOk()
            ->assertJsonPath('data.payload.titleLine1.pt', 'Bem-vindos')
            ->assertJsonPath('data.payload.primaryHref', '/inscricao');
    }

    public function test_toggle_is_active_tira_secao_da_landing(): void
    {
        $admin = $this->admin();
        $event = $this->publishedEvent();
        $site = $this->ensureSite($admin, $event);
        $this->publishSite($admin, $event);
        $aboutId = $this->sectionId($site, SiteSectionType::ABOUT);

        $this->actingAs($admin)->putJson("/api/admin/events/{$event->id}/site/sections/{$aboutId}", [
            'payload' => ['aboutTitle' => ['pt' => 'Sobre']],
            'isActive' => false,
        ])->assertOk()->assertJsonPath('data.isActive', false);

        $types = collect($this->getJson("/api/public/sites/{$site['slug']}")->json('data.sections'))
            ->pluck('type')->all();
        $this->assertNotContains(SiteSectionType::ABOUT, $types);
    }

    public function test_reordena_secoes(): void
    {
        $admin = $this->admin();
        $event = $this->publishedEvent();
        $site = $this->ensureSite($admin, $event);

        $hero = $this->sectionId($site, SiteSectionType::HERO);
        $about = $this->sectionId($site, SiteSectionType::ABOUT);

        // Inverte a ordem relativa de hero/about.
        $this->actingAs($admin)->patchJson("/api/admin/events/{$event->id}/site/sections/reorder", [
            'order' => [$about, $hero],
        ])->assertOk();

        $sorts = collect($this->ensureSite($admin, $event)['sections'])
            ->keyBy('id');
        $this->assertLessThan($sorts[$hero]['sort'], $sorts[$about]['sort']);
    }

    public function test_secao_de_outro_evento_retorna_404(): void
    {
        $admin = $this->admin();
        $a = $this->publishedEvent();
        $b = $this->publishedEvent();
        $siteB = $this->ensureSite($admin, $b);
        $this->ensureSite($admin, $a);
        $heroB = $this->sectionId($siteB, SiteSectionType::HERO);

        $this->actingAs($admin)->putJson("/api/admin/events/{$a->id}/site/sections/{$heroB}", [
            'payload' => ['x' => 1],
        ])->assertNotFound();
    }
}
