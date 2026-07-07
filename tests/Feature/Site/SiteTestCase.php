<?php

namespace Tests\Feature\Site;

use App\Domain\Events\Models\Event;
use App\Domain\Events\Models\EventStatus;
use App\Domain\Events\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

abstract class SiteTestCase extends TestCase
{
    use RefreshDatabase;

    protected function admin(): User
    {
        $u = User::factory()->create();
        $u->assignRole(Role::ADMIN);

        return $u;
    }

    protected function gate(): User
    {
        $u = User::factory()->create();
        $u->assignRole(Role::GATE);

        return $u;
    }

    protected function attendee(): User
    {
        $u = User::factory()->create();
        $u->assignRole(Role::ATTENDEE);

        return $u;
    }

    /** Evento publicado e visível (landing pode aparecer quando o site publicar). */
    protected function publishedEvent(array $attrs = []): Event
    {
        return Event::factory()->published()->create($attrs + ['visible_on_site' => true]);
    }

    /** Garante o site (via API) e devolve o payload de /site do admin. */
    protected function ensureSite(User $admin, Event $event): array
    {
        return $this->actingAs($admin)
            ->getJson("/api/admin/events/{$event->id}/site")
            ->assertOk()->json('data');
    }

    /** Publica o site do evento. */
    protected function publishSite(User $admin, Event $event): void
    {
        $this->actingAs($admin)
            ->postJson("/api/admin/events/{$event->id}/site/publish")
            ->assertOk()->assertJsonPath('data.isPublished', true);
    }

    /** Id da seção de um tipo (a partir do payload de /site). */
    protected function sectionId(array $site, string $type): int
    {
        foreach ($site['sections'] as $s) {
            if ($s['type'] === $type) {
                return $s['id'];
            }
        }
        $this->fail("Seção $type não encontrada no site.");
    }

    protected function draftStatusId(): int
    {
        return EventStatus::idFor(EventStatus::DRAFT);
    }
}
