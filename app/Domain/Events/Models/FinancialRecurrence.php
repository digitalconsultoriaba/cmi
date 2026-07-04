<?php

namespace App\Domain\Events\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinancialRecurrence extends BaseModel
{
    protected $casts = [
        'starts_on' => 'date', 'ends_on' => 'date',
        'last_generated_on' => 'date', 'is_active' => 'boolean',
        'amount' => 'decimal:2',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(FinancialCategory::class, 'category_id');
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'event_id');
    }
}
