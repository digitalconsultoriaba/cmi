<?php

namespace App\Domain\Events\Services;

use App\Domain\Events\Exceptions\DomainRuleViolation;
use App\Domain\Events\Models\Order;
use App\Domain\Events\Models\OrderStatus;
use App\Domain\Events\Models\Payment;
use App\Domain\Events\Models\PaymentStatus;
use App\Domain\Events\Payments\PaymentGateways;
use App\Domain\Events\Payments\SupportsHostedCheckout;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

/**
 * Cria cobranças (uma ativa por pedido — FR-005). Cartão é síncrono e passa
 * pelo RegisterPayment na aprovação.
 */
class CreateCharge
{
    public function __construct(
        private readonly PaymentGateways $gateways,
        private readonly RegisterPayment $registerPayment,
    ) {
    }

    public function pix(Order $order): Payment
    {
        $this->ensureChargeable($order, 'allow_pix');

        return DB::transaction(function () use ($order) {
            $this->expirePendingPayments($order);

            $charge = $this->gateways->pix()->createPixCharge($order);

            return $order->payments()->create([
                'amount' => $order->total_amount,
                'method' => 'pix',
                'provider' => $this->gateways->pix()->providerName(),
                'provider_charge_id' => $charge->externalId,
                'status_id' => PaymentStatus::idFor(PaymentStatus::PENDING),
                'pix_qrcode' => $charge->pixCopiaECola,
                'pix_qrcode_image' => (string) QrCode::format('svg')->size(220)->margin(1)
                    ->generate($charge->pixCopiaECola),
                'due_date' => $charge->expiresAt,
                'raw_response' => $charge->raw,
            ]);
        });
    }

    public function boleto(Order $order): Payment
    {
        $this->ensureChargeable($order, 'allow_boleto');

        $payment = DB::transaction(function () use ($order) {
            $this->expirePendingPayments($order);

            $charge = $this->gateways->pix()->createBoletoCharge($order);

            return $order->payments()->create([
                'amount' => $order->total_amount,
                'method' => 'boleto',
                'provider' => $this->gateways->pix()->providerName(),
                'provider_charge_id' => $charge->externalId,
                'status_id' => PaymentStatus::idFor(PaymentStatus::PENDING),
                'boleto_line' => $charge->boletoLine,
                'boleto_barcode' => $charge->boletoBarcode,
                'boleto_pdf_url' => $charge->boletoPdfUrl,
                'pix_qrcode' => $charge->pixCopiaECola, // cobrança híbrida
                'pix_qrcode_image' => $charge->pixCopiaECola
                    ? (string) QrCode::format('svg')->size(220)->margin(1)->generate($charge->pixCopiaECola)
                    : null,
                'due_date' => $charge->expiresAt,
                'raw_response' => $charge->raw,
            ]);
        });

        try {
            $order->buyerUser?->notify(new \App\Notifications\BoletoIssuedPtBr($payment));
        } catch (\Throwable $e) {
            Log::warning('Falha ao enviar e-mail de boleto', ['order' => $order->code, 'error' => $e->getMessage()]);
        }

        return $payment;
    }

    public function card(Order $order, string $token, int $installments): Payment
    {
        $this->ensureChargeable($order, 'allow_card');

        $gateway = $this->gateways->card();

        $payment = DB::transaction(function () use ($order, $gateway) {
            $this->expirePendingPayments($order);

            return $order->payments()->create([
                'amount' => $order->total_amount,
                'method' => 'card',
                'provider' => $gateway->providerName(),
                'status_id' => PaymentStatus::idFor(PaymentStatus::PENDING),
            ]);
        });

        $result = $gateway->chargeCard($order, $token, $installments);

        if (! $result->approved) {
            $payment->forceFill(['raw_response' => $result->raw]);
            $payment->transitionTo(PaymentStatus::FAILED);

            throw new DomainRuleViolation(
                $result->declineReason ?? 'Pagamento recusado.',
                'card_declined'
            );
        }

        $payment->forceFill([
            'provider_charge_id' => $result->externalId,
            'card_brand' => $result->brand,
            'card_last4' => $result->last4,
            'installments' => $installments,
        ])->save();

        return $this->registerPayment->register($payment, new PaymentEvidence(
            source: PaymentEvidence::GATEWAY,
            raw: $result->raw,
        ));
    }

    /**
     * Cria um checkout hospedado de cartão (ASAAS) e devolve a URL de redirect.
     * A baixa NÃO ocorre aqui — chega por webhook (RegisterPayment).
     *
     * @return array{redirectUrl: string}
     */
    public function cardCheckout(Order $order, int $installments, ?array $customerData = null): array
    {
        $this->ensureChargeable($order, 'allow_card');

        $gateway = $this->gateways->card();
        if (! $gateway instanceof SupportsHostedCheckout) {
            throw new DomainRuleViolation(
                'Checkout hospedado não disponível para o meio de pagamento atual.',
                'method_disabled'
            );
        }

        return DB::transaction(function () use ($order, $gateway, $installments, $customerData) {
            $this->expirePendingPayments($order);

            // Snapshot do CPF do comprador (spec 014 — mesmo padrão de buyer_*).
            $cpf = preg_replace('/\D/', '', (string) ($customerData['cpfCnpj'] ?? ''));
            if ($cpf !== '' && empty($order->buyer_document)) {
                $order->forceFill(['buyer_document' => $cpf])->save();
            }

            $checkout = $gateway->createCardCheckout($order, $installments, $customerData);

            $order->payments()->create([
                'amount' => $order->total_amount,
                'method' => 'card',
                'provider' => $gateway->providerName(),
                'provider_charge_id' => $checkout->checkoutId,
                'installments' => $installments,
                'status_id' => PaymentStatus::idFor(PaymentStatus::PENDING),
                'raw_response' => $checkout->raw,
            ]);

            return ['redirectUrl' => $checkout->redirectUrl];
        });
    }

    /** Expira cobranças pendentes anteriores + cancela no provedor (melhor esforço). */
    public function expirePendingPayments(Order $order): void
    {
        $pending = $order->payments()
            ->whereIn('status_id', PaymentStatus::idsFor([PaymentStatus::PENDING]))
            ->get();

        foreach ($pending as $payment) {
            try {
                $this->gateways->forProvider($payment->provider)->cancelCharge($payment);
            } catch (\Throwable $e) {
                Log::warning('Falha ao cancelar cobrança no provedor (melhor esforço)', [
                    'payment' => $payment->id, 'error' => $e->getMessage(),
                ]);
            }

            $payment->transitionTo(PaymentStatus::EXPIRED);
        }
    }

    private function ensureChargeable(Order $order, string $flag): void
    {
        if ($order->status?->slug !== OrderStatus::PENDING) {
            throw new DomainRuleViolation(
                'Este pedido não está aguardando pagamento.',
                'terminal_status'
            );
        }

        if ($order->reserved_until !== null && now()->gt($order->reserved_until)) {
            throw new DomainRuleViolation(
                'A reserva deste pedido expirou. Faça um novo pedido.',
                'terminal_status'
            );
        }

        if (! $order->event->{$flag}) {
            throw new DomainRuleViolation(
                'Este meio de pagamento não está habilitado para o evento.',
                'method_disabled'
            );
        }
    }
}
