<?php

namespace Tests\Feature\Finance;

use App\Domain\Events\Models\FinancialEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;

/**
 * US2 — baixa total/parcial, edição com justificativa (spec 010).
 */
class SettlementTest extends FinanceTestCase
{
    use RefreshDatabase;

    private function novaConta($fin, array $over = []): int
    {
        return $this->actingAs($fin)->postJson('/api/finance/entries',
            $this->entryPayload(array_merge(['amount' => '1000.00'], $over)))->json('data.id');
    }

    public function test_baixa_parcial_depois_total(): void
    {
        $fin = $this->finance();
        $id = $this->novaConta($fin);

        $this->actingAs($fin)->postJson("/api/finance/entries/$id/settle", [
            'amount' => '400.00', 'settled_on' => now()->toDateString(),
        ])->assertOk()->assertJsonPath('data.status', 'partial')
            ->assertJsonPath('data.balance', '600.00');

        $this->actingAs($fin)->postJson("/api/finance/entries/$id/settle", [
            'amount' => '600.00', 'settled_on' => now()->toDateString(),
        ])->assertOk()->assertJsonPath('data.status', 'settled')
            ->assertJsonPath('data.balance', '0.00');

        $this->assertSame(2, Activity::query()->where('log_name', 'financial.settled')->count());
    }

    public function test_baixa_maior_que_saldo_recusa(): void
    {
        $fin = $this->finance();
        $id = $this->novaConta($fin);

        $this->actingAs($fin)->postJson("/api/finance/entries/$id/settle", [
            'amount' => '1500.00', 'settled_on' => now()->toDateString(),
        ])->assertStatus(409)->assertJsonPath('type', 'exceeds_balance');
    }

    public function test_cancelada_nao_recebe_baixa(): void
    {
        $fin = $this->finance();
        $id = $this->novaConta($fin);
        $this->actingAs($fin)->postJson("/api/finance/entries/$id/cancel", ['reason' => 'engano'])->assertOk();

        $this->actingAs($fin)->postJson("/api/finance/entries/$id/settle", [
            'amount' => '100.00', 'settled_on' => now()->toDateString(),
        ])->assertStatus(409)->assertJsonPath('type', 'cancelled');
    }

    public function test_editar_baixada_exige_justificativa(): void
    {
        $fin = $this->finance();
        $id = $this->novaConta($fin);
        $this->actingAs($fin)->postJson("/api/finance/entries/$id/settle", [
            'amount' => '200.00', 'settled_on' => now()->toDateString(),
        ])->assertOk();

        // Sem justificativa → 409
        $this->actingAs($fin)->putJson("/api/finance/entries/$id", ['description' => 'Novo'])
            ->assertStatus(409)->assertJsonPath('type', 'justification_required');

        // Com justificativa → 200 + log
        $this->actingAs($fin)->putJson("/api/finance/entries/$id", [
            'description' => 'Novo', 'justification' => 'Correção autorizada',
        ])->assertOk();
        $this->assertSame(1, Activity::query()->where('log_name', 'financial.updated')->count());
    }

    public function test_estorno_reduz_saldo_baixado(): void
    {
        $fin = $this->finance();
        $id = $this->novaConta($fin);
        $this->actingAs($fin)->postJson("/api/finance/entries/$id/settle", [
            'amount' => '500.00', 'settled_on' => now()->toDateString(),
        ])->assertOk();

        $this->actingAs($fin)->postJson("/api/finance/entries/$id/reverse", [
            'amount' => '500.00', 'reason' => 'Devolução',
        ])->assertOk()->assertJsonPath('data.balance', '1000.00');

        $this->assertSame('0.00', FinancialEntry::query()->find($id)->settled_amount);
    }
}
