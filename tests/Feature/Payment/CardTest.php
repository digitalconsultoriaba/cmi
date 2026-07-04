<?php

namespace Tests\Feature\Payment;

use App\Domain\Events\Models\TicketStatus;
use App\Notifications\PaymentConfirmedPtBr;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

/**
 * US4 — cartão tokenizado (quickstart §US4).
 */
class CardTest extends PaymentTestCase
{
    use RefreshDatabase;

    public function test_aprovado_confirma_na_mesma_resposta(): void
    {
        Notification::fake();
        [$buyer, $order] = $this->pendingOrder();

        $response = $this->actingAs($buyer)
            ->postJson("/api/orders/{$order->code}/checkout/card", [
                'token' => 'tok_ok_4242',
                'installments' => 3,
            ])->assertOk();

        $response->assertJsonPath('data.status', 'paid')
            ->assertJsonPath('data.installments', 3)
            ->assertJsonPath('data.cardLast4', '4242');

        $fresh = $order->fresh();
        $this->assertSame('paid', $fresh->status->slug);
        $this->assertSame(TicketStatus::CONFIRMED, $fresh->tickets->first()->status->slug);
        Notification::assertSentTo($buyer, PaymentConfirmedPtBr::class);
    }

    public function test_recusado_deixa_pedido_pendente_e_permite_retry(): void
    {
        [$buyer, $order] = $this->pendingOrder();

        $this->actingAs($buyer)
            ->postJson("/api/orders/{$order->code}/checkout/card", [
                'token' => 'tok_declined_test',
                'installments' => 1,
            ])
            ->assertStatus(409)
            ->assertJsonPath('type', 'card_declined');

        $this->assertSame('pending', $order->fresh()->status->slug);

        // Retry com token válido funciona
        $this->actingAs($buyer)
            ->postJson("/api/orders/{$order->code}/checkout/card", [
                'token' => 'tok_ok_4242',
                'installments' => 1,
            ])->assertOk();

        $this->assertSame('paid', $order->fresh()->status->slug);
    }

    public function test_parcelas_invalidas_recusam(): void
    {
        [$buyer, $order] = $this->pendingOrder();

        foreach ([0, 13] as $installments) {
            $this->actingAs($buyer)
                ->postJson("/api/orders/{$order->code}/checkout/card", [
                    'token' => 'tok_ok_4242',
                    'installments' => $installments,
                ])->assertUnprocessable()->assertJsonValidationErrors(['installments']);
        }
    }

    public function test_token_parecendo_cartao_e_barrado_na_validacao(): void
    {
        [$buyer, $order] = $this->pendingOrder();

        // Um PAN disfarçado de token → 422 na borda (sem stack trace em log)
        $this->actingAs($buyer)
            ->postJson("/api/orders/{$order->code}/checkout/card", [
                'token' => '4242424242424242',
                'installments' => 1,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['token']);

        $this->assertSame('pending', $order->fresh()->status->slug);
        $this->assertSame(0, $order->payments()->count(), 'nenhuma cobrança criada com PAN');
    }
}
