<?php

namespace Tests\Feature\Admin;

use App\Domain\Events\Models\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * US6 — patrocínios e parcelas (quickstart §US6).
 */
class SponsorshipTest extends AdminTestCase
{
    use RefreshDatabase;

    private function createSponsorship(Event $event, string $total = '1000.00', int $count = 3): array
    {
        return $this->actingAs($this->admin())
            ->postJson("/api/admin/events/{$event->id}/sponsorships", [
                'company_name' => 'Empresa Apoiadora',
                'total_amount' => $total,
                'installments_count' => $count,
            ])->assertCreated()->json('data');
    }

    public function test_criacao_gera_parcelas_que_somam_o_total(): void
    {
        $event = Event::factory()->create();
        $data = $this->createSponsorship($event, '1000.00', 3);

        $this->assertCount(3, $data['installments']);
        $sum = collect($data['installments'])->sum(fn ($i) => (float) $i['amount']);
        $this->assertEqualsWithDelta(1000.00, $sum, 0.001);
        $this->assertSame([1, 2, 3], collect($data['installments'])->pluck('number')->all());
        $this->assertSame('pending', $data['status']);
    }

    public function test_baixa_recalcula_status_e_registra_trilha(): void
    {
        $admin = $this->admin();
        $event = Event::factory()->create();
        $data = $this->createSponsorship($event);
        $id = $data['id'];

        $partial = $this->actingAs($admin)
            ->postJson("/api/admin/events/{$event->id}/sponsorships/{$id}/installments/1/pay", [
                'method' => 'pix',
            ])->assertOk();

        $this->assertSame('partial', $partial->json('data.status'));
        $this->assertSame('paid', $partial->json('data.installments.0.status'));
        $this->assertNotNull($partial->json('data.installments.0.paidAt'));

        $this->actingAs($admin)
            ->postJson("/api/admin/events/{$event->id}/sponsorships/{$id}/installments/2/pay")
            ->assertOk();
        $paid = $this->actingAs($admin)
            ->postJson("/api/admin/events/{$event->id}/sponsorships/{$id}/installments/3/pay")
            ->assertOk();

        $this->assertSame('paid', $paid->json('data.status'));
    }

    public function test_parcela_paga_rejeita_nova_baixa(): void
    {
        $event = Event::factory()->create();
        $data = $this->createSponsorship($event);

        $this->actingAs($this->admin())
            ->postJson("/api/admin/events/{$event->id}/sponsorships/{$data['id']}/installments/1/pay")
            ->assertOk();

        $this->actingAs($this->admin())
            ->postJson("/api/admin/events/{$event->id}/sponsorships/{$data['id']}/installments/1/pay")
            ->assertStatus(409)->assertJsonPath('type', 'already_paid');
    }

    public function test_cancelamento_preserva_parcelas_e_bloqueia_baixa(): void
    {
        $event = Event::factory()->create();
        $data = $this->createSponsorship($event);

        $this->actingAs($this->admin())
            ->postJson("/api/admin/events/{$event->id}/sponsorships/{$data['id']}/cancel")
            ->assertOk()->assertJsonPath('data.status', 'cancelled')
            ->assertJsonCount(3, 'data.installments');

        $this->actingAs($this->admin())
            ->postJson("/api/admin/events/{$event->id}/sponsorships/{$data['id']}/installments/1/pay")
            ->assertStatus(409);
    }

    public function test_parcelas_invalidas_recusam(): void
    {
        $event = Event::factory()->create();

        $this->actingAs($this->admin())
            ->postJson("/api/admin/events/{$event->id}/sponsorships", [
                'company_name' => 'X', 'total_amount' => '100.00', 'installments_count' => 0,
            ])->assertUnprocessable()->assertJsonValidationErrors(['installments_count']);
    }
}
