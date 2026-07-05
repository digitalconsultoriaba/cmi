<?php

namespace App\Domain\Events\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Cenário what-if do orçamento (spec 011): conservative|realistic|optimistic.
 */
class BudgetScenario extends BaseModel
{
    public const KEYS = ['conservative', 'realistic', 'optimistic'];

    protected $casts = [
        'avg_ticket' => 'decimal:2',
        'sponsorship' => 'decimal:2',
        'cost' => 'decimal:2',
        'other_revenue' => 'decimal:2',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(BudgetPlan::class, 'budget_plan_id');
    }

    /** Receita do cenário (ingressos + patrocínio + outras). */
    public function revenue(): float
    {
        return (int) $this->paying * (float) $this->avg_ticket
            + (float) $this->sponsorship + (float) $this->other_revenue;
    }

    /** O cenário fecha o orçamento? (derivado) */
    public function closesBudget(): bool
    {
        return $this->revenue() >= (float) $this->cost;
    }
}
