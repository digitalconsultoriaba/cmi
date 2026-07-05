<?php

namespace Tests\Feature\Budget;

/**
 * Spec 011 — derivações do resumo do orçamento: valor do item, exclusão de
 * cancelados, resultado/investimento próprio e divisor zero.
 */
class BudgetSummaryTest extends BudgetTestCase
{
    public function test_valor_do_item_qtd_x_unitario_e_so_total(): void
    {
        $this->sellableEvent();
        $admin = $this->admin();

        // qtd × unitário → total derivado
        $r = $this->actingAs($admin)->postJson($this->budgetUrl('/cost-items'), [
            'description' => 'Sonorização', 'category' => 'Som e iluminação',
            'quantity' => 2, 'unitPrice' => '1000.00',
        ])->assertCreated();
        $this->assertSame('2000.00', $r->json('data.totalAmount'));

        // só total → aceito
        $this->actingAs($admin)->postJson($this->budgetUrl('/cost-items'), [
            'description' => 'Buffet', 'category' => 'Alimentação', 'totalAmount' => '500.00',
        ])->assertCreated()->assertJsonPath('data.totalAmount', '500.00');

        $summary = $this->actingAs($admin)->getJson($this->budgetUrl())->json('data.summary');
        $this->assertSame('2500.00', $summary['totalCost']);
    }

    public function test_item_cancelado_fica_fora_do_custo(): void
    {
        $this->sellableEvent();
        $admin = $this->admin();

        $this->actingAs($admin)->postJson($this->budgetUrl('/cost-items'), [
            'description' => 'Ativo', 'category' => 'Outros', 'totalAmount' => '1000.00',
        ])->assertCreated();
        $this->actingAs($admin)->postJson($this->budgetUrl('/cost-items'), [
            'description' => 'Cancelado', 'category' => 'Outros', 'totalAmount' => '999.00',
            'status' => 'cancelled',
        ])->assertCreated();

        $summary = $this->actingAs($admin)->getJson($this->budgetUrl())->json('data.summary');
        $this->assertSame('1000.00', $summary['totalCost']);
    }

    public function test_resultado_e_investimento_proprio(): void
    {
        $this->sellableEvent();
        $admin = $this->admin();

        // custo 250k
        $this->actingAs($admin)->postJson($this->budgetUrl('/cost-items'), [
            'description' => 'Tudo', 'category' => 'Outros', 'totalAmount' => '250000.00',
        ])->assertCreated();
        // ingressos 100k (200 × 500)
        $this->actingAs($admin)->postJson($this->budgetUrl('/ticket-lots'), [
            'name' => 'Lote', 'unitPrice' => '500.00', 'expectedQuantity' => 200,
        ])->assertCreated();
        // patrocínio 100k confirmado
        $this->actingAs($admin)->postJson($this->budgetUrl('/sponsorships'), [
            'name' => 'Master', 'unitValue' => '100000.00', 'quantity' => 1, 'status' => 'confirmed',
        ])->assertCreated();

        $summary = $this->actingAs($admin)->getJson($this->budgetUrl())->json('data.summary');
        // receita total 200k, resultado -50k, investimento próprio 50k
        $this->assertSame('200000.00', $summary['totalRevenue']);
        $this->assertSame('-50000.00', $summary['result']);
        $this->assertSame('deficit', $summary['classification']);
        $this->assertSame('50000.00', $summary['ownInvestment']);
    }

    public function test_ponto_de_equilibrio_e_divisor_zero(): void
    {
        $this->sellableEvent();
        $admin = $this->admin();

        // sem pagantes → ticket médio e ponto de equilíbrio nulos
        $summary = $this->actingAs($admin)->getJson($this->budgetUrl())->json('data.summary');
        $this->assertNull($summary['avgTicket']);
        $this->assertNull($summary['breakEvenPaying']);

        // custo 250k, patrocínio 100k, ticket médio 300 (500 pagantes, receita 150k)
        $this->actingAs($admin)->postJson($this->budgetUrl('/cost-items'), [
            'description' => 'Custo', 'category' => 'Outros', 'totalAmount' => '250000.00',
        ])->assertCreated();
        $this->actingAs($admin)->postJson($this->budgetUrl('/ticket-lots'), [
            'name' => 'Lote', 'unitPrice' => '300.00', 'expectedQuantity' => 500,
        ])->assertCreated();
        $this->actingAs($admin)->postJson($this->budgetUrl('/sponsorships'), [
            'name' => 'Master', 'unitValue' => '100000.00', 'quantity' => 1, 'status' => 'confirmed',
        ])->assertCreated();
        $this->actingAs($admin)->putJson($this->budgetUrl(), ['expectedPaying' => 500])->assertOk();

        $summary = $this->actingAs($admin)->getJson($this->budgetUrl())->json('data.summary');
        $this->assertSame('300.00', $summary['avgTicket']);
        // (250k - 100k) / 300 = 500
        $this->assertSame(500, $summary['breakEvenPaying']);
    }
}
