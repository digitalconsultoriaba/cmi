<?php

namespace App\Domain\Events\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EventShirtModel extends BaseModel
{
    protected $casts = ['is_active' => 'boolean'];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function sizes(): HasMany
    {
        return $this->hasMany(EventShirtSize::class, 'shirt_model_id');
    }
}
