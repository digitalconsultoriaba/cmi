<?php

namespace App\Domain\Events\Payments;

use Illuminate\Support\Carbon;

final readonly class ChargeStatus
{
    public const PAID = 'paid';
    public const PENDING = 'pending';
    public const EXPIRED = 'expired';
    public const CANCELLED = 'cancelled';

    public function __construct(
        public string $state,
        public ?string $paidAmount = null,
        public ?Carbon $paidAt = null,
        public array $raw = [],
    ) {
    }

    public function isPaid(): bool
    {
        return $this->state === self::PAID;
    }
}
