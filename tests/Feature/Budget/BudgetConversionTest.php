<?php

namespace Tests\Feature\Budget;

use App\Domain\Events\Models\BudgetCostItem;
use App\Domain\Events\Models\FinancialEntry;

/**
 * Spec 011 — conversão de previsão em financeiro real, idempotente.
 */
class BudgetConversionTest extends BudgetTestCase
{
    public function test_item_gera_conta_a_pagar_uma_vez(): void
    {
        $this->sellableEvent();
        $admin = $this->admin();

        $itemId = $this->actingAs($admin)->postJson($this->budgetUrl('/cost-items'), [
            'description' => 'Sonorização', 'category' => 'Som e iluminação', 'totalAmount' => '26000.00',
        ])->json('data.id');

        $this->actingAs($admin)->postJson($this->budgetUrl("/cost-items/{$itemId}/generate-payable"))
            ->assertCreated();

        $this->assertSame(1, FinancialEntry::query()
            ->where('event_id', $this->event->id)->where('direction', 'payable')->count());

        // 2ª tentativa → 409, nenhuma conta nova
        $this->actingAs($admin)->postJson($this->budgetUrl("/cost-items/{$itemId}/generate-payable"))
            ->assertStatus(409)->assertJsonPath('type', 'already_converted');

        $this->assertSame(1, FinancialEntry::query()
            ->where('event_id', $this->event->id)->where('direction', 'payable')->count());
    }

    public function test_excluir_item_preserva_lancamento(): void
    {
        $this->sellableEvent();
        $admin = $this->admin();

        $itemId = $this->actingAs($admin)->postJson($this->budgetUrl('/cost-items'), [
            'description' => 'Buffet', 'category' => 'Alimentação', 'totalAmount' => '5000.00',
        ])->json('data.id');
        $this->actingAs($admin)->postJson($this->budgetUrl("/cost-items/{$itemId}/generate-payable"))->assertCreated();

        $this->actingAs($admin)->deleteJson($this->budgetUrl("/cost-items/{$itemId}"))->assertNoContent();

        // item soft-deleted, lançamento preservado
        $this->assertNull(BudgetCostItem::query()->find($itemId));
        $this->assertSame(1, FinancialEntry::query()->where('event_id', $this->event->id)->count());
    }

    public function test_patrocinio_confirmado_gera_conta_a_receber_e_perdido_recusa(): void
    {
        $this->sellableEvent();
        $admin = $this->admin();

        $ok = $this->actingAs($admin)->postJson($this->budgetUrl('/sponsorships'), [
            'name' => 'Master', 'unitValue' => '100000.00', 'quantity' => 1, 'status' => 'confirmed',
        ])->json('data.id');
        $this->actingAs($admin)->postJson($this->budgetUrl("/sponsorships/{$ok}/generate-receivable"))
            ->assertCreated();
        $this->assertSame(1, FinancialEntry::query()
            ->where('event_id', $this->event->id)->where('direction', 'receivable')->count());

        // perdido → 409
        $lost = $this->actingAs($admin)->postJson($this->budgetUrl('/sponsorships'), [
            'name' => 'Bronze', 'unitValue' => '5000.00', 'quantity' => 1, 'status' => 'lost',
        ])->json('data.id');
        $this->actingAs($admin)->postJson($this->budgetUrl("/sponsorships/{$lost}/generate-receivable"))
            ->assertStatus(409)->assertJsonPath('type', 'invalid_sponsorship_status');
    }
}
