<?php

namespace Tests\Feature\Gate;

use App\Domain\Events\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Lifecycle\LifecycleTestCase;

/**
 * US3 — presentes/ausentes em PESSOAS, com busca (data-model da 007).
 */
class AttendanceTest extends LifecycleTestCase
{
    use RefreshDatabase;

    private function gate()
    {
        $user = $this->buyer();
        $user->assignRole(Role::GATE);

        return $user;
    }

    public function test_contadores_em_pessoas_com_casal_valendo_dois(): void
    {
        // 1 individual pago + 1 casal pago = 3 pessoas esperadas
        $this->sellableEvent();
        $buyer = $this->buyer();
        $code = $this->buy($buyer, [
            $this->item($this->individual, ['participant_name' => 'Solo Da Silva']),
            $this->item($this->couple, [
                'participant_name' => 'Titular Casal',
                'companion_name' => 'Par Casal',
            ]),
        ])->json('data.orders.0.code');
        $this->actingAs($buyer)->postJson("/api/orders/{$code}/checkout/card", [
            'token' => 'tok_ok_4242', 'installments' => 1,
        ])->assertOk();

        $gate = $this->gate();

        $before = $this->actingAs($gate)->getJson('/api/gate/attendance')->assertOk();
        $this->assertSame(3, $before->json('data.expectedPeople'));
        $this->assertSame(0, $before->json('data.presentPeople'));

        // Check-in do casal → presentes = 2
        $coupleTicket = collect($before->json('data.tickets'))
            ->firstWhere('participantName', 'Titular Casal');
        $this->actingAs($gate)->postJson('/api/gate/checkin', ['code' => $coupleTicket['code']])
            ->assertOk();

        $after = $this->actingAs($gate)->getJson('/api/gate/attendance')->assertOk();
        $this->assertSame(2, $after->json('data.presentPeople'));
        $this->assertSame(1, $after->json('data.absentPeople'));

        $present = collect($after->json('data.tickets'))->firstWhere('status', 'used');
        $this->assertNotNull($present['usedAt']);
        $this->assertSame($gate->name, $present['validatedBy']);
    }

    public function test_cancelado_e_transferido_fora_dos_esperados(): void
    {
        [, $order] = $this->paidOrder(2);
        $buyer = \App\Models\User::query()->find($order->buyer_user_id);
        [$first, $second] = $order->tickets;

        // Cancela um; transfere o outro (o novo substitui nos esperados)
        $this->actingAs($buyer)->postJson("/api/tickets/{$first->code}/cancel")->assertOk();
        $this->actingAs($buyer)->postJson("/api/tickets/{$second->code}/transfer", [
            'participant_name' => 'Novo Dono', 'participant_email' => 'novo@x.com',
        ])->assertCreated();

        $response = $this->actingAs($this->gate())->getJson('/api/gate/attendance')->assertOk();

        $codes = collect($response->json('data.tickets'))->pluck('code');
        $this->assertFalse($codes->contains($first->code), 'cancelado fora');
        $this->assertFalse($codes->contains($second->code), 'transferido fora');
        $this->assertSame(1, $response->json('data.expectedPeople'), 'só o novo ingresso');
    }

    public function test_busca_por_participante_acompanhante_e_codigo(): void
    {
        $this->sellableEvent();
        $buyer = $this->buyer();
        $code = $this->buy($buyer, [
            $this->item($this->individual, ['participant_name' => 'Fulano Buscável']),
            $this->item($this->couple, [
                'participant_name' => 'Outro Nome',
                'companion_name' => 'Companheira Única',
            ]),
        ])->json('data.orders.0.code');
        $this->actingAs($buyer)->postJson("/api/orders/{$code}/checkout/card", [
            'token' => 'tok_ok_4242', 'installments' => 1,
        ])->assertOk();
        $gate = $this->gate();

        $byName = $this->actingAs($gate)->getJson('/api/gate/attendance?search=Buscável')->assertOk();
        $this->assertCount(1, $byName->json('data.tickets'));

        $byCompanion = $this->actingAs($gate)->getJson('/api/gate/attendance?search=Companheira')->assertOk();
        $this->assertCount(1, $byCompanion->json('data.tickets'));
        $this->assertSame('Outro Nome', $byCompanion->json('data.tickets.0.participantName'));

        $ticketCode = $byName->json('data.tickets.0.code');
        $byCode = $this->actingAs($gate)->getJson('/api/gate/attendance?search='.$ticketCode)->assertOk();
        $this->assertCount(1, $byCode->json('data.tickets'));
    }

    public function test_attendance_exige_papel(): void
    {
        $this->sellableEvent();

        $this->getJson('/api/gate/attendance')->assertStatus(401);
        $this->actingAs($this->buyer())->getJson('/api/gate/attendance')->assertStatus(403);
    }
}
