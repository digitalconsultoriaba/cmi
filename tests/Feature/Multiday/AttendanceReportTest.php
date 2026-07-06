<?php

namespace Tests\Feature\Multiday;

/**
 * Spec 012 — relatório de presença por dia + consolidado + individual.
 */
class AttendanceReportTest extends MultidayTestCase
{
    public function test_relatorio_por_dia_e_consolidado(): void
    {
        $this->sellableEvent();
        [$d1, $d2] = $this->twoDays();
        $gate = $this->gate();

        // 2 ingressos: A presente nos 2 dias; B só no Dia 1
        $a = $this->paidTicketCode();
        $b = $this->paidTicketCode();

        $this->actingAs($gate)->postJson('/api/gate/checkin', ['code' => $a, 'day' => $d1->id])->assertOk();
        $this->actingAs($gate)->postJson('/api/gate/checkin', ['code' => $a, 'day' => $d2->id])->assertOk();
        $this->actingAs($gate)->postJson('/api/gate/checkin', ['code' => $b, 'day' => $d1->id])->assertOk();

        $report = $this->actingAs($this->admin())
            ->getJson("/api/admin/events/{$this->event->id}/attendance-report")->assertOk()->json('data');

        $this->assertSame(2, $report['totalRegistered']);
        // Dia 1: 2 presentes; Dia 2: 1 presente
        $this->assertSame(2, $report['byDay'][0]['present']);
        $this->assertSame(1, $report['byDay'][1]['present']);
        // Consolidado: 1 em todos os dias, 1 parcial, 0 nenhum
        $this->assertSame(1, $report['consolidated']['allDays']);
        $this->assertSame(1, $report['consolidated']['partial']);
        $this->assertSame(0, $report['consolidated']['none']);

        // Individual: cada ingresso com 2 dias
        $this->assertCount(2, $report['individual'][0]['days']);
    }
}
