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
        ));

        $event->forceFill(['processed_at' => now(), 'result' => 'ok'])->save();

        return ApiResponse::data(['result' => 'ok']);
    }
}
