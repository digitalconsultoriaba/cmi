<?php

namespace Tests\Feature\Site;

use App\Domain\Events\Models\SiteSectionType;
use App\Domain\Events\Services\Translation\TranslationProviderContract;

/**
 * US4 — multi-idioma: preenchimento automático, campo não traduzível, fallback.
 */
class SiteI18nTest extends SiteTestCase
{
    private function useFake(): void
    {
        $this->app->bind(TranslationProviderContract::class, TranslationProviderFake::class);
    }

    public function test_salvar_preenche_idiomas_ativos_via_provider(): void
    {
        $this->useFake();
        $admin = $this->admin();
        $event = $this->publishedEvent();
        $site = $this->ensureSite($admin, $event);

        $this->actingAs($admin)->putJson("/api/admin/events/{$event->id}/site", [
            'activeLanguages' => ['pt', 'en', 'es'],
        ])->assertOk();

        $heroId = $this->sectionId($site, SiteSectionType::HERO);
        $resp = $this->actingAs($admin)->putJson("/api/admin/events/{$event->id}/site/sections/{$heroId}", [
            'payload' => ['titleLine1' => ['pt' => 'Bem-vindos']],
        ])->assertOk();

        $resp->assertJsonPath('data.payload.titleLine1.en', '[en] Bem-vindos');
        $resp->assertJsonPath('data.payload.titleLine1.es', '[es] Bem-vindos');
        $resp->assertJsonPath('data.payload.titleLine1.pt', 'Bem-vindos');
    }

    public function test_campo_nao_traduzivel_permanece_igual(): void
    {
        $this->useFake();
        $admin = $this->admin();
        $event = $this->publishedEvent();
        $site = $this->ensureSite($admin, $event);
        $this->actingAs($admin)->putJson("/api/admin/events/{$event->id}/site", ['activeLanguages' => ['pt', 'en']])->assertOk();

        $speakers = $this->sectionId($site, SiteSectionType::SPEAKERS);
        // name é escalar (não locale-map) → não traduzido.
        $resp = $this->actingAs($admin)->postJson(
            "/api/admin/events/{$event->id}/site/sections/{$speakers}/items",
            ['payload' => ['name' => 'Ada Lovelace', 'talk' => ['pt' => 'Algoritmos']]]
        )->assertCreated();

        $resp->assertJsonPath('data.payload.name', 'Ada Lovelace');
        $resp->assertJsonPath('data.payload.talk.en', '[en] Algoritmos');
    }

    public function test_provider_indisponivel_nao_quebra_o_save(): void
    {
        $this->app->bind(TranslationProviderContract::class, TranslationProviderBroken::class);
        $admin = $this->admin();
        $event = $this->publishedEvent();
        $site = $this->ensureSite($admin, $event);
        $this->actingAs($admin)->putJson("/api/admin/events/{$event->id}/site", ['activeLanguages' => ['pt', 'en']])->assertOk();

        $heroId = $this->sectionId($site, SiteSectionType::HERO);
        $this->actingAs($admin)->putJson("/api/admin/events/{$event->id}/site/sections/{$heroId}", [
            'payload' => ['titleLine1' => ['pt' => 'Olá']],
        ])->assertOk()->assertJsonPath('data.payload.titleLine1.en', '');
    }

    public function test_landing_resolve_idioma_traduzido(): void
    {
        $this->useFake();
        $admin = $this->admin();
        $event = $this->publishedEvent();
        $site = $this->ensureSite($admin, $event);
        $this->actingAs($admin)->putJson("/api/admin/events/{$event->id}/site", [
            'activeLanguages' => ['pt', 'en'],
        ])->assertOk();

        $heroId = $this->sectionId($site, SiteSectionType::HERO);
        $this->actingAs($admin)->putJson("/api/admin/events/{$event->id}/site/sections/{$heroId}", [
            'payload' => ['titleLine1' => ['pt' => 'Bem-vindos']],
        ])->assertOk();
        $this->publishSite($admin, $event);

        // EN: traduzido no save.
        $en = $this->getJson("/api/public/sites/{$site['slug']}?lang=en")->assertOk();
        $hero = collect($en->json('data.sections'))->firstWhere('type', SiteSectionType::HERO);
        $this->assertSame('[en] Bem-vindos', $hero['payload']['titleLine1']);

        // PT: base.
        $pt = $this->getJson("/api/public/sites/{$site['slug']}?lang=pt")->assertOk();
        $heroPt = collect($pt->json('data.sections'))->firstWhere('type', SiteSectionType::HERO);
        $this->assertSame('Bem-vindos', $heroPt['payload']['titleLine1']);
    }

    public function test_landing_cai_no_pt_quando_sem_traducao(): void
    {
        // Provider quebrado → EN fica vazio no save; leitura cai no PT.
        $this->app->bind(TranslationProviderContract::class, TranslationProviderBroken::class);
        $admin = $this->admin();
        $event = $this->publishedEvent();
        $site = $this->ensureSite($admin, $event);
        $this->actingAs($admin)->putJson("/api/admin/events/{$event->id}/site", [
            'activeLanguages' => ['pt', 'en'],
            'seo' => ['title' => ['pt' => 'Congresso']],
        ])->assertOk();

        $heroId = $this->sectionId($site, SiteSectionType::HERO);
        $this->actingAs($admin)->putJson("/api/admin/events/{$event->id}/site/sections/{$heroId}", [
            'payload' => ['titleLine1' => ['pt' => 'Bem-vindos']],
        ])->assertOk();
        $this->publishSite($admin, $event);

        $en = $this->getJson("/api/public/sites/{$site['slug']}?lang=en")->assertOk();
        $hero = collect($en->json('data.sections'))->firstWhere('type', SiteSectionType::HERO);
        $this->assertSame('Bem-vindos', $hero['payload']['titleLine1']); // fallback pt
        $this->assertSame('Congresso', $en->json('data.seo.title')); // seo en vazio → pt
    }

    public function test_idioma_nao_ativo_cai_no_base(): void
    {
        $admin = $this->admin();
        $event = $this->publishedEvent();
        $site = $this->ensureSite($admin, $event); // só pt ativo
        $this->publishSite($admin, $event);

        $this->getJson("/api/public/sites/{$site['slug']}?lang=es")
            ->assertOk()->assertJsonPath('data.lang', 'pt');
    }
}
