<?php

namespace App\Domain\Events\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventShirtSize extends BaseModel
{
    protected $casts = ['is_active' => 'boolean'];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function shirtModel(): BelongsTo
    {
        return $this->belongsTo(EventShirtModel::class, 'shirt_model_id');
    }

    // ── Derivações (contracts/domain-derivations.md) ────────────────

    /** Esgotada só quando há estoque definido; null = ilimitado, nunca esgota. */
    public function soldOut(): bool
    {
        return $this->stock_quantity !== null && $this->sold_count >= $this->stock_quantity;
    }

    /**
     * Recalcula o cache sold_count: camisas do titular + do acompanhante (casal).
     * Specs 004+ DEVEM chamar dentro da mesma DB::transaction.
     */
    public function recountSold(): int
    {
        $statusIds = TicketStatus::idsFor(TicketStatus::COUNTS_CAPACITY);

        $count = Ticket::query()
            ->whereIn('status_id', $statusIds)
            ->where(fn ($q) => $q->where('shirt_size_id', $this->id)
                ->orWhere('companion_shirt_size_id', $this->id))
            ->count();

        $this->forceFill(['sold_count' => $count])->save();

        return $count;
    }
}
