<?php

namespace App\Domain\Events\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sponsorship extends BaseModel
{
    protected $casts = ['total_amount' => 'decimal:2'];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function installments(): HasMany
    {
        return $this->hasMany(SponsorshipInstallment::class);
    }
}
