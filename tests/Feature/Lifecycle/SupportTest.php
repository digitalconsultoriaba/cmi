<?php

namespace Tests\Feature\Lifecycle;

use App\Domain\Events\Models\Event;
use App\Domain\Events\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * US4 — suporte: escopo, visibilidade e transições (quickstart §US4).
 */
class SupportTest extends LifecycleTestCase
{
    use RefreshDatabase;

    private function staff(string $role = Role::ADMIN)
    {
        $user = $this->buyer();
        $user->assignRole($role);

        return $user;
    }

    public function test_inscrito_abre_caso_e_conversa(): void
    {
        Event::factory()->published()->create();
        $attendee = $this->buyer();

        $response = $this->actingAs($attendee)->postJson('/api/support-cases', [
            'type' => 'question',
            'subject' => 'Dúvida sobre estacionamento',
            'message' => 'O local do evento tem estacionamento próprio?',
        ])->assertCreated();

        $caseId = $response->json('data.id');
        $this->assertSame('open', $response->json('data.status'));
        $this->assertCount(1, $response->json('data.notes'));

        // Lista só os meus
        $this->actingAs($attendee)->getJson('/api/support-cases')
            ->assertOk()->assertJsonCount(1, 'data');

        // Caso alheio → 403
        $this->actingAs($this->buyer())->getJson("/api/support-cases/{$caseId}")
            ->assertStatus(403);
    }

    public function test_caso_vinculado_a_pedido_e_ingresso(): void
    {
        [$buyer, $order] = $this->paidOrder();
        $ticket = $order->tickets->first();

        $response = $this->actingAs($buyer)->postJson('/api/support-cases', [
            'type' => 'shirt_change',
            'subject' => 'Trocar tamanho da camisa',
            'message' => 'Preciso trocar de M para G.',
            'order_code' => $order->code,
            'ticket_code' => $ticket->code,
        ])->assertCreated();

        $this->assertSame($order->code, $response->json('data.orderCode'));
        $this->assertSame($ticket->code, $response->json('data.ticketCode'));
    }

    public function test_nota_interna_nunca_aparece_para_o_inscrito(): void
    {
        Event::factory()->published()->create();
        $attendee = $this->buyer();
        $admin = $this->staff();

        $caseId = $this->actingAs($attendee)->postJson('/api/support-cases', [
            'type' => 'question', 'subject' => 'Assunto', 'message' => 'Mensagem inicial',
        ])->json('data.id');

        // Admin: uma pública e uma interna
        $this->actingAs($admin)->postJson("/api/admin/support-cases/{$caseId}/notes", [
            'message' => 'Resposta pública ao inscrito', 'visible_to_attendee' => true,
        ])->assertOk();
        $this->actingAs($admin)->postJson("/api/admin/support-cases/{$caseId}/notes", [
            'message' => 'Anotação interna da equipe', 'visible_to_attendee' => false,
        ])->assertOk();

        // Inscrito vê 2 notas (a dele + a pública); a interna NUNCA
        $attendeeView = $this->actingAs($attendee)
            ->getJson("/api/support-cases/{$caseId}")->assertOk();
        $bodies = collect($attendeeView->json('data.notes'))->pluck('body');
        $this->assertCount(2, $bodies);
        $this->assertFalse($bodies->contains('Anotação interna da equipe'));

        // Staff vê as 3
        $staffView = $this->actingAs($admin)
            ->getJson("/api/admin/support-cases/{$caseId}")->assertOk();
        $this->assertCount(3, $staffView->json('data.notes'));
    }

    public function test_transicoes_finalizar_e_reabrir(): void
    {
        Event::factory()->published()->create();
        $attendee = $this->buyer();
        $admin = $this->staff();

        $caseId = $this->actingAs($attendee)->postJson('/api/support-cases', [
            'type' => 'other', 'subject' => 'Assunto', 'message' => 'Mensagem',
        ])->json('data.id');

        $this->actingAs($admin)->postJson("/api/admin/support-cases/{$caseId}/finish")
            ->assertOk()->assertJsonPath('data.status', 'finished');

        // Inscrito responde em caso finalizado → reabre
        $this->actingAs($attendee)->postJson("/api/support-cases/{$caseId}/notes", [
            'message' => 'Ainda tenho a dúvida!',
        ])->assertOk()->assertJsonPath('data.status', 'reopened');
    }

    public function test_fila_acessivel_a_admin_e_treasury_nunca_a_attendee(): void
    {
        Event::factory()->published()->create();
        $attendee = $this->buyer();
        $this->actingAs($attendee)->postJson('/api/support-cases', [
            'type' => 'question', 'subject' => 'A', 'message' => 'B',
        ])->assertCreated();

        $this->actingAs($attendee)->getJson('/api/admin/support-cases')->assertStatus(403);
        $this->actingAs($this->staff(Role::ADMIN))->getJson('/api/admin/support-cases')
            ->assertOk()->assertJsonCount(1, 'data');
        $this->actingAs($this->staff(Role::TREASURY))->getJson('/api/admin/support-cases')
            ->assertOk();

        // Filtro por status
        $this->actingAs($this->staff(Role::ADMIN))
            ->getJson('/api/admin/support-cases?status=finished')
            ->assertOk()->assertJsonCount(0, 'data');
    }
}
