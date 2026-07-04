<?php

namespace Tests\Feature\Reports;

use App\Domain\Events\Models\EventShirtModel;
use App\Domain\Events\Models\EventShirtSize;
use App\Domain\Events\Models\Role;
use App\Domain\Events\Models\SupportCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Lifecycle\LifecycleTestCase;

/**
 * US1 — dashboard 100% derivado (spec 008): números batem com os registros
 * base no instante da consulta, inclusive após estorno.
 */
class DashboardTest extends LifecycleTestCase
{
    use RefreshDatabase;

    private function admin()
    {
        $user = $this->buyer();
        $user->assignRole(Role::ADMIN);

        return $user;
    }

    private function dashboard()
    {
        return $this->actingAs($this->admin())->getJson('/api/admin/dashboard');
    }

    public function test_pessoas_receita_e_quebras_derivadas(): void
    {
        $this->sellableEvent();
        $buyer = $this->buyer();

        // 1 individual + 1 casal pagos (3 pessoas) + 1 individual pendente
        $code = $this->buy($buyer, [
            $this->item($this->individual),
            $this->item($this->couple, ['companion_name' => 'Par Da Silva']),
        ])->json('data.orders.0.code');
        $this->actingAs($buyer)->postJson("/api/orders/{$code}/checkout/card", [
            'token' => 'tok_ok_4242', 'installments' => 1,
        ])->assertOk();

        $pendingBuyer = $this->buyer();
        $this->buy($pendingBuyer, [$this->item($this->individual)])->assertCreated();

        $paidTotal = \App\Domain\Events\Models\Order::query()
            ->where('code', $code)->first()->total_amount;
        $pendingTotal = \App\Domain\Events\Models\Order::query()
            ->where('buyer_user_id', $pendingBuyer->id)->first()->total_amount;

        $response = $this->dashboard()->assertOk();

        $response->assertJsonPath('data.people.confirmed', 3)
            ->assertJsonPath('data.people.capacity', 50)
            ->assertJsonPath('data.revenue.confirmed', $paidTotal)
            ->assertJsonPath('data.revenue.pending', $pendingTotal)
            ->assertJsonPath('data.revenue.projected',
                number_format((float) $paidTotal + (float) $pendingTotal, 2, '.', ''));

        // Por forma: 1 pagamento de cartão com o total pago
        $byMethod = collect($response->json('data.byMethod'));
        $this->assertSame(1, $byMethod->firstWhere('method', 'card')['count']);
        $this->assertSame($paidTotal, $byMethod->firstWhere('method', 'card')['amount']);

        // Por lote: receita = Σ preço dos elegíveis do lote
        $byLot = collect($response->json('data.byLot'));
        $this->assertSame($paidTotal, $byLot->firstWhere('lot', '1º lote')['revenue']);
    }

    public function test_baixa_manual_com_desconto_conta_o_valor_recebido(): void
    {
        $this->sellableEvent();
        $buyer = $this->buyer();
        $code = $this->buy($buyer, [$this->item($this->individual)])->json('data.orders.0.code');

        $treasury = $this->buyer();
        $treasury->assignRole(Role::TREASURY);
        $this->actingAs($treasury)->postJson("/api/treasury/orders/{$code}/pay-manual", [
            'amount' => '150.00',
            'justification' => 'Desconto autorizado pela organização',
        ])->assertOk();

        // O recebido (150,00), nunca o nominal do pedido (200,00) — FR-005
        $this->dashboard()->assertOk()
            ->assertJsonPath('data.revenue.confirmed', '150.00');
    }

    public function test_estorno_reflete_imediatamente(): void
    {
        [$buyer, $order] = $this->paidOrder();
        $total = $order->total_amount;

        $this->dashboard()->assertOk()
            ->assertJsonPath('data.revenue.confirmed', $total)
            ->assertJsonPath('data.revenue.refunded', '0.00');

        // Cancela (abre caso integral) e a tesouraria executa o estorno
        $this->actingAs($buyer)
            ->postJson("/api/tickets/{$order->tickets->first()->code}/cancel")->assertOk();
        $case = SupportCase::query()->where('type', 'refund')->firstOrFail();
        $treasury = $this->buyer();
        $treasury->assignRole(Role::TREASURY);
        $this->actingAs($treasury)->postJson("/api/treasury/refunds/{$case->id}/execute", [
            'justification' => 'Cancelamento dentro da política',
        ])->assertOk();

        // Recarga reflete na hora — nada em cache mente (invariante 7)
        $this->dashboard()->assertOk()
            ->assertJsonPath('data.revenue.confirmed', '0.00')
            ->assertJsonPath('data.revenue.refunded', $total)
            ->assertJsonPath('data.people.confirmed', 0);
    }

    public function test_grade_de_camisas_por_pessoa_fecha_com_o_total(): void
    {
        $this->sellableEvent();

        $model = EventShirtModel::factory()->create([
            'event_id' => $this->event->id, 'label' => 'Tradicional',
        ]);
        $sizeM = EventShirtSize::factory()->create([
            'shirt_model_id' => $model->id, 'event_id' => $this->event->id, 'label' => 'M',
        ]);
        $sizeG = EventShirtSize::factory()->create([
            'shirt_model_id' => $model->id, 'event_id' => $this->event->id, 'label' => 'G',
        ]);

        $buyer = $this->buyer();
        $code = $this->buy($buyer, [
            // individual com M; casal com titular G e acompanhante M; individual SEM camisa
            $this->item($this->individual, [
                'shirt_model_id' => $model->id, 'shirt_size_id' => $sizeM->id,
            ]),
            $this->item($this->couple, [
                'companion_name' => 'Par Com Camisa',
                'shirt_model_id' => $model->id, 'shirt_size_id' => $sizeG->id,
                'companion_shirt_model_id' => $model->id, 'companion_shirt_size_id' => $sizeM->id,
            ]),
            $this->item($this->individual),
        ])->json('data.orders.0.code');
        $this->actingAs($buyer)->postJson("/api/orders/{$code}/checkout/card", [
            'token' => 'tok_ok_4242', 'installments' => 1,
        ])->assertOk();

        $shirts = $this->dashboard()->assertOk()->json('data.shirts');

        $grid = collect($shirts['grid']);
        $this->assertSame(2, $grid->first(fn ($g) => $g['size'] === 'M')['count'], 'individual + acompanhante');
        $this->assertSame(1, $grid->first(fn ($g) => $g['size'] === 'G')['count']);
        $this->assertSame(1, $grid->first(fn ($g) => $g['size'] === null)['count'], 'não informado');

        // Invariante 1: a grade FECHA com as pessoas confirmadas
        $this->assertSame(4, $shirts['totalPeople']);
        $this->assertSame(4, (int) $grid->sum('count'));
    }

    public function test_evento_sem_vendas_mostra_zeros_coerentes(): void
    {
        $this->sellableEvent();

        $response = $this->dashboard()->assertOk();

        $response->assertJsonPath('data.people.confirmed', 0)
            ->assertJsonPath('data.revenue.confirmed', '0.00')
            ->assertJsonPath('data.revenue.projected', '0.00')
            ->assertJsonPath('data.shirts.totalPeople', 0);
        $this->assertSame([], $response->json('data.byMethod'));
    }

    public function test_dashboard_e_exclusivo_do_admin(): void
    {
        // Anônimo primeiro (actingAs persiste dentro do teste)
        $this->getJson('/api/admin/dashboard')->assertStatus(401);

        $this->sellableEvent();

        $treasury = $this->buyer();
        $treasury->assignRole(Role::TREASURY);
        $this->actingAs($treasury)->getJson('/api/admin/dashboard')->assertStatus(403);

        $gate = $this->buyer();
        $gate->assignRole(Role::GATE);
        $this->actingAs($gate)->getJson('/api/admin/dashboard')->assertStatus(403);

        $this->actingAs($this->buyer())->getJson('/api/admin/dashboard')->assertStatus(403);

        $this->dashboard()->assertOk();
    }
}
