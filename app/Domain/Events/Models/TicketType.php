<?php

namespace App\Domain\Events\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TicketType extends BaseModel
{
    protected $casts = [
        'price' => 'decimal:2',
        'is_couple' => 'boolean',
        'includes_shirt' => 'boolean',
        'includes_kit' => 'boolean',
        'is_courtesy' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function lots(): HasMany
    {
        return $this->hasMany(TicketLot::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    // ── Derivações ──────────────────────────────────────────────────

    /** Vagas restantes do tipo; null = sem capacidade própria. */
    public function available(): ?int
    {
        if ($this->capacity === null) {
            return null;
        }

        $sold = $this->tickets()
            ->whereIn('status_id', TicketStatus::idsFor(TicketStatus::COUNTS_CAPACITY))
            ->count();

        return $this->capacity - $sold;
    }

    public function soldOut(): bool
    {
        $available = $this->available();

        return $available !== null && $available <= 0;
    }

    /** Vendas registradas (tickets vivos) — bloqueia exclusão (spec 003). */
    public function hasSales(): bool
    {
        return $this->tickets()
            ->whereIn('status_id', TicketStatus::idsFor(TicketStatus::COUNTS_CAPACITY))
            ->exists();
    }

    public function soldCount(): int
    {
        return $this->tickets()
            ->whereIn('status_id', TicketStatus::idsFor(TicketStatus::COUNTS_CAPACITY))
            ->count();
    }
}
