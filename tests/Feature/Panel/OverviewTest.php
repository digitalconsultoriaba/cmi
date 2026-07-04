<?php

namespace Tests\Feature\Panel;

use App\Domain\Events\Models\Event;
use App\Domain\Events\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Lifecycle\LifecycleTestCase;

/**
 * US2 — painel do módulo: consolidado de todos os eventos (spec 009),
 * derivado na consulta.
 */
class OverviewTest extends LifecycleTestCase
{
    use RefreshDatabase;

    private function admin()
    {
        $user = $this->buyer();
        $user->assignRole(Role::ADMIN);

        return $user;
    }

    public function test_cards_consolidados_e_series(): void
    {
        // Evento vendável (publicado) + 1 casal pago (3 pessoas)
        $this->sellableEvent();
        $buyer = $this->buyer();
        $code = $this->buy($buyer, [
            $this->item($this->individual),
            $this->item($this->couple, ['companion_name' => 'Par']),
        ])->json('data.orders.0.code');
        $this->actingAs($buyer)->postJson("/api/orders/{$code}/checkout/card", [
            'token' => 'tok_ok_4242', 'installments' => 1,
        ])->assertOk();

        // Um segundo evento em rascunho (não publicado, futuro)
        Event::factory()->create(['starts_at' => now()->addDays(30)]);

        $response = $this->actingAs($this->admin())->getJson('/api/admin/overview')->assertOk();

        $response->assertJsonPath('data.cards.events', 2)
            ->assertJsonPath('data.cards.published', 1)
            ->assertJsonPath('data.cards.activeRegistrations', 3); // casal conta 2

        // Série mensal fecha com o total de inscritos
        $series = collect($response->json('data.inscriptionsByMonth'));
        $this->assertSame(3, (int) $series->sum('count'), 'curva fecha com inscritos');
        $this->assertNotEmpty($response->json('data.eventsByStatus'));
    }

    public function test_filtro_por_evento_recorta(): void
    {
        $this->sellableEvent();
        $buyer = $this->buyer();
        $code = $this->buy($buyer, [$this->item($this->individual)])->json('data.orders.0.code');
        $this->actingAs($buyer)->postJson("/api/orders/{$code}/checkout/card", [
            'token' => 'tok_ok_4242', 'installments' => 1,
        ])->assertOk();

        $other = Event::factory()->published()->create();

        $admin = $this->admin();
        $all = $this->actingAs($admin)->getJson('/api/admin/overview')->assertOk();
        $this->assertSame(2, $all->json('data.cards.events'));

        $one = $this->actingAs($admin)
            ->getJson("/api/admin/overview?event={$other->id}")->assertOk();
        $this->assertSame(1, $one->json('data.cards.events'));
        $this->assertSame(0, $one->json('data.cards.activeRegistrations'));
    }

    public function test_overview_e_exclusivo_do_admin(): void
    {
        $this->getJson('/api/admin/overview')->assertStatus(401);

        $this->sellableEvent();

        // Financeiro acessa tudo (spec 009); inscrito comum não
        $treasury = $this->buyer();
        $treasury->assignRole(Role::TREASURY);
        $this->actingAs($treasury)->getJson('/api/admin/overview')->assertOk();

        $this->actingAs($this->buyer())->getJson('/api/admin/overview')->assertStatus(403);
        $this->actingAs($this->admin())->getJson('/api/admin/overview')->assertOk();
    }
}
