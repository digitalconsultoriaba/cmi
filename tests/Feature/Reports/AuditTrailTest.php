<?php

namespace Tests\Feature\Reports;

use App\Domain\Events\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\Feature\Lifecycle\LifecycleTestCase;

/**
 * US4 — toda ação sensível deixa exatamente UM rastro imutável (spec 008).
 */
class AuditTrailTest extends LifecycleTestCase
{
    use RefreshDatabase;

    private function admin()
    {
        $user = $this->buyer();
        $user->assignRole(Role::ADMIN);

        return $user;
    }

    private function treasury()
    {
        $user = $this->buyer();
        $user->assignRole(Role::TREASURY);

        return $user;
    }

    public function test_confirmacao_por_gateway_registra_com_causer_sistema(): void
    {
        $this->paidOrder(); // cartão síncrono → evidência do gateway, não do comprador

        $log = Activity::query()->where('log_name', 'payment.registered')->get();
        $this->assertCount(1, $log);
        $this->assertNull($log->first()->causer_id, 'gateway = sistema, nunca o comprador');
    }

    public function test_baixa_manual_registra_com_o_operador(): void
    {
        $this->sellableEvent();
        $buyer = $this->buyer();
        $code = $this->buy($buyer, [$this->item($this->individual)])->json('data.orders.0.code');
        $treasury = $this->treasury();

        $this->actingAs($treasury)->postJson("/api/treasury/orders/{$code}/pay-manual", [
            'justification' => 'Dinheiro recebido na secretaria',
        ])->assertOk();

        $log = Activity::query()->where('log_name', 'payment.registered')->firstOrFail();
        $this->assertSame($treasury->id, (int) $log->causer_id);
        $this->assertSame($code, $log->properties['reference']);
    }

    public function test_estorno_cancelamento_e_transferencia_deixam_um_rastro_cada(): void
    {
        [$buyer, $order] = $this->paidOrder(2);
        [$first, $second] = $order->tickets;

        // Cancela (abre caso de reembolso) e transfere
        $this->actingAs($buyer)->postJson("/api/tickets/{$first->code}/cancel")->assertOk();
        $newCode = $this->actingAs($buyer)->postJson("/api/tickets/{$second->code}/transfer", [
            'participant_name' => 'Novo Dono', 'participant_email' => 'novo@x.com',
        ])->json('data.code');

        // Estorno executado pela tesouraria
        $case = \App\Domain\Events\Models\SupportCase::query()->where('type', 'refund')->firstOrFail();
        $treasury = $this->treasury();
        $this->actingAs($treasury)->postJson("/api/treasury/refunds/{$case->id}/execute", [
            'justification' => 'Cancelamento dentro da política',
        ])->assertOk();

        $this->assertSame(1, Activity::query()->where('log_name', 'ticket.cancelled')->count());
        $this->assertSame(1, Activity::query()->where('log_name', 'ticket.transferred')->count());
        $this->assertSame(1, Activity::query()->where('log_name', 'payment.refunded')->count());

        $transfer = Activity::query()->where('log_name', 'ticket.transferred')->first();
        $this->assertSame($second->code, $transfer->properties['reference']);
        $this->assertSame($newCode, $transfer->properties['transferredTo']);
        $this->assertSame($buyer->id, (int) $transfer->causer_id);

        $refund = Activity::query()->where('log_name', 'payment.refunded')->first();
        $this->assertSame($treasury->id, (int) $refund->causer_id);
    }

    public function test_checkin_cortesia_e_config_do_evento(): void
    {
        [, $order] = $this->paidOrder();
        $admin = $this->admin();

        // Check-in
        $gate = $this->buyer();
        $gate->assignRole(Role::GATE);
        $ticketCode = $order->tickets->first()->code;
        $this->actingAs($gate)->postJson('/api/gate/checkin', ['code' => $ticketCode])->assertOk();

        // Emissão de cortesias (lote = 1 ação = 1 registro)
        $this->actingAs($admin)->postJson("/api/admin/events/{$this->event->id}/courtesy-vouchers", [
            'quantity' => 3,
        ])->assertCreated();

        // Alteração de configuração
        $this->actingAs($admin)->putJson("/api/admin/events/{$this->event->id}", [
            'name' => 'Seminário Renomeado',
        ])->assertOk();

        $checkin = Activity::query()->where('log_name', 'ticket.checked_in')->get();
        $this->assertCount(1, $checkin);
        $this->assertSame($gate->id, (int) $checkin->first()->causer_id);
        $this->assertSame($ticketCode, $checkin->first()->properties['reference']);

        $this->assertSame(1, Activity::query()->where('log_name', 'courtesy.issued')->count());

        $updated = Activity::query()->where('log_name', 'event.updated')->firstOrFail();
        $this->assertContains('name', $updated->properties['changed']);
        $this->assertSame($admin->id, (int) $updated->causer_id);
    }

    public function test_resgate_de_voucher_registra_courtesy_redeemed(): void
    {
        $this->sellableEvent(['allow_courtesy' => true]);
        $admin = $this->admin();

        $voucher = $this->actingAs($admin)->postJson(
            "/api/admin/events/{$this->event->id}/courtesy-vouchers",
            ['quantity' => 1, 'ticket_type_id' => $this->individual->id]
        )->json('data.0');
        $this->actingAs($admin)->patchJson(
            "/api/admin/events/{$this->event->id}/courtesy-vouchers/{$voucher['id']}/distribute"
        )->assertOk();

        $buyer = $this->buyer();
        $this->actingAs($buyer)->postJson('/api/orders', [
            'event_slug' => $this->event->slug,
            'voucher_code' => $voucher['code'],
            'participant_name' => 'Convidado Voucher',
        ])->assertCreated();

        $log = Activity::query()->where('log_name', 'courtesy.redeemed')->firstOrFail();
        $this->assertSame($buyer->id, (int) $log->causer_id);
    }

    public function test_evento_cancelado_registra_event_cancelled(): void
    {
        $this->paidOrder();
        $admin = $this->admin();

        $this->actingAs($admin)->postJson("/api/admin/events/{$this->event->id}/cancel", [
            'reason' => 'Força maior',
        ])->assertOk();

        $log = Activity::query()->where('log_name', 'event.cancelled')->get();
        $this->assertCount(1, $log);
        $this->assertSame($admin->id, (int) $log->first()->causer_id);
    }

    public function test_expiracao_automatica_e_atribuida_ao_sistema(): void
    {
        $this->sellableEvent();
        $code = $this->buy($this->buyer(), [$this->item($this->individual)])
            ->json('data.orders.0.code');

        \App\Domain\Events\Models\Order::query()->where('code', $code)
            ->update(['reserved_until' => now()->subMinute()]);

        $this->artisan('orders:expire')->assertSuccessful();

        $log = Activity::query()->where('log_name', 'order.expired')->get();
        $this->assertCount(1, $log);
        $this->assertNull($log->first()->causer_id, 'expiração = sistema');
        $this->assertSame($code, $log->first()->properties['reference']);
    }

    public function test_consulta_pagina_e_filtra_por_acao_e_periodo(): void
    {
        [$buyer, $order] = $this->paidOrder();
        $gate = $this->buyer();
        $gate->assignRole(Role::GATE);
        $this->actingAs($gate)->postJson('/api/gate/checkin', [
            'code' => $order->tickets->first()->code,
        ])->assertOk();

        $admin = $this->admin();

        $all = $this->actingAs($admin)->getJson('/api/admin/audit')->assertOk();
        $this->assertSame('ticket.checked_in', $all->json('data.items.0.action'), 'mais recente primeiro');
        $this->assertArrayHasKey('currentPage', $all->json('data.meta'));
        $this->assertNotEmpty($all->json('data.items.0.description'));
        $this->assertNotEmpty($all->json('data.items.0.createdAt'));

        $filtered = $this->actingAs($admin)
            ->getJson('/api/admin/audit?action=payment.registered')->assertOk();
        $this->assertCount(1, $filtered->json('data.items'));
        $this->assertNull($filtered->json('data.items.0.causer'), 'gateway aparece como sistema');

        // Período futuro → vazio (filtro no fuso do evento)
        $empty = $this->actingAs($admin)
            ->getJson('/api/admin/audit?from=2030-01-01&to=2030-01-31')->assertOk();
        $this->assertCount(0, $empty->json('data.items'));

        $this->actingAs($admin)->getJson('/api/admin/audit?from=2030-02-01&to=2030-01-01')
            ->assertUnprocessable();
    }

    public function test_trilha_e_imutavel_e_exclusiva_do_admin(): void
    {
        // Anônimo primeiro (actingAs persiste dentro do teste)
        $this->getJson('/api/admin/audit')->assertStatus(401);

        $this->sellableEvent();

        // Financeiro acessa tudo (spec 009); portaria e inscrito comum não
        $this->actingAs($this->treasury())->getJson('/api/admin/audit')->assertOk();

        $gate = $this->buyer();
        $gate->assignRole(Role::GATE);
        $this->actingAs($gate)->getJson('/api/admin/audit')->assertStatus(403);

        $this->actingAs($this->buyer())->getJson('/api/admin/audit')->assertStatus(403);

        // Não existe escrita na trilha
        $this->actingAs($this->admin())->postJson('/api/admin/audit')->assertStatus(405);
    }
}
