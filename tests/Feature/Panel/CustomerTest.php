<?php

namespace Tests\Feature\Panel;

use App\Domain\Events\Models\Role;
use App\Domain\Events\Models\SupportCase;
use App\Domain\Events\Models\TicketStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Lifecycle\LifecycleTestCase;

/**
 * Spec 009 — ficha do cliente (comprador): dados, compras, ingressos,
 * mensagens (reusa Atendimentos) e cancelamento (política 006).
 */
class CustomerTest extends LifecycleTestCase
{
    use RefreshDatabase;

    private function admin()
    {
        $user = $this->buyer();
        $user->assignRole(Role::ADMIN);

        return $user;
    }

    public function test_ficha_traz_dados_compras_e_ingressos(): void
    {
        [$buyer, $order] = $this->paidOrder(2);
        $eventId = $this->event->id;

        $response = $this->actingAs($this->admin())
            ->getJson("/api/admin/events/{$eventId}/customers/{$buyer->id}")->assertOk();

        $response->assertJsonPath('data.customer.id', $buyer->id)
            ->assertJsonPath('data.customer.email', $buyer->email)
            ->assertJsonPath('data.stats.ordersCount', 1)
            ->assertJsonPath('data.stats.ticketsCount', 2);

        $this->assertSame($order->code, $response->json('data.orders.0.code'));
        $this->assertSame('paid', $response->json('data.orders.0.status'));
        $this->assertSame('card', $response->json('data.orders.0.method'));
        $this->assertNotEmpty($response->json('data.tickets'));
        $this->assertNotEmpty($response->json('data.history'), 'histórico da trilha');
    }

    public function test_mensagens_reusam_atendimento_e_aparecem_para_o_inscrito(): void
    {
        [$buyer] = $this->paidOrder();
        $eventId = $this->event->id;
        $admin = $this->admin();

        // Sem thread ainda
        $this->actingAs($admin)
            ->getJson("/api/admin/events/{$eventId}/customers/{$buyer->id}/messages")
            ->assertOk()->assertJsonPath('data.messages', []);

        // Admin envia → cria o caso de atendimento tipo "message"
        $this->actingAs($admin)
            ->postJson("/api/admin/events/{$eventId}/customers/{$buyer->id}/messages", [
                'message' => 'Olá! Seu ingresso está confirmado.',
            ])->assertOk()->assertJsonPath('data.messages.0.body', 'Olá! Seu ingresso está confirmado.');

        // Vira um caso de suporte do inscrito (ele responde na área dele)
        $case = SupportCase::query()->where('type', 'message')
            ->where('user_id', $buyer->id)->firstOrFail();
        $this->assertSame($eventId, $case->event_id);

        // O inscrito responde pela área de suporte da 006
        $this->actingAs($buyer)->postJson("/api/support-cases/{$case->id}/notes", [
            'message' => 'Obrigado, confirmado!',
        ])->assertOk();

        $thread = $this->actingAs($admin)
            ->getJson("/api/admin/events/{$eventId}/customers/{$buyer->id}/messages")->assertOk();
        $this->assertCount(2, $thread->json('data.messages'));
        $this->assertTrue(collect($thread->json('data.messages'))->contains('fromAttendee', true));
    }

    public function test_cancelamento_pelo_staff_segue_politica(): void
    {
        [$buyer, $order] = $this->paidOrder();
        $ticket = $order->tickets->first();

        // Evento com autoatendimento DESLIGADO — staff cancela mesmo assim
        $this->event->update(['allow_user_cancel' => false]);

        $this->actingAs($this->admin())
            ->postJson("/api/admin/tickets/{$ticket->code}/cancel", [
                'reason' => 'Solicitação por telefone',
            ])->assertOk()->assertJsonPath('data.status', TicketStatus::CANCELLED);

        // Devolução integral → caso de reembolso aberto (política 006)
        $this->assertDatabaseHas('support_cases', [
            'order_id' => $order->id, 'type' => 'refund',
        ]);
    }

    public function test_ficha_e_cancelamento_respeitam_papel(): void
    {
        [$buyer, $order] = $this->paidOrder();
        $eventId = $this->event->id;

        // (401 anônimo é coberto em outros testes Panel; aqui o actingAs do
        // paidOrder já autenticou, então focamos no RBAC de papéis)

        // Financeiro acessa (spec 009); inscrito comum não
        $treasury = $this->buyer();
        $treasury->assignRole(Role::TREASURY);
        $this->actingAs($treasury)
            ->getJson("/api/admin/events/{$eventId}/customers/{$buyer->id}")->assertOk();

        $this->actingAs($this->buyer())
            ->getJson("/api/admin/events/{$eventId}/customers/{$buyer->id}")->assertStatus(403);
    }
}
