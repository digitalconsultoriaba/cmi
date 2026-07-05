<?php

namespace Tests\Feature\Panel;

use App\Domain\Events\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Lifecycle\LifecycleTestCase;

/**
 * US2 — painel do evento: contadores + financeiro + gráficos (spec 009),
 * escopado ao evento, derivado na consulta.
 */
class EventDashboardTest extends LifecycleTestCase
{
    use RefreshDatabase;

    private function admin()
    {
        $user = $this->buyer();
        $user->assignRole(Role::ADMIN);

        return $user;
    }

    private function panel(int $eventId)
    {
        return $this->actingAs($this->admin())
            ->getJson("/api/admin/events/{$eventId}/dashboard");
    }

    public function test_contadores_financeiro_e_por_tipo(): void
    {
        $this->sellableEvent();
        $buyer = $this->buyer();
        // 1 individual + 1 casal pagos (3 pessoas); 1 individual pendente
        $code = $this->buy($buyer, [
            $this->item($this->individual),
            $this->item($this->couple, ['companion_name' => 'Par']),
        ])->json('data.orders.0.code');
        $this->actingAs($buyer)->postJson("/api/orders/{$code}/checkout/card", [
            'token' => 'tok_ok_4242', 'installments' => 1,
        ])->assertOk();
        $this->buy($this->buyer(), [$this->item($this->individual)])->assertCreated();

        $response = $this->panel($this->event->id)->assertOk();

        $response->assertJsonPath('data.counters.capacity', 50)
            ->assertJsonPath('data.counters.registeredTotal', 2)   // por ingresso: individual + casal
            ->assertJsonPath('data.counters.awaitingPayment', 1);

        // Financeiro: previsto = confirmado + a receber
        $confirmed = $response->json('data.financial.confirmed');
        $receivable = $response->json('data.financial.receivable');
        $this->assertSame(
            number_format((float) $confirmed + (float) $receivable, 2, '.', ''),
            $response->json('data.financial.expected')
        );

        // Recorte por tipo de ingresso (no lugar de "por loja")
        $byType = collect($response->json('data.byTicketType'));
        $this->assertSame(1, $byType->firstWhere('type', 'Individual')['count']);
        $this->assertSame(1, $byType->firstWhere('type', 'Casal')['count']); // por ingresso
        $this->assertNotEmpty($response->json('data.ticketsByStatus'));
        $this->assertNotEmpty($response->json('data.inscriptionsByMonth'));
    }

    public function test_evento_sem_vendas_zera_coerente(): void
    {
        // Anônimo barrado pelo auth antes do binding (actingAs ainda não usado)
        $this->getJson('/api/admin/events/1/dashboard')->assertStatus(401);

        $this->sellableEvent();

        $response = $this->panel($this->event->id)->assertOk();

        $response->assertJsonPath('data.counters.registeredTotal', 0)
            ->assertJsonPath('data.counters.present', 0)
            ->assertJsonPath('data.financial.confirmed', '0.00');
        $this->assertSame([], $response->json('data.byTicketType'));
    }

    public function test_reflete_checkin_e_rbac(): void
    {
        [, $order] = $this->paidOrder();

        $before = $this->panel($this->event->id)->assertOk();
        $this->assertSame(0, $before->json('data.counters.present'));

        $gate = $this->buyer();
        $gate->assignRole(Role::GATE);
        $this->actingAs($gate)->postJson('/api/gate/checkin', [
            'code' => $order->tickets->first()->code,
        ])->assertOk();

        $after = $this->panel($this->event->id)->assertOk();
        $this->assertSame(1, $after->json('data.counters.present'));

        // RBAC (gate não é admin) — 401 anônimo é coberto em outros testes,
        // pois actingAs persiste dentro do mesmo teste
        $this->actingAs($gate)->getJson("/api/admin/events/{$this->event->id}/dashboard")
            ->assertStatus(403);
    }
}
