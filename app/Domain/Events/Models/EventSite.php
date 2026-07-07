<?php

namespace App\Domain\Events\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Site do evento (spec 013). Publicação e visibilidade pública são derivadas
 * (constituição II) — não há coluna "público".
 */
class EventSite extends BaseModel
{
    protected $casts = [
        'theme' => 'array',
        'identity' => 'array',
        'seo' => 'array',
        'active_languages' => 'array',
        'countdown_at' => 'datetime',
        'published_at' => 'datetime',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function sections(): HasMany
    {
        return $this->hasMany(EventSiteSection::class)->orderBy('sort')->orderBy('id');
    }

    public function publishedBy(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'published_by');
    }

    // ── Derivações (nunca colunas) ──────────────────────────────────

    public function isPublished(): bool
    {
        return $this->published_at !== null;
    }

    /** Landing só aparece se publicada E o evento visível e publicado. */
    public function isPubliclyVisible(): bool
    {
        if (! $this->isPublished()) {
            return false;
        }

        $event = $this->event;

        return $event !== null
            && $event->visible_on_site
            && $event->status?->slug === EventStatus::PUBLISHED;
    }

    /** Idiomas ativos, com o base sempre presente. */
    public function activeLanguages(): array
    {
        $base = (string) config('site.base_locale', 'pt');
        $langs = $this->active_languages ?: [$base];

        if (! in_array($base, $langs, true)) {
            array_unshift($langs, $base);
        }

        return array_values($langs);
    }

    // ── Ações auditadas ─────────────────────────────────────────────

    public function publish(): void
    {
        if ($this->published_at === null) {
            $this->published_at = now();
            $this->published_by = auth()->id();
            $this->save();
        }
    }

    public function unpublish(): void
    {
        if ($this->published_at !== null) {
            $this->published_at = null;
            $this->published_by = null;
            $this->save();
        }
    }
}
