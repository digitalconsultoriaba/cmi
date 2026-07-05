<?php

namespace App\Domain\Events\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Item de custo previsto (spec 011). Regra de valor: se quantidade+unitário,
 * total = qtd × unitário; senão usa o total informado. `cancelled` fica fora
 * do custo total previsto.
 */
class BudgetCostItem extends BaseModel
{
    protected $casts = [
        'quantity' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'total_amount' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        // Deriva o total quando quantidade e unitário estão presentes.
        static::saving(function (BudgetCostItem $item) {
            if ($item->quantity !== null && $item->unit_price !== null) {
                $item->total_amount = number_format(
                    (float) $item->quantity * (float) $item->unit_price, 2, '.', ''
                );
            }
        });
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(BudgetPlan::class, 'budget_plan_id');
    }

    public function financialEntry(): BelongsTo
    {
        return $this->belongsTo(FinancialEntry::class, 'financial_entry_id');
    }

    public function isCancelled(): bool
    {
        return $this->status === BudgetCostItemStatus::CANCELLED;
    }
}
