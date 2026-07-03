<?php

namespace App\Domain\Events\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportCaseNote extends BaseAuditedModel
{
    protected $casts = [
        'visible_to_attendee' => 'boolean',
        'from_attendee' => 'boolean',
    ];

    public function supportCase(): BelongsTo
    {
        return $this->belongsTo(SupportCase::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_user_id');
    }
}
