<?php

namespace App\Domain\Events\Payments;

use App\Domain\Events\Models\Order;
use App\Domain\Events\Models\Payment;
use Illuminate\Support\Carbon;

/**
 * Driver Sicoob real (Pix + boleto híbrido) sobre o SicoobClient.
 * Entra por env (`PAYMENTS_PIX_DRIVER=sicoob`) quando houver credenciais.
 */
class SicoobGateway implements PaymentGatewayContract
{
    public function __construct(private readonly SicoobClient $client)
    {
    }

    public function providerName(): string
    {
        return 'sicoob';
    }

    public function createPixCharge(Order $order): ChargeData
    {
        $txid = SicoobClient::newTxid();
        $expiracao = max(60, (int) now()->diffInSeconds($order->reserved_until ?? now()->addMinutes(30), false));

        $response = $this->client->createPixCharge($txid, [
            'calendario' => ['expiracao' => $expiracao],
            'valor' => ['original' => $order->total_amount],
            'chave' => config('payments.sicoob.pix_key'),
            'solicitacaoPagador' => 'Pedido '.$order->code,
        ]);

        return new ChargeData(
            externalId: $txid,
            pixCopiaECola: $response['pixCopiaECola'] ?? $response['brcode'] ?? '',
            expiresAt: $order->reserved_until !== null ? Carbon::parse($order->reserved_until) : null,
            raw: $response,
        );
    }

    public function createBoletoCharge(Order $order): ChargeData
    {
        $response = $this->client->createHybridBoleto([
            'valor' => $order->total_amount,
            'dataVencimento' => Carbon::parse($order->reserved_until ?? now()->addDay())->format('Y-m-d'),
            'pagador' => [
                'nome' => $order->buyer_name,
                'email' => $order->buyer_email,
            ],
            'seuNumero' => $order->code,
        ]);

        return new ChargeData(
            externalId: (string) ($response['nossoNumero'] ?? $response['txid'] ?? $order->code),
            pixCopiaECola: $response['qrCode'] ?? $response['pixCopiaECola'] ?? null,
            boletoLine: $response['linhaDigitavel'] ?? null,
            boletoBarcode: $response['codigoBarras'] ?? null,
            boletoPdfUrl: $response['pdfBoletoUrl'] ?? null,
            expiresAt: $order->reserved_until !== null ? Carbon::parse($order->reserved_until) : null,
            raw: $response,
        );
    }

    public function chargeCard(Order $order, string $token, int $installments): CardResult
    {
        throw new \LogicException('Sicoob não processa cartão — use o card driver.');
    }

    public function getChargeStatus(Payment $payment): ChargeStatus
    {
        $response = $this->client->getPixCharge($payment->provider_charge_id);
        $status = $response['status'] ?? 'ATIVA';

        $state = match ($status) {
            'CONCLUIDA' => ChargeStatus::PAID,
            'REMOVIDA_PELO_USUARIO_RECEBEDOR', 'REMOVIDA_PELO_PSP' => ChargeStatus::CANCELLED,
            default => ChargeStatus::PENDING,
        };

        $pix = $response['pix'][0] ?? null;

        return new ChargeStatus(
            state: $state,
            paidAmount: $pix['valor'] ?? null,
            paidAt: isset($pix['horario']) ? Carbon::parse($pix['horario']) : null,
            raw: $response,
        );
    }

    public function cancelCharge(Payment $payment): void
    {
        $this->client->cancelPixCharge($payment->provider_charge_id);
    }
}
