<?php

namespace Tests\Feature\Reports;

use App\Domain\Events\Models\Payment;
use App\Domain\Events\Models\Role;
use App\Domain\Events\Models\SupportCase;
use App\Domain\Events\Services\SponsorshipService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Feature\Lifecycle\LifecycleTestCase;

/**
 * US2 — consolidado financeiro com filtro de período no FUSO DO EVENTO
 * (spec 008): visão de caixa — o que entrou e o que saiu no período.
 */
class FinanceTest extends LifecycleTestCase
{
    use RefreshDatabase;

    private function treasury()
    {
        $user = $this->buyer();
        $user->assignRole(Role::TREASURY);

        return $user;
    }

    private function finance(string $query = '')
    {
        return $this->actingAs($this->treasury())->getJson('/api/treasury/finance'.$query);
    }

    public function test_total_e_a_soma_das_formas(): void
    {
        // Um pagamento de cartão + uma baixa manual
        [, $order] = $this->paidOrder();
        $manualBuyer = $this->buyer();
        $manualCode = $this->buy($manualBuyer, [$this->item($this->individual)])
            ->json('data.orders.0.code');
        $treasury = $this->treasury();
        $this->actingAs($treasury)->postJson("/api/treasury/orders/{$manualCode}/pay-manual", [
            'justification' => 'Dinheiro recebido na secretaria',
        ])->assertOk();

        $response = $this->actingAs($treasury)->getJson('/api/treasury/finance')->assertOk();

        $byMethod = collect($response->json('data.byMethod'));
        $sum = $byMethod->reduce(fn ($carry, $row) => bcadd($carry, $row['amount'], 2), '0.00');

        $this->assertCount(2, $byMethod); // card + manual
        $this->assertSame($sum, $response->json('data.total.amount'), 'invariante 2');
        $this->assertSame(2, $response->json('data.total.count'));
    }

    public function test_filtro_de_periodo_no_fuso_do_evento(): void
    {
        [, $order] = $this->paidOrder();

        // Pago às 23h30 de 30/jun no Brasil = 02h30 de 1º/jul em UTC:
        // o caixa de JUNHO é o dono desse pagamento (FR-011)
        Payment::query()->whereKey($order->payments()->first()->id)->update([
            'paid_at' => Carbon::parse('2026-06-30 23:30', 'America/Sao_Paulo')->utc(),
        ]);

        $treasury = $this->treasury();

        $june = $this->actingAs($treasury)
            ->getJson('/api/treasury/finance?month=6&year=2026')->assertOk();
        $this->assertSame(1, $june->json('data.total.count'), 'entra no mês brasileiro');

        $july = $this->actingAs($treasury)
            ->getJson('/api/treasury/finance?month=7&year=2026')->assertOk();
        $this->assertSame(0, $july->json('data.total.count'));

        $range = $this->actingAs($treasury)
            ->getJson('/api/treasury/finance?from=2026-06-30&to=2026-06-30')->assertOk();
        $this->assertSame(1, $range->json('data.total.count'));

        $this->actingAs($treasury)
            ->getJson('/api/treasury/finance?from=2026-07-02&to=2026-07-01')
            ->assertUnprocessable();
        $this->actingAs($treasury)
            ->getJson('/api/treasury/finance?month=13&year=2026')
            ->assertUnprocessable();
    }

    public function test_pago_e_estornado_no_periodo_aparece_nas_duas_secoes(): void
    {
        [$buyer, $order] = $this->paidOrder();
        $total = $order->total_amount;

        $this->actingAs($buyer)
            ->postJson("/api/tickets/{$order->tickets->first()->code}/cancel")->assertOk();
        $case = SupportCase::query()->where('type', 'refund')->firstOrFail();
        $treasury = $this->treasury();
        $this->actingAs($treasury)->postJson("/api/treasury/refunds/{$case->id}/execute", [
            'justification' => 'Cancelamento dentro da política',
        ])->assertOk();

        $response = $this->actingAs($treasury)->getJson('/api/treasury/finance')->assertOk();

        // Visão de caixa: entrou E saiu no período (invariante 4); líquido zera
        $this->assertSame($total, $response->json('data.total.amount'));
        $this->assertSame($total, $response->json('data.refunds.amount'));
        $this->assertSame(1, $response->json('data.refunds.count'));
        $this->assertSame('0.00', $response->json('data.net'));
    }

    public function test_pendentes_sao_fotografia_e_patrocinio_vencido_destaca(): void
    {
        $this->sellableEvent();
        $this->buy($this->buyer(), [$this->item($this->individual)])->assertCreated();

        // Patrocínio: 2 parcelas de 1000, primeira vencida há um mês; paga a 2ª
        $sponsorship = app(SponsorshipService::class)->createWithInstallments($this->event, [
            'company_name' => 'Patrocinadora X',
            'total_amount' => '2000.00',
            'installments_count' => 2,
            'first_due_date' => now()->subMonth()->toDateString(),
        ]);
        app(SponsorshipService::class)->payInstallment(
            $sponsorship->installments->firstWhere('number', 2), []
        );

        $response = $this->finance()->assertOk();

        $this->assertSame(1, $response->json('data.pendingOrders.count'));
        $this->assertSame('1000.00', $response->json('data.sponsorships.received'));
        $this->assertSame('1000.00', $response->json('data.sponsorships.receivable'));
        $this->assertSame(1, $response->json('data.sponsorships.overdue.count'));
        $this->assertSame('1000.00', $response->json('data.sponsorships.overdue.amount'));
    }

    public function test_financeiro_e_da_tesouraria_e_do_admin(): void
    {
        // Anônimo primeiro (actingAs persiste dentro do teste)
        $this->getJson('/api/treasury/finance')->assertStatus(401);

        $this->sellableEvent();

        $admin = $this->buyer();
        $admin->assignRole(Role::ADMIN);
        $this->actingAs($admin)->getJson('/api/treasury/finance')->assertOk();

        $this->finance()->assertOk();

        $gate = $this->buyer();
        $gate->assignRole(Role::GATE);
        $this->actingAs($gate)->getJson('/api/treasury/finance')->assertStatus(403);

        $this->actingAs($this->buyer())->getJson('/api/treasury/finance')->assertStatus(403);
    }
}
