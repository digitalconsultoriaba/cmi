<?php

namespace Tests\Feature\Payment;

use App\Domain\Events\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

/**
 * US5 — tesouraria (quickstart §US5).
 */
class TreasuryTest extends PaymentTestCase
{
    use RefreshDatabase;

    private function treasurer()
    {
        $user = $this->buyer();
        $user->assignRole(Role::TREASURY);

        return $user;
    }

    public function test_receivables_lista_com_filtros_e_exige_papel(): void
    {
        [$buyer, $order] = $this->pendingOrder();
        $this->createPixCharge($buyer, $order);
        $this->settleAndNotify($order);

        // Sem papel → 403
        $this->actingAs($this->buyer())->getJson('/api/treasury/receivables')->assertStatus(403);

        $response = $this->actingAs($this->treasurer())
            ->getJson('/api/treasury/receivables')->assertOk();

        $paid = collect($response->json('data'))->firstWhere('status', 'paid');
        $this->assertSame($order->code, $paid['orderCode']);
        $this->assertSame('webhook', $paid['source']);
        $this->assertFalse($paid['flagged'], 'pagamento normal não é pendência');

        // Filtro por método
        $this->actingAs($this->treasurer())
            ->getJson('/api/treasury/receivables?method=boleto')
            ->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_pagamento_de_pedido_expirado_aparece_como_pendencia(): void
    {
        [$buyer, $order] = $this->pendingOrder();
        $this->createPixCharge($buyer, $order);
        $payment = $order->payments()->latest('id')->first();

        // Pedido expira; o pagamento chega depois (cobrança ainda viva no fake)
        Carbon::setTestNow(now()->addHours(2));
        $this->artisan('orders:expire')->assertSuccessful();
        Carbon::setTestNow();

        // Pagamento tardio direto no ponto único (cenário FR-012)
        $this->fakePix()->settle($payment->provider_charge_id);
        app(\App\Domain\Events\Services\RegisterPayment::class)->register(
            $payment->fresh(),
            new \App\Domain\Events\Services\PaymentEvidence(source: 'reconciliation')
        );

        $response = $this->actingAs($this->treasurer())
            ->getJson('/api/treasury/receivables?status=paid')->assertOk();

        $flagged = collect($response->json('data'))->firstWhere('orderCode', $order->code);
        $this->assertTrue($flagged['flagged'], 'pago × pedido expirado = pendência derivada');
    }

    public function test_reconcile_endpoint_dispara_e_retorna_resumo(): void
    {
        [$buyer, $order] = $this->pendingOrder();
        $this->createPixCharge($buyer, $order);
        $this->fakePix()->settle($order->payments()->latest('id')->first()->provider_charge_id);

        $this->actingAs($this->treasurer())
            ->postJson('/api/treasury/reconcile')
            ->assertOk()
            ->assertJsonPath('data.settled', 1);

        $this->assertSame('paid', $order->fresh()->status->slug);
    }

    public function test_baixa_manual_exige_justificativa_e_registra_trilha(): void
    {
        [, $order] = $this->pendingOrder();
        $treasurer = $this->treasurer();

        // Sem justificativa → 422
        $this->actingAs($treasurer)
            ->postJson("/api/treasury/orders/{$order->code}/pay-manual", [])
            ->assertUnprocessable()->assertJsonValidationErrors(['justification']);

        $this->actingAs($treasurer)
            ->postJson("/api/treasury/orders/{$order->code}/pay-manual", [
                'justification' => 'Transferência bancária comprovada pelo extrato',
            ])
            ->assertOk()
            ->assertJsonPath('data.orderStatus', 'paid');

        $payment = $order->payments()->latest('id')->first();
        $this->assertSame($treasurer->id, $payment->registered_by);
        $this->assertSame('manual', $payment->raw_response['source']);

        // Repetir em pedido pago → 409
        $this->actingAs($treasurer)
            ->postJson("/api/treasury/orders/{$order->code}/pay-manual", [
                'justification' => 'Tentativa duplicada de baixa',
            ])->assertStatus(409)->assertJsonPath('type', 'already_paid');
    }

    public function test_comprador_nunca_baixa_o_proprio_pedido(): void
    {
        [$buyer, $order] = $this->pendingOrder();
        $buyer->assignRole(Role::TREASURY); // mesmo com o papel!

        $this->actingAs($buyer)
            ->postJson("/api/treasury/orders/{$order->code}/pay-manual", [
                'justification' => 'Tentando baixar meu próprio pedido',
            ])
            ->assertStatus(403)
            ->assertJsonPath('type', 'forbidden');

        $this->assertSame('pending', $order->fresh()->status->slug);
    }
}
