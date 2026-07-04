<?php

namespace App\Domain\Events\Payments;

use App\Domain\Events\Models\Order;
use App\Domain\Events\Models\Payment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * "Banco simulado" para dev/teste: cobranças vivem no cache (database no dev —
 * persiste entre processos; array nos testes — persiste dentro do teste).
 * `settle()` marca a cobrança como paga, simulando o pagamento no app do banco.
 */
class FakePixGateway implements PaymentGatewayContract
{
    private const CACHE_PREFIX = 'fakepix:';

    public function providerName(): string
    {
        return 'sicoob'; // o fake emula o Sicoob (webhooks/provider coerentes)
    }

    public function createPixCharge(Order $order): ChargeData
    {
        $externalId = 'fakepix-'.Str::lower(Str::random(20));
        $expiresAt = $order->reserved_until !== null
            ? Carbon::parse($order->reserved_until)
            : now()->addMinutes(30);

        $this->store($externalId, [
            'state' => ChargeStatus::PENDING,
            'amount' => $order->total_amount,
        ]);

        return new ChargeData(
            externalId: $externalId,
            pixCopiaECola: '00020126580014br.gov.bcb.pix0136'.$externalId.'520400005303986',
            expiresAt: $expiresAt,
            raw: ['fake' => true, 'txid' => $externalId],
        );
    }

    public function createBoletoCharge(Order $order): ChargeData
    {
        $externalId = 'fakeboleto-'.Str::lower(Str::random(20));

        $this->store($externalId, [
            'state' => ChargeStatus::PENDING,
            'amount' => $order->total_amount,
        ]);

        return new ChargeData(
            externalId: $externalId,
            pixCopiaECola: '00020126580014br.gov.bcb.pix0136'.$externalId.'520400005303986', // híbrido
            boletoLine: '75691.23456 01234.567890 12345.678901 1 '.now()->format('ymd').'0000012345',
            boletoBarcode: '75691'.str_pad((string) random_int(1, 999999999), 39, '0', STR_PAD_LEFT),
            boletoPdfUrl: null,
            expiresAt: $order->reserved_until !== null ? Carbon::parse($order->reserved_until) : now()->addDay(),
            raw: ['fake' => true, 'nossoNumero' => $externalId, 'hibrido' => true],
        );
    }

    public function chargeCard(Order $order, string $token, int $installments): CardResult
    {
        throw new \LogicException('FakePixGateway não processa cartão — use o card driver.');
    }

    public function getChargeStatus(Payment $payment): ChargeStatus
    {
        $charge = Cache::get(self::CACHE_PREFIX.$payment->provider_charge_id);

        if ($charge === null) {
            return new ChargeStatus(ChargeStatus::EXPIRED, raw: ['fake' => true, 'missing' => true]);
        }

        return new ChargeStatus(
            state: $charge['state'],
            paidAmount: $charge['paid_amount'] ?? null,
            paidAt: isset($charge['paid_at']) ? Carbon::parse($charge['paid_at']) : null,
            raw: ['fake' => true, 'charge' => $charge],
        );
    }

    public function cancelCharge(Payment $payment): void
    {
        $key = self::CACHE_PREFIX.$payment->provider_charge_id;
        $charge = Cache::get($key);

        if ($charge !== null && $charge['state'] === ChargeStatus::PENDING) {
            $charge['state'] = ChargeStatus::CANCELLED;
            Cache::put($key, $charge, now()->addDays(2));
        }
    }

    /** Simula o pagamento no banco (usado por testes e pelo smoke de dev). */
    public function settle(string $externalId, ?string $amount = null): void
    {
        $key = self::CACHE_PREFIX.$externalId;
        $charge = Cache::get($key) ?? ['state' => ChargeStatus::PENDING, 'amount' => $amount];

        $charge['state'] = ChargeStatus::PAID;
        $charge['paid_amount'] = $amount ?? $charge['amount'];
        $charge['paid_at'] = now()->toISOString();

        Cache::put($key, $charge, now()->addDays(2));
    }

    private function store(string $externalId, array $charge): void
    {
        Cache::put(self::CACHE_PREFIX.$externalId, $charge, now()->addDays(2));
    }
}
