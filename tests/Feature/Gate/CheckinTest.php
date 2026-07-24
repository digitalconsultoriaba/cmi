<?php

namespace Tests\Feature\Gate;

use App\Domain\Events\Models\EventStatus;
use App\Domain\Events\Models\Role;
use App\Domain\Events\Models\TicketStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Lifecycle\LifecycleTestCase;

/**
 * US1 — a matriz de validação completa (data-model da 007).
 */
class CheckinTest extends LifecycleTestCase
{
    use RefreshDatabase;

    private function gate()
    {
        $user = $this->buyer();
        $user->assignRole(Role::GATE);

        return $user;
    }

    private function scan($operator, string $code)
    {
        return $this->actingAs($operator)->postJson('/api/gate/checkin', ['code' => $code]);
    }

    public function test_ingresso_confirmado_marca_utilizado_com_trilha(): void
    {
        [, $order] = $this->paidOrder();
        $ticket = $order->tickets->first();
        $gate = $this->gate();

        $response = $this->scan($gate, $ticket->code)->assertOk();

        $response->assertJsonPath('data.participantName', $ticket->participant_name)
            ->assertJsonPath('data.seats', 1);
        $this->assertNotNull($response->json('data.usedAt'));

        $fresh = $ticket->fresh();
        $this->assertSame(TicketStatus::USED, $fresh->status->slug);
        $this->assertSame($gate->id, $fresh->validated_by);
    }

    public function test_codigo_normalizado_minusculas_e_espacos(): void
    {
        [, $order] = $this->paidOrder();
        $ticket = $order->tickets->first();

        $this->scan($this->gate(), '  '.strtolower($ticket->code).'  ')->assertOk();
    }

    public function test_aceita_url_do_qr_extraindo_o_codigo(): void
    {
        [, $order] = $this->paidOrder();
        $ticket = $order->tickets->first();

        // O QR do ingresso aponta para a URL pública /validar/{code} — a portaria
        // pode escanear a URL inteira e o check-in extrai o código.
        $this->scan($this->gate(), 'https://cmi.glmees.org.br/validar/'.$ticket->code)
            ->assertOk()
            ->assertJsonPath('data.code', $ticket->code);
    }

    public function test_casal_vale_duas_pessoas_com_acompanhante(): void
    {
        $this->sellableEvent();
        $buyer = $this->buyer();
        $code = $this->buy($buyer, [
            $this->item($this->couple, ['companion_name' => 'Acompanhante Par']),
        ])->json('data.orders.0.code');
        $this->actingAs($buyer)->postJson("/api/orders/{$code}/checkout/card", [
            'token' => 'tok_ok_4242', 'installments' => 1,
        ])->assertOk();

        $ticket = \App\Domain\Events\Models\Order::query()->where('code', $code)
            ->first()->tickets->first();

        $this->scan($this->gate(), $ticket->code)
            ->assertOk()
            ->assertJsonPath('data.seats', 2)
            ->assertJsonPath('data.companionName', 'Acompanhante Par');
    }

    public function test_ja_utilizado_recusa_com_horario_e_operador(): void
    {
        [, $order] = $this->paidOrder();
        $ticket = $order->tickets->first();
        $gate = $this->gate();

        $this->scan($gate, $ticket->code)->assertOk();

        // Segunda validação (qualquer operador) → exatamente uma entrada
        // (spec 012 emenda: check-in por dia → "já possui check-in neste dia")
        $response = $this->scan($this->gate(), $ticket->code)
            ->assertStatus(409)
            ->assertJsonPath('type', 'already_checked_in_day');

        $this->assertNotNull($response->json('errors.checkedInAt'));
        $this->assertSame($gate->name, $response->json('errors.operator'));
        $this->assertSame(
            1,
            $order->tickets()->whereNotNull('used_at')->count(),
            'nunca dupla entrada'
        );
    }

    public function test_cancelado_transferido_e_nao_pago_recusam_com_o_motivo(): void
    {
        // Não pago (reservado)
        $this->sellableEvent(['allow_user_cancel' => true, 'allow_transfer' => true]);
        $buyer = $this->buyer();
        $pendingCode = $this->buy($buyer, [$this->item($this->individual)])
            ->json('data.orders.0.tickets.0.code');
        $this->scan($this->gate(), $pendingCode)
            ->assertStatus(409)->assertJsonPath('type', 'not_paid');

        // Pago → transfere → antigo recusa com o código novo
        [, $order] = $this->paidOrder();
        $original = $order->tickets->first();
        $newCode = $this->actingAs(\App\Models\User::query()->find($order->buyer_user_id))
            ->postJson("/api/tickets/{$original->code}/transfer", [
                'participant_name' => 'Novo', 'participant_email' => 'novo@x.com',
            ])->json('data.code');

        $this->scan($this->gate(), $original->code)
            ->assertStatus(409)
            ->assertJsonPath('type', 'ticket_transferred')
            ->assertJsonPath('errors.transferredToCode', $newCode);

        // O novo passa
        $this->scan($this->gate(), $newCode)->assertOk();

        // Cancelado
        [, $order2] = $this->paidOrder();
        $cancelled = $order2->tickets->first();
        $this->actingAs(\App\Models\User::query()->find($order2->buyer_user_id))
            ->postJson("/api/tickets/{$cancelled->code}/cancel")->assertOk();
        $this->scan($this->gate(), $cancelled->code)
            ->assertStatus(409)->assertJsonPath('type', 'ticket_cancelled');
    }

    public function test_inexistente_e_evento_cancelado(): void
    {
        $this->sellableEvent();
        $gate = $this->gate();

        $this->scan($gate, 'TCK-NAOEXISTE1')->assertNotFound();
        $this->scan($gate, '')->assertUnprocessable();

        // Evento cancelado recusa qualquer ingresso
        [, $order] = $this->paidOrder();
        $this->event->update(['status_id' => EventStatus::idFor(EventStatus::CANCELLED)]);
        $this->scan($gate, $order->tickets->first()->code)
            ->assertStatus(409)->assertJsonPath('type', 'event_cancelled');
    }

    public function test_recusa_nunca_altera_estado(): void
    {
        $this->sellableEvent();
        $code = $this->buy($this->buyer(), [$this->item($this->individual)])
            ->json('data.orders.0.tickets.0.code');

        $this->scan($this->gate(), $code)->assertStatus(409); // not_paid

        $ticket = \App\Domain\Events\Models\Ticket::query()->where('code', $code)->first();
        $this->assertSame(TicketStatus::RESERVED, $ticket->status->slug);
        $this->assertNull($ticket->used_at);
    }

    public function test_rbac_da_portaria(): void
    {
        // Anônimo primeiro (o middleware auth barra antes de qualquer validação)
        $this->postJson('/api/gate/checkin', ['code' => 'TCK-QUALQUER01'])->assertStatus(401);

        [, $order] = $this->paidOrder();
        $code = $order->tickets->first()->code;

        $this->actingAs($this->buyer())
            ->postJson('/api/gate/checkin', ['code' => $code])->assertStatus(403);

        $admin = $this->buyer();
        $admin->assignRole(Role::ADMIN);
        $this->actingAs($admin)->postJson('/api/gate/checkin', ['code' => $code])->assertOk();
    }
}
