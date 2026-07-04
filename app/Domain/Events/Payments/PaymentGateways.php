<?php

namespace App\Domain\Events\Payments;

use InvalidArgumentException;

/**
 * Resolve o driver por meio de pagamento (config) e por provider gravado no
 * payment (webhooks/reconciliação).
 */
class PaymentGateways
{
    public function pix(): PaymentGatewayContract
    {
        return match (config('payments.pix_driver')) {
            'sicoob' => app(SicoobGateway::class),
            'fake' => app(FakePixGateway::class),
            default => throw new InvalidArgumentException('pix_driver inválido: '.config('payments.pix_driver')),
        };
    }

    public function card(): PaymentGatewayContract
    {
        return match (config('payments.card_driver')) {
            'fake' => app(FakeCardGateway::class),
            default => throw new InvalidArgumentException('card_driver inválido: '.config('payments.card_driver')),
        };
    }

    /** Para reconsulta a partir de um payment existente. */
    public function forProvider(string $provider): PaymentGatewayContract
    {
        return match ($provider) {
            'sicoob' => $this->pix(),
            'card_gateway' => $this->card(),
            default => throw new InvalidArgumentException("Provider desconhecido: $provider"),
        };
    }
}
