<?php

namespace App\Domain\Events\Models;

class OrderStatus extends BaseLookupModel
{
    public const PENDING = 'pending';
    public const PAID = 'paid';
    public const PARTIALLY_PAID = 'partially_paid';
    public const CANCELLED = 'cancelled';
    public const EXPIRED = 'expired';
    public const REFUNDED = 'refunded';

    public const ALL = [
        self::PENDING, self::PAID, self::PARTIALLY_PAID,
        self::CANCELLED, self::EXPIRED, self::REFUNDED,
    ];

    /** Situações terminais: rejeitam transição (409). */
    public const TERMINAL = [self::CANCELLED, self::EXPIRED, self::REFUNDED];
}
