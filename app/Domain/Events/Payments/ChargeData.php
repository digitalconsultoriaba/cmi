<?php

namespace App\Domain\Events\Payments;

use Illuminate\Support\Carbon;

final readonly class ChargeData
{
    public function __construct(
        public string $externalId,
        public ?string $pixCopiaECola = null,
        public ?string $boletoLine = null,
        public ?string $boletoBarcode = null,
        public ?string $boletoPdfUrl = null,
        public ?Carbon $expiresAt = null,
        public array $raw = [],
    ) {
    }
}
