<?php

namespace App\Domain\Events\Models;

/**
 * Status da cota de patrocínio prevista (spec 011).
 * - `lost`/`cancelled` ficam fora do previsto total e não podem gerar conta a receber.
 * - `confirmed`/`received` compõem o patrocínio confirmado.
 */
class BudgetSponsorshipStatus
{
    public const PLANNED = 'planned';
    public const NEGOTIATING = 'negotiating';
    public const CONFIRMED = 'confirmed';
    public const RECEIVED = 'received';
    public const LOST = 'lost';
    public const CANCELLED = 'cancelled';

    public const ALL = [
        self::PLANNED, self::NEGOTIATING, self::CONFIRMED,
        self::RECEIVED, self::LOST, self::CANCELLED,
    ];

    /** Não entram no previsto total nem podem virar conta a receber. */
    public const EXCLUDED = [self::LOST, self::CANCELLED];

    /** Compõem o patrocínio confirmado. */
    public const CONFIRMED_SET = [self::CONFIRMED, self::RECEIVED];
}
