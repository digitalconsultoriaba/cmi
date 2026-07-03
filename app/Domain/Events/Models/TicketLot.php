<?php

namespace App\Domain\Events\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TicketLot extends BaseModel
{
    protected $casts = [
        'price_override' => 'decimal:2',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function ticketType(): BelongsTo
    {
        return $this->belongsTo(TicketType::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    // ── Derivações (contracts/domain-derivations.md) ────────────────

    public function soldOut(): bool
    {
        return $this->quantity !== null && $this->sold_count >= $this->quantity;
    }

    /** É o lote vigente do seu escopo (tipo específico ou global)? */
    public function isCurrent(): bool
    {
        return $this->event->currentLot($this->ticketType)?->is($this) ?? false;
    }

    /** Preço efetivo = price_override ?? preço do tipo (o do lote ou o informado). */
    public function effectivePrice(?TicketType $ticketType = null): ?string
    {
        return $this->price_override ?? ($ticketType ?? $this->ticketType)?->price;
    }

    /**
     * Recalcula o cache sold_count a partir da fonte de verdade (tickets que
     * contam vaga). Specs 004+ DEVEM chamar dentro da mesma DB::transaction.
     */
    public function recountSold(): int
    {
        $count = $this->tickets()
            ->whereIn('status_id', TicketStatus::idsFor(TicketStatus::COUNTS_CAPACITY))
            ->count();

        $this->forceFill(['sold_count' => $count])->save();

        return $count;
    }
}
