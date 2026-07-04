<?php

namespace App\Domain\Events\Payments;

final readonly class RefundResult
{
    public function __construct(
        public bool $refunded,
        public ?string $externalId = null,
        public array $raw = [],
    ) {
    }
}
