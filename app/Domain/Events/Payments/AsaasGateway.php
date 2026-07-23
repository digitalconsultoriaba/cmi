<?php

namespace App\Domain\Events\Payments;

use App\Domain\Events\Exceptions\DomainRuleViolation;
use App\Domain\Events\Models\Order;
use App\Domain\Events\Models\Payment;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use LogicException;
use RuntimeException;

/**
 * Driver ASAAS (Checkout hospedado de cartão). Cobrança única (nunca
 * assinatura): 1x → DETACHED, parcelado → INSTALLMENT. Só cartão
 * (billingTypes: CREDIT_CARD). Os juros de parcelamento são repassados ao
 * comprador — repasse configurado na conta ASAAS, não aqui.
 *
 * Entra por env (`PAYMENTS_CARD_DRIVER=asaas`). Como o pagamento é
 * assíncrono/redirect, a baixa chega por webhook → RegisterPayment.
 */
class AsaasGateway implements PaymentGatewayContract, SupportsHostedCheckout
{
    public function __construct(private readonly AsaasClient $client)
    {
    }

    public function providerName(): string
    {
        return 'asaas';
    }

    public function createCardCheckout(Order $order, int $installments, ?array $customerData = null): HostedCheckout
    {
        $max = (int) config('payments.asaas.max_installments', 12);
        $installments = max(1, min($installments, $max));

        $base = rtrim((string) config('payments.asaas.frontend_url'), '/');
        $slug = $order->event->slug;
        $ret = fn (string $flag) => "{$base}/checkout/{$slug}?order={$order->code}&{$flag}=1";

        // ASAAS exige DETACHED (à vista) sempre presente; INSTALLMENT é somado
        // para habilitar o parcelamento. Nunca RECURRENT (sem assinatura).
        $payload = [
            'billingTypes' => ['CREDIT_CARD'],
            'chargeTypes' => $installments > 1 ? ['DETACHED', 'INSTALLMENT'] : ['DETACHED'],
            'minutesToExpire' => 30,
            'callback' => [
                'successUrl' => $ret('paid'),
                'cancelUrl' => $ret('cancel'),
                'expiredUrl' => $ret('expired'),
            ],
            'items' => [[
                // ASAAS limita `name` a 30 caracteres — evento vai na descrição.
                'name' => Str::limit('Inscrição '.$order->code, 30, ''),
                'description' => 'Pedido '.$order->code.' — '.$order->event->name,
                'quantity' => 1,
                'value' => (float) $order->total_amount,
            ]],
            'externalReference' => $order->code,
        ];

        // Pré-preenche o cadastro do comprador na página hospedada. O ASAAS
        // exige o conjunto completo (CPF, telefone, endereço) ou nada — por isso
        // só enviamos quando o checkout coletou todos os campos.
        if ($customerData !== null) {
            $filled = array_filter([
                'name' => $customerData['name'] ?? $order->buyer_name,
                'email' => $customerData['email'] ?? $order->buyer_email,
                'cpfCnpj' => preg_replace('/\D/', '', (string) ($customerData['cpfCnpj'] ?? '')),
                // O objeto de cliente do ASAAS usa `mobilePhone` (celular).
                'mobilePhone' => preg_replace('/\D/', '', (string) ($customerData['phoneNumber'] ?? '')),
                'postalCode' => preg_replace('/\D/', '', (string) ($customerData['postalCode'] ?? '')),
                'address' => $customerData['address'] ?? null,
                'addressNumber' => $customerData['addressNumber'] ?? null,
                'complement' => $customerData['complement'] ?? null,
                'province' => $customerData['province'] ?? null,
            ], fn ($v) => $v !== null && $v !== '');

            if (! empty($filled)) {
                $payload['customerData'] = $filled;
            }
        }

        if ($installments > 1) {
            $payload['installment'] = ['maxInstallmentCount' => $installments];
        }

        try {
            $response = $this->client->createCheckout($payload);
        } catch (RequestException $e) {
            Log::warning('ASAAS: falha ao criar checkout', [
                'order' => $order->code,
                'status' => $e->response?->status(),
                'body' => $e->response?->body(),
            ]);

            throw new DomainRuleViolation(
                'Não foi possível iniciar o pagamento com cartão. Tente novamente em instantes.',
                'gateway_error'
            );
        }

        $redirect = (string) ($response['link'] ?? $response['url'] ?? $response['invoiceUrl'] ?? '');
        if ($redirect === '') {
            throw new RuntimeException('ASAAS: resposta de checkout sem URL de redirecionamento.');
        }

        return new HostedCheckout(
            checkoutId: (string) ($response['id'] ?? ''),
            redirectUrl: $redirect,
            raw: $response,
        );
    }

    public function getChargeStatus(Payment $payment): ChargeStatus
    {
        // Reconsulta o pagamento pelo checkoutSession (= provider_charge_id do
        // nosso payment). O filtro `?checkoutSession=` do ASAAS NÃO funciona
        // (devolve vazio mesmo com pagamentos vinculados) e o pagamento NÃO herda
        // o externalReference do checkout — então listamos os recentes e casamos
        // por checkoutSession aqui. Parcelado gera N pagamentos, todos com o mesmo
        // checkoutSession; qualquer um CONFIRMED confirma o pedido inteiro.
        $response = $this->client->listPayments(['limit' => 100]);

        $rows = array_values(array_filter(
            $response['data'] ?? [],
            fn ($p) => ($p['checkoutSession'] ?? null) === $payment->provider_charge_id,
        ));
        $paid = null;
        $expired = false;

        foreach ($rows as $p) {
            $status = strtoupper((string) ($p['status'] ?? ''));
            if (in_array($status, ['CONFIRMED', 'RECEIVED', 'RECEIVED_IN_CASH'], true)) {
                $paid = $p;
                break;
            }
            if (in_array($status, ['OVERDUE', 'REFUNDED', 'CHARGEBACK_REQUESTED'], true)) {
                $expired = true;
            }
        }

        if ($paid !== null) {
            // paidAmount fica null de propósito: o valor cobrado pode incluir
            // juros de parcelamento repassados ao comprador; a baixa usa o total
            // do pedido (payment->amount), evitando falso "parcialmente pago".
            // ASAAS envia data SEM hora (ex.: "2026-07-23") em confirmedDate/
            // paymentDate — usá-la zeraria a hora (00:00) no comprovante. Só
            // aproveitamos quando vier com horário; senão o RegisterPayment usa
            // now() (momento real em que a confirmação foi processada).
            $when = $paid['confirmedDate'] ?? $paid['paymentDate'] ?? null;
            $hasTime = is_string($when) && preg_match('/[T ]\d{2}:\d{2}/', $when) === 1;

            return new ChargeStatus(
                state: ChargeStatus::PAID,
                paidAmount: null,
                paidAt: $hasTime ? Carbon::parse($when) : null,
                raw: $paid,
                cardBrand: $paid['creditCard']['creditCardBrand'] ?? null,
                cardLast4: $paid['creditCard']['creditCardNumber'] ?? null,
                installments: $this->resolveInstallments($paid),
            );
        }

        return new ChargeStatus(
            state: $expired ? ChargeStatus::EXPIRED : ChargeStatus::PENDING,
            raw: $response,
        );
    }

    /**
     * Nº real de parcelas escolhido pelo comprador na página do ASAAS. À vista
     * (sem `installment`) → 1. Parcelado → o pagamento só carrega o id do
     * parcelamento; a contagem vem de uma consulta ao parcelamento. Falha na
     * consulta → null (não sobrescreve com valor errado; a placeholder permanece).
     */
    private function resolveInstallments(array $paid): ?int
    {
        if (isset($paid['installmentCount'])) {
            return (int) $paid['installmentCount'] ?: null;
        }

        if (empty($paid['installment'])) {
            return 1; // à vista
        }

        try {
            $inst = $this->client->getInstallment((string) $paid['installment']);

            return ((int) ($inst['installmentCount'] ?? 0)) ?: null;
        } catch (\Throwable $e) {
            Log::warning('ASAAS: falha ao consultar parcelamento', [
                'installment' => $paid['installment'], 'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    public function cancelCharge(Payment $payment): void
    {
        // Melhor esforço: o checkout hospedado expira sozinho (minutesToExpire).
    }

    public function createPixCharge(Order $order): ChargeData
    {
        throw new LogicException('ASAAS driver: Pix não implementado (use o pix driver).');
    }

    public function createBoletoCharge(Order $order): ChargeData
    {
        throw new LogicException('ASAAS driver: boleto não implementado.');
    }

    public function chargeCard(Order $order, string $token, int $installments): CardResult
    {
        // Cartão via ASAAS é sempre pelo checkout hospedado (createCardCheckout),
        // nunca cobrança síncrona por token.
        throw new LogicException('ASAAS usa checkout hospedado — chame createCardCheckout.');
    }

    public function refundCharge(Payment $payment, string $amount): RefundResult
    {
        throw new RefundNotSupported('Estorno ASAAS é operacional no MVP.');
    }
}
