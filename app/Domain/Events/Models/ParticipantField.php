<?php

namespace App\Domain\Events\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ParticipantField extends BaseModel
{
    public const TYPES = ['text', 'affiliation', 'country', 'city', 'conditional'];

    protected $casts = [
        'required' => 'boolean',
        'config' => 'array',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(ParticipantCategory::class, 'participant_category_id');
    }
}
