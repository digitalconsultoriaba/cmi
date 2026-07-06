<?php

namespace Tests\Feature\Multiday;

use App\Domain\Events\Models\EventDay;

/**
 * Spec 012 — duração/dias do evento e compatibilidade 1 dia.
 */
class EventDaysTest extends MultidayTestCase
{
    public function test_evento_nasce_com_um_dia(): void
    {
        $this->sellableEvent();
        $days = EventDay::query()->where('event_id', $this->event->id)->get();
        $this->assertCount(1, $days);
        $this->assertSame(1, (int) $days->first()->day_number);
    }

    public function test_upsert_dois_dias_e_lista(): void
    {
        $this->sellableEvent();
        $admin = $this->admin();

        $this->actingAs($admin)->putJson("/api/admin/events/{$this->event->id}/days", [
            'days' => [
                ['date' => '2026-08-11', 'label' => 'Encerramento'],
                ['date' => '2026-08-10', 'startsAt' => '08:00', 'label' => 'Abertura'],
            ],
        ])->assertOk();

        $list = $this->actingAs($admin)->getJson("/api/admin/events/{$this->event->id}/days")->json('data');
        $this->assertCount(2, $list);
        // Renumerado pela ordem das datas
        $this->assertSame('2026-08-10', $list[0]['date']);
        $this->assertSame(1, $list[0]['dayNumber']);
        $this->assertSame('Abertura', $list[0]['label']);
        $this->assertSame('2026-08-11', $list[1]['date']);
    }

    public function test_datas_distintas_e_maximo_tres(): void
    {
        $this->sellableEvent();
        $admin = $this->admin();

        // datas repetidas → 422
        $this->actingAs($admin)->putJson("/api/admin/events/{$this->event->id}/days", [
            'days' => [['date' => '2026-08-10'], ['date' => '2026-08-10']],
        ])->assertStatus(422);

        // 4 dias → 422
        $this->actingAs($admin)->putJson("/api/admin/events/{$this->event->id}/days", [
            'days' => [['date' => '2026-08-10'], ['date' => '2026-08-11'], ['date' => '2026-08-12'], ['date' => '2026-08-13']],
        ])->assertStatus(422);
    }

    public function test_nao_remove_dia_com_checkin(): void
    {
        $this->sellableEvent();
        [$d1, $d2] = $this->twoDays();
        $admin = $this->admin();

        // presença no Dia 2 (viaja para o Dia 2)
        $code = $this->paidTicketCode();
        $this->operateDay($d2);
        $this->actingAs($this->gate())->postJson('/api/gate/checkin', ['code' => $code, 'day' => $d2->id])->assertOk();

        // tentar reduzir para 1 dia (removeria o Dia 2 com presença) → 409
        $this->actingAs($admin)->putJson("/api/admin/events/{$this->event->id}/days", [
            'days' => [['date' => '2026-08-10']],
        ])->assertStatus(409)->assertJsonPath('type', 'day_has_checkins');
    }
}
