<?php

namespace App\Domain\Events\Payments;

final readonly class CardResult
{
    public function __construct(
        public bool $approved,
        public ?string $externalId = null,
        public ?string $brand = null,
        public ?string $last4 = null,
        public ?string $declineReason = null,
        public array $raw = [],
    ) {
    }
}
