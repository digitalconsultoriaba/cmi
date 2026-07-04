<?php

namespace Tests\Feature\Payment;

use App\Domain\Events\Models\Payment;
use App\Domain\Events\Models\WebhookEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * US4 / SC-005 — nenhum dado de cartão em banco, respostas ou logs.
 */
class AntiPanTest extends PaymentTestCase
{
    use RefreshDatabase;

    /** PANs de teste que jamais podem aparecer persistidos. */
    private const TEST_PANS = ['4242424242424242', '4000000000000002'];

    public function test_fluxos_de_pagamento_nao_persistem_nada_parecido_com_cartao(): void
    {
        [$buyer, $order] = $this->pendingOrder();

        // Fluxo completo: pix + boleto + cartão aprovado
        $this->createPixCharge($buyer, $order);
        $this->actingAs($buyer)->postJson("/api/orders/{$order->code}/checkout/boleto");
        $cardResponse = $this->actingAs($buyer)
            ->postJson("/api/orders/{$order->code}/checkout/card", [
                'token' => 'tok_ok_4242',
                'installments' => 2,
            ])->assertOk();

        // Resposta da API: só last4, nunca PAN
        $this->assertSame('4242', $cardResponse->json('data.cardLast4'));
        $this->assertPanFree(json_encode($cardResponse->json()));

        // Banco: todas as colunas de payments e webhook_events
        Payment::query()->get()->each(function (Payment $payment) {
            $this->assertPanFree(json_encode($payment->getAttributes()));
        });
        WebhookEvent::query()->get()->each(function (WebhookEvent $event) {
            $this->assertPanFree(json_encode($event->getAttributes()));
        });

        // Log do laravel (se existir no ambiente de teste)
        $logFile = storage_path('logs/laravel.log');
        if (file_exists($logFile)) {
            $this->assertPanFree(file_get_contents($logFile));
        }
    }

    private function assertPanFree(string $haystack): void
    {
        foreach (self::TEST_PANS as $pan) {
            $this->assertStringNotContainsString($pan, $haystack, 'PAN encontrado!');
        }

        // Qualquer sequência de 13-19 dígitos que passe em Luhn = suspeita de PAN
        preg_match_all('/\d{13,19}/', $haystack, $matches);
        foreach ($matches[0] as $candidate) {
            $this->assertFalse(
                $this->passesLuhn($candidate),
                "Sequência tipo cartão persistida: $candidate — contexto: ".substr($haystack, max(0, strpos($haystack, $candidate) - 120), 300)
            );
        }
    }

    private function passesLuhn(string $number): bool
    {
        $sum = 0;
        $alt = false;

        for ($i = strlen($number) - 1; $i >= 0; $i--) {
            $digit = (int) $number[$i];
            if ($alt) {
                $digit *= 2;
                if ($digit > 9) {
                    $digit -= 9;
                }
            }
            $sum += $digit;
            $alt = ! $alt;
        }

        return $sum % 10 === 0;
    }
}
