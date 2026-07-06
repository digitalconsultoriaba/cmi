<?php

namespace Tests\Feature\Multiday;

use App\Domain\Events\Models\EventDay;
use App\Domain\Events\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Lifecycle\LifecycleTestCase;

abstract class MultidayTestCase extends LifecycleTestCase
{
    use RefreshDatabase;

    protected function admin(): User
    {
        $u = User::factory()->create();
        $u->assignRole(Role::ADMIN);

        return $u;
    }

    protected function gate(): User
    {
        $u = User::factory()->create();
        $u->assignRole(Role::GATE);

        return $u;
    }

    /** Define 2 dias no evento atual e devolve os dias (Dia 1, Dia 2). */
    protected function twoDays(array $dates = ['2026-08-10', '2026-08-11']): array
    {
        $admin = $this->admin();
        $this->actingAs($admin)->putJson("/api/admin/events/{$this->event->id}/days", [
            'days' => array_map(fn ($d) => ['date' => $d], $dates),
        ])->assertOk();

        return EventDay::query()->where('event_id', $this->event->id)
            ->orderBy('day_number')->get()->all();
    }

    /** Um ingresso pago no evento atual → devolve o código. */
    protected function paidTicketCode(): string
    {
        $buyer = $this->buyer();
        $code = $this->buy($buyer, [$this->item($this->individual)])->json('data.orders.0.code');
        $this->actingAs($buyer)->postJson("/api/orders/{$code}/checkout/card", [
            'token' => 'tok_ok_4242', 'installments' => 1,
        ])->assertOk();

        return \App\Domain\Events\Models\Order::query()->where('code', $code)
            ->first()->tickets->first()->code;
    }
}
