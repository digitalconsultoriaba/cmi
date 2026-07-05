<?php

namespace Tests\Feature\Budget;

/**
 * Spec 011 — receita por lote, patrocínio previsto×confirmado, preço mínimo.
 */
class BudgetRevenueTest extends BudgetTestCase
{
    public function test_receita_por_lote_e_patrocinio_previsto_confirmado(): void
    {
        $this->sellableEvent();
        $admin = $this->admin();

        foreach ([['250.00', 200], ['300.00', 200], ['350.00', 200]] as [$price, $qty]) {
            $this->actingAs($admin)->postJson($this->budgetUrl('/ticket-lots'), [
                'name' => "Lote {$price}", 'unitPrice' => $price, 'expectedQuantity' => $qty,
            ])->assertCreated();
        }
        // confirmado 100k, previsto 30k, perdido 5k (fora)
        $this->actingAs($admin)->postJson($this->budgetUrl('/sponsorships'), [
            'name' => 'Master', 'unitValue' => '100000.00', 'quantity' => 1, 'status' => 'confirmed',
        ])->assertCreated();
        $this->actingAs($admin)->postJson($this->budgetUrl('/sponsorships'), [
            'name' => 'Ouro', 'unitValue' => '30000.00', 'quantity' => 1, 'status' => 'negotiating',
        ])->assertCreated();
        $this->actingAs($admin)->postJson($this->budgetUrl('/sponsorships'), [
            'name' => 'Bronze', 'unitValue' => '5000.00', 'quantity' => 1, 'status' => 'lost',
        ])->assertCreated();

        $summary = $this->actingAs($admin)->getJson($this->budgetUrl())->json('data.summary');
        $this->assertSame('180000.00', $summary['ticketRevenue']);      // 50k+60k+70k
        $this->assertSame('130000.00', $summary['sponsorshipExpected']); // 100k+30k (lost fora)
        $this->assertSame('100000.00', $summary['sponsorshipConfirmed']); // só confirmado
    }

    public function test_cenario_upsert_e_fecha_orcamento(): void
    {
        $this->sellableEvent();
        $admin = $this->admin();

        $r = $this->actingAs($admin)->putJson($this->budgetUrl('/scenarios/realistic'), [
            'paying' => 500, 'avgTicket' => '300.00', 'sponsorship' => '100000.00',
            'cost' => '250000.00', 'otherRevenue' => '0.00',
        ])->assertOk();
        // 500×300 + 100k = 250k ≥ 250k → fecha
        $this->assertTrue($r->json('data.closesBudget'));
        $this->assertSame('realistic', $r->json('data.key'));
    }
}
