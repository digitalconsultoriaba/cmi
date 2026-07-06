<?php

namespace Tests\Feature\Multiday;

use App\Domain\Events\Models\EventDay;
use App\Domain\Events\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
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

    /** Define 2 dias (08:00–18:00) no evento e devolve os dias; opera no Dia 1. */
    protected function twoDays(array $dates = ['2026-08-10', '2026-08-11']): array
    {
        // Vendas sempre abertas (sem janela) para permitir compra no tempo congelado.
        $this->event->update(['sales_start_at' => null, 'sales_end_at' => null]);
        $this->lot->update(['starts_at' => null, 'ends_at' => null]);

        $admin = $this->admin();
        $this->actingAs($admin)->putJson("/api/admin/events/{$this->event->id}/days", [
            'days' => array_map(fn ($d) => ['date' => $d, 'startsAt' => '08:00', 'endsAt' => '18:00'], $dates),
        ])->assertOk();

        $days = EventDay::query()->where('event_id', $this->event->id)
            ->orderBy('day_number')->get()->all();

        // Regra: abre 3h antes do início, encerra na hora final. Dias consecutivos
        // NÃO têm janela comum — opera-se um dia por vez (viaje com operateDay()).
        $this->operateDay($days[0]);

        return $days;
    }

    /** Congela o tempo dentro da janela operável do dia (data 10:00). */
    protected function operateDay(EventDay $day): void
    {
        $date = Carbon::parse($day->event_date)->toDateString();
        $this->travelTo(Carbon::parse($date.' 10:00:00', config('events.timezone')));
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
