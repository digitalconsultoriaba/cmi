<?php

namespace Tests\Feature\Budget;

/**
 * Spec 011 — comparativo orçado × realizado (vendas + Financeiro reais).
 */
class BudgetComparisonTest extends BudgetTestCase
{
    public function test_atingimento_de_meta_de_ingressos(): void
    {
        // Um pedido pago real (paidOrder monta o sellableEvent e vende 1 ingresso).
        [$buyer, $order] = $this->paidOrder(1);
        $admin = $this->admin();

        // Meta prevista de 4 ingressos → 1 vendido = 25%
        $this->actingAs($admin)->postJson($this->budgetUrl('/ticket-lots'), [
            'name' => 'Lote', 'unitPrice' => '250.00', 'expectedQuantity' => 4,
        ])->assertCreated();

        $cmp = $this->actingAs($admin)->getJson($this->budgetUrl('/comparison'))->assertOk()->json('data');
        $this->assertSame(4, $cmp['tickets']['budgeted']);
        $this->assertSame(1, $cmp['tickets']['actual']);
        $this->assertSame('25.00', $cmp['tickets']['attainmentPct']);
    }

    public function test_sem_dados_reais_retorna_zeros(): void
    {
        $this->sellableEvent();
        $admin = $this->admin();

        $cmp = $this->actingAs($admin)->getJson($this->budgetUrl('/comparison'))->assertOk()->json('data');
        $this->assertSame('0.00', $cmp['cost']['actual']);
        $this->assertSame('0.00', $cmp['revenue']['actual']);
        $this->assertNull($cmp['tickets']['attainmentPct']);
    }
}
