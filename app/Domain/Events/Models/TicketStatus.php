<?php

namespace App\Domain\Events\Models;

class TicketStatus extends BaseLookupModel
{
    public const RESERVED = 'reserved';
    public const AWAITING_PAYMENT = 'awaiting_payment';
    public const PAID = 'paid';
    public const CONFIRMED = 'confirmed';
    public const COURTESY = 'courtesy';
    public const CANCELLED = 'cancelled';
    public const REFUNDED = 'refunded';
    public const TRANSFERRED = 'transferred';
    public const USED = 'used';

    public const ALL = [
        self::RESERVED, self::AWAITING_PAYMENT, self::PAID, self::CONFIRMED,
        self::COURTESY, self::CANCELLED, self::REFUNDED, self::TRANSFERRED, self::USED,
    ];

    /** Tickets "vivos" (aguardam/valem participação). */
    public const LIVE = [
        self::RESERVED, self::AWAITING_PAYMENT, self::PAID, self::CONFIRMED, self::COURTESY,
    ];

    /**
     * Contam vaga/lote/estoque: vivos + used (a vaga foi de fato consumida) —
     * contracts/domain-derivations.md.
     */
    public const COUNTS_CAPACITY = [...self::LIVE, self::USED];

    /** Confirmados para previsto×confirmado (consumo na spec 008). */
    public const CONFIRMED_SET = [self::PAID, self::CONFIRMED, self::COURTESY, self::USED];

    /** Situações terminais: rejeitam transição (409). */
    public const TERMINAL = [self::CANCELLED, self::REFUNDED, self::TRANSFERRED, self::USED];
}
