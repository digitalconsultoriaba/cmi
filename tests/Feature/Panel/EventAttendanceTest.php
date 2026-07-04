<?php

namespace Tests\Feature\Panel;

use App\Domain\Events\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\Feature\Lifecycle\LifecycleTestCase;

/**
 * US3 — check-in escopado por evento + presença manual pela lista, reusando o
 * ponto único (spec 009).
 */
class EventAttendanceTest extends LifecycleTestCase
{
    use RefreshDatabase;

    private function admin()
    {
        $user = $this->buyer();
        $user->assignRole(Role::ADMIN);

        return $user;
    }

    public function test_contadores_escopados_e_presenca_manual(): void
    {
        [, $order] = $this->paidOrder(2);
        $eventId = $this->event->id;
        $admin = $this->admin();

        $before = $this->actingAs($admin)
            ->getJson("/api/admin/events/{$eventId}/attendance")->assertOk();
        $before->assertJsonPath('data.counters.purchased', 2)
            ->assertJsonPath('data.counters.present', 0)
            ->assertJsonPath('data.counters.presentPct', 0);

        // Presença manual pela lista = mesmo ponto de check-in (POST /gate/checkin)
        $ticketCode = $order->tickets->first()->code;
        $this->actingAs($admin)->postJson('/api/gate/checkin', ['code' => $ticketCode])->assertOk();

        $after = $this->actingAs($admin)
            ->getJson("/api/admin/events/{$eventId}/attendance")->assertOk();
        $after->assertJsonPath('data.counters.present', 1)
            ->assertJsonPath('data.counters.presentPct', 50);

        // Trilha idêntica a um check-in por código (spec 008)
        $this->assertSame(1, Activity::query()
            ->where('log_name', 'ticket.checked_in')->count());

        $present = collect($after->json('data.items'))->firstWhere('present', true);
        $this->assertSame($ticketCode, $present['code']);
        $this->assertNotNull($present['usedAt']);
    }

    public function test_casal_conta_duas_pessoas_e_busca(): void
    {
        $this->sellableEvent();
        $buyer = $this->buyer();
        $code = $this->buy($buyer, [
            $this->item($this->couple, [
                'participant_name' => 'Titular Casal', 'companion_name' => 'Par Casal',
            ]),
        ])->json('data.orders.0.code');
        $this->actingAs($buyer)->postJson("/api/orders/{$code}/checkout/card", [
            'token' => 'tok_ok_4242', 'installments' => 1,
        ])->assertOk();

        $admin = $this->admin();
        $eventId = $this->event->id;

        $this->actingAs($admin)->getJson("/api/admin/events/{$eventId}/attendance")
            ->assertOk()->assertJsonPath('data.counters.purchased', 2); // casal = 2

        // Busca por acompanhante
        $byCompanion = $this->actingAs($admin)
            ->getJson("/api/admin/events/{$eventId}/attendance?search=Par+Casal")->assertOk();
        $this->assertCount(1, $byCompanion->json('data.items'));
    }

    public function test_nao_elegivel_recusa_pela_mesma_regua(): void
    {
        // Pedido reservado (não pago) → check-in recusa "not_paid"
        $this->sellableEvent();
        $code = $this->buy($this->buyer(), [$this->item($this->individual)])
            ->json('data.orders.0.tickets.0.code');

        $this->actingAs($this->admin())->postJson('/api/gate/checkin', ['code' => $code])
            ->assertStatus(409)->assertJsonPath('type', 'not_paid');
    }

    public function test_attendance_exige_papel(): void
    {
        $this->sellableEvent();
        $eventId = $this->event->id;

        $this->getJson("/api/admin/events/{$eventId}/attendance")->assertStatus(401);
        $this->actingAs($this->buyer())
            ->getJson("/api/admin/events/{$eventId}/attendance")->assertStatus(403);
    }
}
