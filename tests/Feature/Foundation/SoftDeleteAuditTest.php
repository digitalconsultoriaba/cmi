<?php

namespace Tests\Feature\Foundation;

use App\Domain\Events\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * US2 — histórico: soft delete reversível + trilha created_by/updated_by
 * (constituição, princípio V).
 */
class SoftDeleteAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_exclusao_e_reversivel_e_trilha_de_auditoria_e_preenchida(): void
    {
        $author = User::factory()->create();
        $editor = User::factory()->create();

        $this->actingAs($author);
        $event = Event::factory()->create();

        $this->assertSame($author->id, $event->created_by);
        $this->assertSame($author->id, $event->updated_by);

        $this->actingAs($editor);
        $event->update(['name' => 'Nome revisado']);

        $this->assertSame($author->id, $event->fresh()->created_by, 'created_by não muda em update');
        $this->assertSame($editor->id, $event->fresh()->updated_by);

        $event->delete();

        $this->assertSoftDeleted('events', ['id' => $event->id]);
        $this->assertNull(Event::query()->find($event->id), 'consulta padrão não lista soft-deleted');

        $trashed = Event::withTrashed()->find($event->id);
        $this->assertNotNull($trashed, 'registro permanece recuperável');

        $trashed->restore();
        $this->assertNotNull(Event::query()->find($event->id));
    }
}
