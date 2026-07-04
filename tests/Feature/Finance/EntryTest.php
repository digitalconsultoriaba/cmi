<?php

namespace Tests\Feature\Finance;

use App\Domain\Events\Models\FinancialEntry;
use App\Domain\Events\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * US1 — lançar e situar contas a pagar/receber (spec 010).
 */
class EntryTest extends FinanceTestCase
{
    use RefreshDatabase;

    public function test_cria_a_pagar_e_a_receber_com_e_sem_evento(): void
    {
        $fin = $this->finance();
        $this->sellableEvent();

        // A pagar vinculada a evento
        $this->actingAs($fin)->postJson('/api/finance/entries', $this->entryPayload([
            'event_id' => $this->event->id,
        ]))->assertCreated()
            ->assertJsonPath('data.direction', 'payable')
            ->assertJsonPath('data.status', 'open')
            ->assertJsonPath('data.event.id', $this->event->id);

        // A receber administrativa (sem evento)
        $this->actingAs($fin)->postJson('/api/finance/entries', [
            'direction' => 'receivable', 'description' => 'Receita avulsa',
            'amount' => '500.00', 'due_date' => now()->addDays(5)->toDateString(),
        ])->assertCreated()->assertJsonPath('data.event', null);
    }

    public function test_valor_zero_ou_negativo_recusado(): void
    {
        $fin = $this->finance();

        $this->actingAs($fin)->postJson('/api/finance/entries', $this->entryPayload(['amount' => '0']))
            ->assertStatus(422);
        $this->actingAs($fin)->postJson('/api/finance/entries', $this->entryPayload(['amount' => '-10']))
            ->assertStatus(422);
    }

    public function test_vencido_derivado_pela_data(): void
    {
        $fin = $this->finance();
        $id = $this->actingAs($fin)->postJson('/api/finance/entries', $this->entryPayload([
            'due_date' => now()->subDays(3)->toDateString(),
        ]))->json('data.id');

        $entry = FinancialEntry::query()->find($id);
        $this->assertSame('overdue', $entry->status());
        $this->assertSame('Vencido', $entry->statusLabel());
    }

    public function test_listagem_filtra_e_pagina(): void
    {
        $fin = $this->finance();
        foreach (range(1, 3) as $i) {
            $this->actingAs($fin)->postJson('/api/finance/entries', $this->entryPayload([
                'description' => "Despesa $i",
            ]))->assertCreated();
        }
        $this->actingAs($fin)->postJson('/api/finance/entries', $this->entryPayload([
            'direction' => 'receivable', 'category_id' => $this->anyCategory('income'),
        ]))->assertCreated();

        $payables = $this->actingAs($fin)->getJson('/api/finance/entries?direction=payable')->assertOk();
        $this->assertSame(3, $payables->json('data.total'));

        $page = $this->actingAs($fin)->getJson('/api/finance/entries?perPage=25&page=1')->assertOk();
        $this->assertArrayHasKey('lastPage', $page->json('data'));
    }

    public function test_rbac(): void
    {
        $this->getJson('/api/finance/entries')->assertStatus(401);

        $this->actingAs($this->buyer())->getJson('/api/finance/entries')->assertStatus(403);

        $gate = $this->buyer();
        $gate->assignRole(Role::GATE);
        $this->actingAs($gate)->getJson('/api/finance/entries')->assertStatus(403);

        $this->actingAs($this->finance())->getJson('/api/finance/entries')->assertOk();
        $this->actingAs($this->adminUser())->getJson('/api/finance/entries')->assertOk();
    }
}
