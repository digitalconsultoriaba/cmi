<?php

namespace App\Domain\Events\Payments;

/**
 * Resultado da criação de um checkout hospedado (redirect): o comprador é
 * enviado à página do provedor e a baixa chega depois por webhook.
 */
final readonly class HostedCheckout
{
    public function __construct(
        public string $checkoutId,
        public string $redirectUrl,
        public array $raw = [],
    ) {
    }
}
