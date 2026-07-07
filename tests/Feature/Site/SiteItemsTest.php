<?php

namespace Tests\Feature\Site;

use App\Domain\Events\Models\EventSiteItem;
use App\Domain\Events\Models\SiteSectionType;

/**
 * US3 — itens das seções dinâmicas: CRUD, reordenação e aninhamento.
 */
class SiteItemsTest extends SiteTestCase
{
    private function speakersSection(): array
    {
        $admin = $this->admin();
        $event = $this->publishedEvent();
        $site = $this->ensureSite($admin, $event);

        return [$admin, $event, $site, $this->sectionId($site, SiteSectionType::SPEAKERS)];
    }

    public function test_cria_edita_e_remove_itens(): void
    {
        [$admin, $event, , $sectionId] = $this->speakersSection();
        $base = "/api/admin/events/{$event->id}/site/sections/{$sectionId}/items";

        $id = $this->actingAs($admin)->postJson($base, [
            'payload' => ['name' => 'Fulano', 'talk' => ['pt' => 'Abertura']],
        ])->assertCreated()->json('data.id');

        $this->actingAs($admin)->putJson("$base/$id", [
            'payload' => ['name' => 'Fulano de Tal', 'talk' => ['pt' => 'Keynote']],
        ])->assertOk()->assertJsonPath('data.payload.name', 'Fulano de Tal');

        $this->actingAs($admin)->deleteJson("$base/$id")->assertOk()->assertJsonPath('data.deleted', true);
        $this->assertSoftDeleted('event_site_items', ['id' => $id]);
    }

    public function test_reorder_recalcula_sort(): void
    {
        [$admin, $event, , $sectionId] = $this->speakersSection();
        $base = "/api/admin/events/{$event->id}/site/sections/{$sectionId}/items";

        $a = $this->actingAs($admin)->postJson($base, ['payload' => ['name' => 'A']])->json('data.id');
        $b = $this->actingAs($admin)->postJson($base, ['payload' => ['name' => 'B']])->json('data.id');
        $c = $this->actingAs($admin)->postJson($base, ['payload' => ['name' => 'C']])->json('data.id');

        $this->actingAs($admin)->patchJson("$base/reorder", ['order' => [$c, $a, $b]])->assertOk();

        $order = $this->actingAs($admin)->getJson($base)->json('data.*.id');
        $this->assertSame([$c, $a, $b], $order);
    }

    public function test_itens_aninhados_por_parent(): void
    {
        $admin = $this->admin();
        $event = $this->publishedEvent();
        $site = $this->ensureSite($admin, $event);
        $sectionId = $this->sectionId($site, SiteSectionType::PROGRAM);
        $base = "/api/admin/events/{$event->id}/site/sections/{$sectionId}/items";

        $dayId = $this->actingAs($admin)->postJson($base, [
            'payload' => ['label' => ['pt' => 'Dia 1'], 'date' => '2026-09-18'],
        ])->json('data.id');

        $entryId = $this->actingAs($admin)->postJson($base, [
            'parentItemId' => $dayId,
            'payload' => ['type' => 'talk', 'title' => ['pt' => 'Palestra']],
        ])->assertCreated()->json('data.id');

        $tree = $this->actingAs($admin)->getJson($base)->json('data');
        $this->assertCount(1, $tree);
        $this->assertSame($dayId, $tree[0]['id']);
        $this->assertSame($entryId, $tree[0]['children'][0]['id']);
    }

    public function test_parent_de_outra_secao_recusa(): void
    {
        $admin = $this->admin();
        $event = $this->publishedEvent();
        $site = $this->ensureSite($admin, $event);
        $speakers = $this->sectionId($site, SiteSectionType::SPEAKERS);
        $program = $this->sectionId($site, SiteSectionType::PROGRAM);

        $foreign = $this->actingAs($admin)->postJson(
            "/api/admin/events/{$event->id}/site/sections/{$program}/items",
            ['payload' => ['label' => ['pt' => 'Dia']]]
        )->json('data.id');

        $this->actingAs($admin)->postJson(
            "/api/admin/events/{$event->id}/site/sections/{$speakers}/items",
            ['parentItemId' => $foreign, 'payload' => ['name' => 'X']]
        )->assertStatus(409);
    }

    public function test_delete_soft_deleta_filhos(): void
    {
        $admin = $this->admin();
        $event = $this->publishedEvent();
        $site = $this->ensureSite($admin, $event);
        $sectionId = $this->sectionId($site, SiteSectionType::PROGRAM);
        $base = "/api/admin/events/{$event->id}/site/sections/{$sectionId}/items";

        $dayId = $this->actingAs($admin)->postJson($base, ['payload' => ['label' => ['pt' => 'Dia']]])->json('data.id');
        $entryId = $this->actingAs($admin)->postJson($base, ['parentItemId' => $dayId, 'payload' => ['type' => 'talk']])->json('data.id');

        $this->actingAs($admin)->deleteJson("$base/$dayId")->assertOk();

        $this->assertSoftDeleted('event_site_items', ['id' => $dayId]);
        $this->assertSoftDeleted('event_site_items', ['id' => $entryId]);
    }
}
