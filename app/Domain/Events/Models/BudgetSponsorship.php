<?php

namespace App\Domain\Events\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Cota de patrocínio PREVISTA (spec 011). `lost`/`cancelled` ficam fora do
 * previsto total e não podem gerar conta a receber.
 */
class BudgetSponsorship extends BaseModel
{
    protected $casts = [
        'unit_value' => 'decimal:2',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(BudgetPlan::class, 'budget_plan_id');
    }

    public function financialEntry(): BelongsTo
    {
        return $this->belongsTo(FinancialEntry::class, 'financial_entry_id');
    }

    /** Receita prevista da cota (derivada). */
    public function expectedRevenue(): string
    {
        return number_format((float) $this->unit_value * (int) $this->quantity, 2, '.', '');
    }

    public function isExcluded(): bool
    {
        return in_array($this->status, BudgetSponsorshipStatus::EXCLUDED, true);
    }

    public function isConfirmed(): bool
    {
        return in_array($this->status, BudgetSponsorshipStatus::CONFIRMED_SET, true);
    }
}
