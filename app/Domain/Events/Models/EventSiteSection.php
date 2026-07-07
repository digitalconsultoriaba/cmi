<?php

namespace App\Domain\Events\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EventSiteSection extends BaseModel
{
    protected $casts = [
        'payload' => 'array',
        'is_active' => 'boolean',
    ];

    public function site(): BelongsTo
    {
        return $this->belongsTo(EventSite::class, 'event_site_id');
    }

    /** Itens de topo (sem pai), ordenados. */
    public function items(): HasMany
    {
        return $this->hasMany(EventSiteItem::class)
            ->whereNull('parent_item_id')
            ->orderBy('sort')->orderBy('id');
    }

    /** Todos os itens da seção (topo + filhos). */
    public function allItems(): HasMany
    {
        return $this->hasMany(EventSiteItem::class)->orderBy('sort')->orderBy('id');
    }

    public function isDynamic(): bool
    {
        return SiteSectionType::isDynamic($this->type);
    }
}
