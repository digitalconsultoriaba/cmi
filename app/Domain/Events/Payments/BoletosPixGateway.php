<?php

namespace App\Domain\Events\Payments;

use App\Domain\Events\Models\Order;
use App\Domain\Events\Models\Payment;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Driver PIX sobre o microsserviço Boletos SICOOB V2 (spec 015). O microsserviço
 * fala com o SICOOB; o cmi só cria a cobrança e reconsulta o status por txid.
 * `providerName` é 'sicoob' (o pagamento é Sicoob por baixo) — mantém o provider
 * gravado, o `forProvider` e a reconciliação sem mudança.
 */
class BoletosPixGateway implements PaymentGatewayContract
{
    public function __construct(private readonly BoletosPixClient $client)
    {
    }

    public function providerName(): string
    {
        return 'sicoob';
    }

    public function createPixCharge(Order $order): ChargeData
    {
        // Expira junto com a reserva do pedido (mín. 60s); senão o default.
        $expiracao = $order->reserved_until !== null
            ? max(60, (int) now()->diffInSeconds($order->reserved_until, false))
            : (int) config('payments.boletos.pix_expiration', 3600);

        $payload = [
            'valor' => (float) $order->total_amount,
            'expiracao' => $expiracao,
            'solicitacaoPagador' => 'Pedido '.$order->code,
        ];

        // Webhook (doc §5.1): pede o aviso de pagamento na nossa URL pública.
        // Só em produção — o microsserviço recusa domínio fora da allowlist (422);
        // sem isso, a baixa segue pelo polling/reconciliação.
        $notifyUrl = (string) config('payments.boletos.notify_url');
        if ($notifyUrl !== '') {
            $payload['notificationUrl'] = $notifyUrl;
        }

        $data = $this->client->createCobranca($payload);

        return new ChargeData(
            externalId: (string) $data['txid'],
            pixCopiaECola: $data['copiaECola'] ?? null,
            expiresAt: $order->reserved_until !== null
                ? Carbon::parse($order->reserved_until)
                : now()->addSeconds($expiracao),
            raw: $data,
        );
    }

    public function createBoletoCharge(Order $order): ChargeData
    {
        // Este driver expõe apenas PIX. Boleto sai por outro fluxo/serviço.
        throw new LogicException('BoletosPixGateway processa apenas PIX (sem boleto).');
    }

    public function chargeCard(Order $order, string $token, int $installments): CardResult
    {
        throw new LogicException('PIX não processa cartão — use o card driver (ASAAS).');
    }

    public function getChargeStatus(Payment $payment): ChargeStatus
    {
        $data = $this->client->getCobranca($payment->provider_charge_id);
        $status = (string) ($data['status'] ?? 'ativa');

        $state = match ($status) {
            'concluida' => ChargeStatus::PAID,
            'expirada' => ChargeStatus::EXPIRED,
            'removida' => ChargeStatus::CANCELLED,
            default => ChargeStatus::PENDING,
        };

        $paidAmount = isset($data['valor']) && $state === ChargeStatus::PAID
            ? number_format((float) $data['valor'], 2, '.', '')
            : null;

        return new ChargeStatus(
            state: $state,
            paidAmount: $paidAmount,
            paidAt: isset($data['paidAt']) && $data['paidAt'] !== null
                ? Carbon::parse($data['paidAt'])
                : null,
            raw: $data,
        );
    }

    public function cancelCharge(Payment $payment): void
    {
        // O microsserviço não expõe cancelamento — a cobrança PIX expira sozinha
        // pela `expiracao`. No-op (melhor esforço, coerente com o contrato).
    }

    public function refundCharge(Payment $payment, string $amount): RefundResult
    {
        throw new RefundNotSupported('Devolução de Pix é operacional no MVP.');
    }
}
