<?php

namespace App\Domain\Events\Services;

use Illuminate\Support\Carbon;

/**
 * Evidência estruturada de toda baixa — mesma shape para webhook,
 * reconciliação, gateway síncrono e baixa manual (trilha do princípio V).
 */
final readonly class PaymentEvidence
{
    public const WEBHOOK = 'webhook';
    public const RECONCILIATION = 'reconciliation';
    public const GATEWAY = 'gateway';
    public const MANUAL = 'manual';

    public function __construct(
        public string $source,
        public array $raw = [],
        public ?string $paidAmount = null,
        public ?Carbon $paidAt = null,
        public ?int $actorId = null,
        public ?string $note = null,
        // Metadados do cartão vindos da confirmação (bandeira, final, parcelas).
        public ?string $cardBrand = null,
        public ?string $cardLast4 = null,
        public ?int $installments = null,
    ) {
    }
}
