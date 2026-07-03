<?php

namespace App\Domain\Events\Models;

class PaymentStatus extends BaseLookupModel
{
    public const PENDING = 'pending';
    public const PAID = 'paid';
    public const FAILED = 'failed';
    public const EXPIRED = 'expired';
    public const REFUNDED = 'refunded';
    public const CHARGEBACK = 'chargeback';

    public const ALL = [
        self::PENDING, self::PAID, self::FAILED,
        self::EXPIRED, self::REFUNDED, self::CHARGEBACK,
    ];

    /** Situações terminais: rejeitam transição (409). */
    public const TERMINAL = [self::REFUNDED, self::CHARGEBACK];
}
