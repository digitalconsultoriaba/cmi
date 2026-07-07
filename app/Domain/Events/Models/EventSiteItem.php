<?php

namespace App\Domain\Events\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EventSiteItem extends BaseModel
{
    protected $casts = [
        'payload' => 'array',
    ];

    public function section(): BelongsTo
    {
        return $this->belongsTo(EventSiteSection::class, 'event_site_section_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(EventSiteItem::class, 'parent_item_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(EventSiteItem::class, 'parent_item_id')
            ->orderBy('sort')->orderBy('id');
    }
}
