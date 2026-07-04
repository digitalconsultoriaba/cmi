<?php

namespace App\Domain\Events\Payments;

use App\Domain\Events\Models\Order;
use App\Domain\Events\Models\Payment;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Gateway de cartão simulado: tok_ok_* aprova, tok_declined_* recusa.
 * Guarda anti-PAN: token que pareça número de cartão é rejeitado na porta —
 * o invariante "PAN nunca no backend" vale até no fake (constituição, IV).
 */
class FakeCardGateway implements PaymentGatewayContract
{
    private const CACHE_PREFIX = 'fakecard:';

    public function providerName(): string
    {
        return 'card_gateway';
    }

    public function createPixCharge(Order $order): ChargeData
    {
        throw new \LogicException('FakeCardGateway não emite Pix — use o pix driver.');
    }

    public function createBoletoCharge(Order $order): ChargeData
    {
        throw new \LogicException('FakeCardGateway não emite boleto — use o pix driver.');
    }

    public function chargeCard(Order $order, string $token, int $installments): CardResult
    {
        // Anti-PAN: 13+ dígitos seguidos = parece cartão → nunca processa
        if (preg_match('/\d{13,}/', preg_replace('/[\s-]/', '', $token))) {
            throw new InvalidArgumentException(
                'Token inválido: dados de cartão nunca devem chegar ao backend.'
            );
        }

        if (str_starts_with($token, 'tok_declined')) {
            return new CardResult(
                approved: false,
                declineReason: 'Transação recusada pelo emissor. Verifique os dados ou use outro cartão.',
                raw: ['fake' => true, 'token' => $token],
            );
        }

        if (! str_starts_with($token, 'tok_ok')) {
            return new CardResult(
                approved: false,
                declineReason: 'Token de pagamento inválido ou expirado.',
                raw: ['fake' => true],
            );
        }

        $externalId = 'fakecard-'.Str::lower(Str::random(20));

        Cache::put(self::CACHE_PREFIX.$externalId, [
            'state' => ChargeStatus::PAID,
            'amount' => $order->total_amount,
            'paid_at' => now()->toISOString(),
        ], now()->addDays(2));

        return new CardResult(
            approved: true,
            externalId: $externalId,
            brand: 'visa',
            last4: substr(preg_replace('/\D/', '', $token) ?: '4242', -4),
            raw: ['fake' => true, 'authorization' => Str::upper(Str::random(6))],
        );
    }

    public function getChargeStatus(Payment $payment): ChargeStatus
    {
        $charge = Cache::get(self::CACHE_PREFIX.$payment->provider_charge_id);

        if ($charge === null) {
            return new ChargeStatus(ChargeStatus::PENDING, raw: ['fake' => true]);
        }

        return new ChargeStatus(
            state: $charge['state'],
            paidAmount: $charge['amount'] ?? null,
            paidAt: isset($charge['paid_at']) ? \Illuminate\Support\Carbon::parse($charge['paid_at']) : null,
            raw: ['fake' => true, 'charge' => $charge],
        );
    }

    public function cancelCharge(Payment $payment): void
    {
        // Cartão síncrono: nada a cancelar no fake.
    }
}
