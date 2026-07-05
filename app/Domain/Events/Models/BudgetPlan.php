<?php

namespace App\Domain\Events\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Cabeçalho do orçamento do evento (spec 011) — 1:1 com o evento.
 * Todos os totais são DERIVADOS (BudgetCalculator), nunca colunas.
 */
class BudgetPlan extends BaseModel
{
    protected $casts = [
        'other_revenue' => 'decimal:2',
        'safety_margin_pct' => 'decimal:2',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function costItems(): HasMany
    {
        return $this->hasMany(BudgetCostItem::class);
    }

    public function ticketLots(): HasMany
    {
        return $this->hasMany(BudgetTicketLot::class);
    }

    public function sponsorships(): HasMany
    {
        return $this->hasMany(BudgetSponsorship::class);
    }

    public function scenarios(): HasMany
    {
        return $this->hasMany(BudgetScenario::class);
    }

    /** Total geral de participantes previstos (derivado). */
    public function totalParticipants(): int
    {
        return (int) ($this->expected_paying + $this->expected_courtesy
            + $this->expected_guests + $this->expected_staff + $this->expected_speakers);
    }
}
