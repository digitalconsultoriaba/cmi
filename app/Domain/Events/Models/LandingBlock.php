<?php

namespace App\Domain\Events\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LandingBlock extends BaseModel
{
    public const TYPES = ['hero', 'text', 'schedule', 'speakers', 'faq', 'location', 'cta'];

    protected $casts = [
        'payload' => 'array',
        'is_active' => 'boolean',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}
