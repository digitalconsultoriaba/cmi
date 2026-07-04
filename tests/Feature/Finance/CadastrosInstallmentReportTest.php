<?php

namespace Tests\Feature\Finance;

use App\Domain\Events\Models\FinancialCategory;
use App\Domain\Events\Models\FinancialEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * US5 (cadastros), US6 (parcelamento/recorrência), US4/US3 (dashboard/evento),
 * US8 (relatórios) — spec 010.
 */
class CadastrosInstallmentReportTest extends FinanceTestCase
{
    use RefreshDatabase;

    public function test_categoria_em_uso_nao_exclui(): void
    {
        $fin = $this->finance();
        $cat = FinancialCategory::query()->where('direction', 'expense')->first();

        $this->actingAs($fin)->postJson('/api/finance/entries', $this->entryPayload([
            'category_id' => $cat->id,
        ]))->assertCreated();

        $this->actingAs($fin)->deleteJson("/api/finance/categories/{$cat->id}")
            ->assertStatus(409)->assertJsonPath('type', 'has_entries');
    }

    public function test_parcelamento_gera_parcelas_independentes(): void
    {
        $fin = $this->finance();
        $this->actingAs($fin)->postJson('/api/finance/entries', $this->entryPayload([
            'amount' => '12000.00', 'installments' => 3, 'first_due_date' => now()->addMonth()->toDateString(),
        ]))->assertCreated();

        $group = FinancialEntry::query()->whereNotNull('installment_group')->get();
        $this->assertCount(3, $group);
        $this->assertSame('12000.00', number_format((float) $group->sum(fn ($e) => (float) $e->amount), 2, '.', ''));

        // Pagar a 1ª não mexe nas outras
        $first = $group->firstWhere('installment_number', 1);
        $this->actingAs($fin)->postJson("/api/finance/entries/{$first->id}/settle", [
            'amount' => '4000.00', 'settled_on' => now()->toDateString(),
        ])->assertOk();
        $others = $group->where('installment_number', '!=', 1);
        foreach ($others as $o) {
            $this->assertSame('open', $o->fresh()->status());
        }
    }

    public function test_recorrencia_gera_lancamentos(): void
    {
        $fin = $this->finance();
        $this->actingAs($fin)->postJson('/api/finance/recurrences', [
            'direction' => 'payable', 'description' => 'Hospedagem', 'amount' => '100.00',
            'frequency' => 'monthly', 'starts_on' => now()->toDateString(),
            'max_occurrences' => 3,
        ])->assertCreated();

        // 1 gerado na criação; o comando materializa os próximos até o limite
        $this->artisan('financial:generate-recurrences')->assertSuccessful();
        $this->assertGreaterThanOrEqual(2, FinancialEntry::query()->whereNotNull('recurrence_id')->count());
        $this->assertLessThanOrEqual(3, FinancialEntry::query()->whereNotNull('recurrence_id')->count());
    }

    public function test_dashboard_e_resultado_do_evento(): void
    {
        $fin = $this->finance();
        $this->sellableEvent();
        $eventId = $this->event->id;

        // Receita e despesa do evento
        $rid = $this->actingAs($fin)->postJson('/api/finance/entries', [
            'direction' => 'receivable', 'description' => 'Apoio', 'amount' => '3000.00',
            'category_id' => $this->anyCategory('income'), 'due_date' => now()->toDateString(),
            'event_id' => $eventId,
        ])->json('data.id');
        $this->actingAs($fin)->postJson("/api/finance/entries/$rid/settle", [
            'amount' => '3000.00', 'settled_on' => now()->toDateString(),
        ])->assertOk();
        $this->actingAs($fin)->postJson('/api/finance/entries', $this->entryPayload([
            'amount' => '1000.00', 'event_id' => $eventId, 'due_date' => now()->toDateString(),
        ]))->assertCreated();

        $result = $this->actingAs($fin)->getJson("/api/finance/events/$eventId/result")->assertOk();
        $result->assertJsonPath('data.receitaRealizada', '3000.00')
            ->assertJsonPath('data.despesaPrevista', '1000.00')
            ->assertJsonPath('data.saldoRealizado', '3000.00'); // despesa ainda não paga

        $this->actingAs($fin)->getJson('/api/finance/dashboard')->assertOk()
            ->assertJsonStructure(['data' => ['month', 'overdue', 'balances', 'dueBuckets', 'upcoming']]);
    }

    public function test_relatorio_previa_e_export(): void
    {
        $fin = $this->finance();
        $this->actingAs($fin)->postJson('/api/finance/entries', $this->entryPayload())->assertCreated();

        $this->actingAs($fin)->getJson('/api/finance/reports/contas-a-pagar')->assertOk()
            ->assertJsonStructure(['data' => ['columns', 'rows', 'total']]);

        $export = $this->actingAs($fin)->get('/api/finance/reports/contas-a-pagar/xlsx')->assertOk();
        $this->assertStringContainsString('spreadsheet', $export->headers->get('content-type'));

        $this->actingAs($fin)->getJson('/api/finance/reports/inexistente')->assertNotFound();
    }
}
