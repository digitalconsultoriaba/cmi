<?php

namespace Tests\Feature\Multiday;

/**
 * Spec 012 — escopo/papel (403) e validação (422).
 */
class MultidayAccessTest extends MultidayTestCase
{
    public function test_attendee_nao_gerencia_dias(): void
    {
        $this->sellableEvent();
        $attendee = $this->buyer(); // sem papel de equipe
        $this->actingAs($attendee)->getJson("/api/admin/events/{$this->event->id}/days")->assertStatus(403);
    }

    public function test_checkin_multidia_exige_dia(): void
    {
        $this->sellableEvent();
        $this->twoDays();
        $code = $this->paidTicketCode();

        // sem `day` num evento de 2 dias → 422
        $this->actingAs($this->gate())->postJson('/api/gate/checkin', ['code' => $code])->assertStatus(422);
    }

    public function test_reduzir_para_um_dia_sem_presenca_ok(): void
    {
        $this->sellableEvent();
        $this->twoDays();
        $admin = $this->admin();

        // sem check-ins → pode voltar para 1 dia
        $this->actingAs($admin)->putJson("/api/admin/events/{$this->event->id}/days", [
            'days' => [['date' => '2026-08-10']],
        ])->assertOk();

        $this->assertSame(1, $this->event->eventDays()->count());
    }
}
