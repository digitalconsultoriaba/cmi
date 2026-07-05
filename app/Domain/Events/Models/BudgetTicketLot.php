<?php

namespace App\Domain\Events\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Lote de ingresso PREVISTO (spec 011) — planejamento, distinto do lote real.
 */
class BudgetTicketLot extends BaseModel
{
    protected $casts = [
        'unit_price' => 'decimal:2',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(BudgetPlan::class, 'budget_plan_id');
    }

    /** Receita prevista do lote (derivada). */
    public function expectedRevenue(): string
    {
        return number_format((float) $this->unit_price * (int) $this->expected_quantity, 2, '.', '');
    }
}
