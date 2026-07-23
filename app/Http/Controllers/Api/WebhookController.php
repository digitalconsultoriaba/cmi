<?php

namespace App\Http\Controllers\Api;

use App\Domain\Events\Models\Payment;
use App\Domain\Events\Models\WebhookEvent;
use App\Domain\Events\Payments\PaymentGateways;
use App\Domain\Events\Services\PaymentEvidence;
use App\Domain\Events\Services\RegisterPayment;
use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;

/**
 * Registrar primeiro, confiar nunca: payload bruto persistido, dedupe pelo
 * unique, verificação de origem e RECONSULTA ao provedor antes de qualquer
 * baixa (constituição, III; research 005, Decisão 4).
 */
class WebhookController extends Controller
{
    public function __construct(
        private readonly PaymentGateways $gateways,
        private readonly RegisterPayment $registerPayment,
    ) {
    }

    public function sicoob(Request $request)
    {
        return $this->handle($request, 'sicoob', (string) config('payments.sicoob.webhook_secret'));
    }

    public function card(Request $request)
    {
        return $this->handle($request, 'card_gateway', (string) config('payments.card.webhook_secret'));
    }

    /**
     * Webhook do ASAAS (Checkout hospedado). Difere do handle() genérico: a
     * origem é o header `asaas-access-token`, o dedupe usa o id do evento
     * (`payload.id`, único por entrega — o mesmo pagamento gera vários eventos)
     * e a correlação com o nosso Payment é por `checkoutSession` (= o id do
     * checkout, gravado em provider_charge_id). O ASAAS não propaga o
     * externalReference do checkout para o pagamento, então o vínculo é o
     * checkoutSession.
     */
    public function asaas(Request $request)
    {
        $provider = 'asaas';
        $secret = (string) config('payments.asaas.webhook_secret');
        $signature = (string) $request->header('asaas-access-token', '');
        $payload = $request->all();

        if ($secret === '' || ! hash_equals($secret, $signature)) {
            WebhookEvent::query()->create([
                'provider' => $provider,
                'external_id' => null,
                'event_type' => 'invalid_signature',
                'payload' => $payload,
                'signature' => substr($signature, 0, 100),
                'received_at' => now(),
                'processed_at' => now(),
                'result' => 'error',
            ]);

            return ApiResponse::error('Origem não reconhecida.', 'unauthenticated', 401);
        }

        // Dedupe pelo id do EVENTO (não do pagamento): um mesmo pagamento dispara
        // PAYMENT_CONFIRMED e PAYMENT_RECEIVED, ambos legítimos.
        $eventId = $payload['id'] ?? ($payload['payment']['id'] ?? null);
        $checkoutId = $payload['payment']['checkoutSession'] ?? null;

        try {
            $event = WebhookEvent::query()->create([
                'provider' => $provider,
                'external_id' => $eventId,
                'event_type' => $payload['event'] ?? null,
                'payload' => $payload,
                'signature' => substr($signature, 0, 100),
                'received_at' => now(),
            ]);
        } catch (UniqueConstraintViolationException) {
            return ApiResponse::data(['result' => 'ignored']);
        }

        // Correlação por checkoutSession → nosso payment (provider_charge_id).
        $payment = $checkoutId !== null
            ? Payment::query()->where('provider', $provider)
                ->where('provider_charge_id', $checkoutId)->first()
            : null;

        if ($payment === null) {
            $event->forceFill(['processed_at' => now(), 'result' => 'ignored'])->save();

            return ApiResponse::data(['result' => 'ignored']);
        }

        // Reconsulta obrigatória — o corpo do webhook nunca é fonte de verdade.
        $status = $this->gateways->forProvider($provider)->getChargeStatus($payment);

        if (! $status->isPaid()) {
            $event->forceFill(['processed_at' => now(), 'result' => 'ignored'])->save();

            return ApiResponse::data(['result' => 'ignored']);
        }

        $this->registerPayment->register($payment, new PaymentEvidence(
            source: PaymentEvidence::WEBHOOK,
            raw: ['webhook' => $payload, 'provider_status' => $status->raw],
            paidAmount: $status->paidAmount,
            paidAt: $status->paidAt,
            cardBrand: $status->cardBrand,
            cardLast4: $status->cardLast4,
            installments: $status->installments,
        ));

        $event->forceFill(['processed_at' => now(), 'result' => 'ok'])->save();

        return ApiResponse::data(['result' => 'ok']);
    }

    /**
     * Webhook PIX do microsserviço Boletos SICOOB V2 (doc v1.1.0 §5.1). Difere do
     * handle() genérico: a assinatura é `X-Pix-Signature: sha256=<hmac>` sobre o
     * CORPO CRU (não o array reparseado), o dedupe usa o `endToEndId` (o mesmo
     * aviso pode chegar mais de uma vez por retry) e a correlação é por `txid`
     * (= provider_charge_id). Reconsulta antes de baixar (corpo nunca é fonte de
     * verdade) — convive com o polling/reconciliação como fallback.
     */
    public function pixNotify(Request $request)
    {
        $provider = 'sicoob';
        $secret = (string) config('payments.boletos.notify_secret');
        $raw = $request->getContent();
        $signature = (string) $request->header('X-Pix-Signature', '');
        $expected = 'sha256='.hash_hmac('sha256', $raw, $secret);
        $payload = $request->all();

        if ($secret === '' || ! hash_equals($expected, $signature)) {
            WebhookEvent::query()->create([
                'provider' => $provider,
                'external_id' => null,
                'event_type' => 'invalid_signature',
                'payload' => $payload,
                'signature' => substr($signature, 0, 100),
                'received_at' => now(),
                'processed_at' => now(),
                'result' => 'error',
            ]);

            return ApiResponse::error('Origem não reconhecida.', 'unauthenticated', 401);
        }

        // Dedupe idempotente pelo endToEndId (retries do microsserviço).
        $endToEndId = $payload['endToEndId'] ?? null;
        $txid = $payload['txid'] ?? null;

        try {
            $event = WebhookEvent::query()->create([
                'provider' => $provider,
                'external_id' => $endToEndId,
                'event_type' => $payload['status'] ?? 'pix_notify',
                'payload' => $payload,
                'signature' => substr($signature, 0, 100),
                'received_at' => now(),
            ]);
        } catch (UniqueConstraintViolationException) {
            return ApiResponse::data(['result' => 'ignored']);
        }

        // Correlação por txid → nosso payment PIX (provider sicoob).
        $payment = $txid !== null
            ? Payment::query()->where('provider', $provider)->where('method', 'pix')
                ->where('provider_charge_id', $txid)->latest('id')->first()
            : null;

        if ($payment === null) {
            $event->forceFill(['processed_at' => now(), 'result' => 'ignored'])->save();

            return ApiResponse::data(['result' => 'ignored']);
        }

        // Reconsulta obrigatória — o corpo do webhook nunca é fonte de verdade.
        $status = $this->gateways->forProvider($provider)->getChargeStatus($payment);

        if (! $status->isPaid()) {
            $event->forceFill(['processed_at' => now(), 'result' => 'ignored'])->save();

            return ApiResponse::data(['result' => 'ignored']);
        }

        $this->registerPayment->register($payment, new PaymentEvidence(
            source: PaymentEvidence::WEBHOOK,
            raw: ['webhook' => $payload, 'provider_status' => $status->raw],
            paidAmount: $status->paidAmount,
            paidAt: $status->paidAt,
            cardBrand: $status->cardBrand,
            cardLast4: $status->cardLast4,
            installments: $status->installments,
        ));

        $event->forceFill(['processed_at' => now(), 'result' => 'ok'])->save();

        return ApiResponse::data(['result' => 'ok']);
    }

    private function handle(Request $request, string $provider, string $secret)
    {
        $payload = $request->all();
        $signature = (string) $request->header('X-Webhook-Secret', '');

        // Origem inválida: registra para auditoria (sem external_id — não trava
        // um evento legítimo futuro no unique) e rejeita sem efeito (FR-008).
        if ($secret === '' || ! hash_equals($secret, $signature)) {
            WebhookEvent::query()->create([
                'provider' => $provider,
                'external_id' => null,
                'event_type' => 'invalid_signature',
                'payload' => $payload,
                'signature' => substr($signature, 0, 100),
                'received_at' => now(),
                'processed_at' => now(),
                'result' => 'error',
            ]);

            return ApiResponse::error('Origem não reconhecida.', 'unauthenticated', 401);
        }

        $externalId = $payload['txid']
            ?? $payload['pix'][0]['txid']
            ?? $payload['nossoNumero']
            ?? $payload['external_id']
            ?? null;

        // Dedupe estrutural: unique (provider, external_id)
        try {
            $event = WebhookEvent::query()->create([
                'provider' => $provider,
                'external_id' => $externalId,
                'event_type' => $payload['event'] ?? $payload['tipo'] ?? null,
                'payload' => $payload,
                'signature' => substr($signature, 0, 100),
                'received_at' => now(),
            ]);
        } catch (UniqueConstraintViolationException) {
            return ApiResponse::data(['result' => 'ignored']); // duplicata (SC-002)
        }

        $payment = $externalId !== null
            ? Payment::query()->where('provider', $provider)
                ->where('provider_charge_id', $externalId)->first()
            : null;

        if ($payment === null) {
            $event->forceFill(['processed_at' => now(), 'result' => 'ignored'])->save();

            return ApiResponse::data(['result' => 'ignored']);
        }

        // Reconsulta obrigatória — o corpo do webhook nunca é fonte de verdade
        $status = $this->gateways->forProvider($provider)->getChargeStatus($payment);

        if (! $status->isPaid()) {
            $event->forceFill(['processed_at' => now(), 'result' => 'ignored'])->save();

            return ApiResponse::data(['result' => 'ignored']);
        }

        $this->registerPayment->register($payment, new PaymentEvidence(
            source: PaymentEvidence::WEBHOOK,
            raw: ['webhook' => $payload, 'provider_status' => $status->raw],
            paidAmount: $status->paidAmount,
            paidAt: $status->paidAt,
            cardBrand: $status->cardBrand,
            cardLast4: $status->cardLast4,
            installments: $status->installments,
        ));

        $event->forceFill(['processed_at' => now(), 'result' => 'ok'])->save();

        return ApiResponse::data(['result' => 'ok']);
    }
}
