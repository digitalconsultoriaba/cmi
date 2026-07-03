<?php

namespace Tests\Feature\Admin;

use App\Domain\Events\Models\CourtesyVoucher;
use App\Domain\Events\Models\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * US5 — vouchers de cortesia (quickstart §US5).
 */
class VoucherTest extends AdminTestCase
{
    use RefreshDatabase;

    public function test_gera_lote_de_codigos_unicos_nao_sequenciais(): void
    {
        $admin = $this->admin();
        $event = Event::factory()->create();

        $response = $this->actingAs($admin)
            ->postJson("/api/admin/events/{$event->id}/courtesy-vouchers", ['quantity' => 10])
            ->assertCreated();

        $codes = collect($response->json('data'))->pluck('code');
        $this->assertCount(10, $codes);
        $this->assertSame(10, $codes->unique()->count());
        $codes->each(fn ($code) => $this->assertMatchesRegularExpression('/^CTY-[A-Z0-9]{10}$/', $code));

        $statuses = collect($response->json('data'))->pluck('status')->unique();
        $this->assertSame([CourtesyVoucher::AVAILABLE], $statuses->all());
    }

    public function test_quantidade_invalida_recusa(): void
    {
        $event = Event::factory()->create();
        $admin = $this->admin();

        $this->actingAs($admin)
            ->postJson("/api/admin/events/{$event->id}/courtesy-vouchers", ['quantity' => 0])
            ->assertUnprocessable();

        $this->actingAs($admin)
            ->postJson("/api/admin/events/{$event->id}/courtesy-vouchers", ['quantity' => 501])
            ->assertUnprocessable();
    }

    public function test_distribuir_registra_trilha_e_ciclo_so_avanca(): void
    {
        $admin = $this->admin();
        $event = Event::factory()->create();
        $voucher = CourtesyVoucher::query()->create(['event_id' => $event->id]);

        $this->actingAs($admin)
            ->patchJson("/api/admin/events/{$event->id}/courtesy-vouchers/{$voucher->id}/distribute", [
                'note' => 'Para a família Silva',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', CourtesyVoucher::DISTRIBUTED);

        $fresh = $voucher->fresh();
        $this->assertSame($admin->id, $fresh->distributed_by);
        $this->assertNotNull($fresh->distributed_at);
        $this->assertSame('Para a família Silva', $fresh->note);

        // Distribuir de novo → 409 (ciclo só avança)
        $this->actingAs($admin)
            ->patchJson("/api/admin/events/{$event->id}/courtesy-vouchers/{$voucher->id}/distribute")
            ->assertStatus(409);
    }

    public function test_voucher_resgatado_e_intocavel(): void
    {
        $event = Event::factory()->create();
        $voucher = CourtesyVoucher::query()->create([
            'event_id' => $event->id,
            'status' => CourtesyVoucher::REDEEMED,
        ]);

        $this->actingAs($this->admin())
            ->patchJson("/api/admin/events/{$event->id}/courtesy-vouchers/{$voucher->id}/distribute")
            ->assertStatus(409);
    }

    public function test_listagem_filtra_por_situacao(): void
    {
        $admin = $this->admin();
        $event = Event::factory()->create();
        CourtesyVoucher::query()->create(['event_id' => $event->id]);
        CourtesyVoucher::query()->create(['event_id' => $event->id, 'status' => CourtesyVoucher::DISTRIBUTED]);

        $this->actingAs($admin)
            ->getJson("/api/admin/events/{$event->id}/courtesy-vouchers?status=available")
            ->assertOk()->assertJsonCount(1, 'data');

        $this->actingAs($admin)
            ->getJson("/api/admin/events/{$event->id}/courtesy-vouchers")
            ->assertOk()->assertJsonCount(2, 'data');
    }
}
