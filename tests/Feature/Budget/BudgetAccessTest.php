<?php

namespace Tests\Feature\Budget;

/**
 * Spec 011 — escopo/papel (403) e validação (422) da aba Orçamento.
 */
class BudgetAccessTest extends BudgetTestCase
{
    public function test_attendee_nao_acessa(): void
    {
        $this->sellableEvent();
        $this->actingAs($this->attendee())->getJson($this->budgetUrl())->assertStatus(403);
    }

    public function test_treasury_acessa(): void
    {
        $this->sellableEvent();
        $this->actingAs($this->treasury())->getJson($this->budgetUrl())->assertOk();
    }

    public function test_valor_negativo_e_categoria_invalida_recusam(): void
    {
        $this->sellableEvent();
        $admin = $this->admin();

        // total zero → 422
        $this->actingAs($admin)->postJson($this->budgetUrl('/cost-items'), [
            'description' => 'X', 'category' => 'Outros', 'totalAmount' => '0.00',
        ])->assertStatus(422);

        // categoria fora da lista → 422
        $this->actingAs($admin)->postJson($this->budgetUrl('/cost-items'), [
            'description' => 'X', 'category' => 'Inexistente', 'totalAmount' => '10.00',
        ])->assertStatus(422);

        // lote com preço negativo → 422
        $this->actingAs($admin)->postJson($this->budgetUrl('/ticket-lots'), [
            'name' => 'L', 'unitPrice' => '-5.00', 'expectedQuantity' => 10,
        ])->assertStatus(422);
    }
}
