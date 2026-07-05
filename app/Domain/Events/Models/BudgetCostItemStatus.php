<?php

namespace App\Domain\Events\Models;

/**
 * Status do item de custo previsto (spec 011). Apenas `cancelled` afeta o
 * cálculo (fica fora do custo total previsto); o resto é classificação livre.
 */
class BudgetCostItemStatus
{
    public const PLANNED = 'planned';
    public const QUOTED = 'quoted';
    public const APPROVED = 'approved';
    public const CONTRACTED = 'contracted';
    public const CANCELLED = 'cancelled';

    public const ALL = [
        self::PLANNED, self::QUOTED, self::APPROVED, self::CONTRACTED, self::CANCELLED,
    ];
}
