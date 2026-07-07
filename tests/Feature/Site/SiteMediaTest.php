<?php

namespace Tests\Feature\Site;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * US2 — upload de mídia do Site.
 */
class SiteMediaTest extends SiteTestCase
{
    public function test_upload_valido_retorna_path_e_url(): void
    {
        Storage::fake('public');
        $admin = $this->admin();
        $event = $this->publishedEvent();

        $this->actingAs($admin)->postJson("/api/admin/events/{$event->id}/site/media", [
            'file' => UploadedFile::fake()->image('logo.png', 300, 300),
        ])->assertCreated()
            ->assertJsonPath('data.path', fn ($p) => str_starts_with($p, 'site/'))
            ->assertJsonStructure(['data' => ['path', 'url']]);
    }

    public function test_arquivo_invalido_recusa(): void
    {
        Storage::fake('public');
        $admin = $this->admin();
        $event = $this->publishedEvent();

        $this->actingAs($admin)->postJson("/api/admin/events/{$event->id}/site/media", [
            'file' => UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf'),
        ])->assertUnprocessable()->assertJsonValidationErrors(['file']);
    }

    public function test_papel_sem_permissao_recusa(): void
    {
        Storage::fake('public');
        $event = $this->publishedEvent();

        $this->actingAs($this->gate())->postJson("/api/admin/events/{$event->id}/site/media", [
            'file' => UploadedFile::fake()->image('x.png'),
        ])->assertForbidden();
    }
}
