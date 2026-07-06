<?php

namespace Tests\Feature\Multiday;

use App\Domain\Events\Models\TicketDayCheckin;
use App\Domain\Events\Models\TicketStatus;

/**
 * Spec 012 — check-in por dia: isolado por dia, único por (ingresso, dia),
 * compatibilidade 1 dia.
 */
class DayCheckinTest extends MultidayTestCase
{
    public function test_presenca_isolada_por_dia(): void
    {
        $this->sellableEvent();
        [$d1, $d2] = $this->twoDays();
        $gate = $this->gate();
        $code = $this->paidTicketCode();

        // Presença no Dia 1 (opera-se no Dia 1 por padrão)
        $this->actingAs($gate)->postJson('/api/gate/checkin', ['code' => $code, 'day' => $d1->id])
            ->assertOk()->assertJsonPath('data.dayNumber', 1);

        // Viaja para o Dia 2 e registra a presença lá (independente)
        $this->operateDay($d2);
        $this->actingAs($gate)->postJson('/api/gate/checkin', ['code' => $code, 'day' => $d2->id])
            ->assertOk()->assertJsonPath('data.dayNumber', 2);

        $this->assertSame(1, TicketDayCheckin::query()->where('event_day_id', $d1->id)->count());
        $this->assertSame(1, TicketDayCheckin::query()->where('event_day_id', $d2->id)->count());
    }

    public function test_duplicidade_no_mesmo_dia_avisa_e_nao_duplica(): void
    {
        $this->sellableEvent();
        [$d1] = $this->twoDays();
        $gate = $this->gate();
        $code = $this->paidTicketCode();

        $this->actingAs($gate)->postJson('/api/gate/checkin', ['code' => $code, 'day' => $d1->id])->assertOk();

        $resp = $this->actingAs($gate)->postJson('/api/gate/checkin', ['code' => $code, 'day' => $d1->id])
            ->assertStatus(409)->assertJsonPath('type', 'already_checked_in_day');
        $this->assertNotNull($resp->json('errors.checkedInAt'));
        $this->assertSame($gate->name, $resp->json('errors.operator'));

        $this->assertSame(1, TicketDayCheckin::query()->where('event_day_id', $d1->id)->count());
    }

    public function test_um_dia_espelha_used(): void
    {
        // Evento de 1 dia (padrão): check-in marca used_at/status used (compat)
        $this->sellableEvent();
        $day = $this->event->eventDays()->first();
        $code = $this->paidTicketCode();

        $this->actingAs($this->gate())->postJson('/api/gate/checkin', ['code' => $code, 'day' => $day->id])->assertOk();

        $ticket = \App\Domain\Events\Models\Ticket::query()->where('code', $code)->first();
        $this->assertSame(TicketStatus::USED, $ticket->status->slug);
        $this->assertNotNull($ticket->used_at);
    }

    public function test_dia_bloqueia_por_evento_errado(): void
    {
        // Um dia de outro evento não serve para um ingresso deste evento
        $this->sellableEvent();
        [$d1] = $this->twoDays();
        $code = $this->paidTicketCode();

        // cria outro evento com dia
        $other = \App\Domain\Events\Models\Event::factory()->published()->create();
        $otherDay = $other->eventDays()->first();

        $this->actingAs($this->gate())->postJson('/api/gate/checkin', ['code' => $code, 'day' => $otherDay->id])
            ->assertStatus(409)->assertJsonPath('type', 'wrong_event');
    }
}
