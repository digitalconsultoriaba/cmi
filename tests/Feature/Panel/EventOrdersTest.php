<?php

namespace Tests\Feature\Panel;

use App\Domain\Events\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\Feature\Lifecycle\LifecycleTestCase;

/**
 * Spec 009 — Financeiro do evento: lista de pedidos + baixa pelo admin;
 * comprovante PDF; código do pedido nos inscritos.
 */
class EventOrdersTest extends LifecycleTestCase
{
    use RefreshDatabase;

    private function admin()
    {
        $user = $this->buyer();
        $user->assignRole(Role::ADMIN);

        return $user;
    }

    public function test_lista_pedidos_e_admin_da_baixa(): void
    {
        $this->sellableEvent();
        $buyer = $this->buyer();
        $code = $this->buy($buyer, [$this->item($this->individual)])->json('data.orders.0.code');
        $eventId = $this->event->id;
        $admin = $this->admin();

        $list = $this->actingAs($admin)->getJson("/api/admin/events/{$eventId}/orders")->assertOk();
        $order = collect($list->json('data.items'))->firstWhere('code', $code);
        $this->assertTrue($order['canSettle'], 'pedido pendente pode receber baixa');

        // Baixa pelo admin (não é o comprador) → pedido pago + trilha
        $this->actingAs($admin)->postJson("/api/admin/orders/{$code}/pay-manual", [
            'justification' => 'Pagamento em dinheiro na secretaria',
        ])->assertOk()->assertJsonPath('data.orderStatus', 'paid');

        $this->assertSame(1, Activity::query()->where('log_name', 'payment.registered')->count());

        $after = $this->actingAs($admin)->getJson("/api/admin/events/{$eventId}/orders")->assertOk();
        $this->assertFalse(collect($after->json('data.items'))->firstWhere('code', $code)['canSettle']);
    }

    public function test_comprador_nunca_da_baixa_no_proprio_pedido(): void
    {
        // Admin que também é o comprador → 403 (guarda da constituição)
        $this->sellableEvent();
        $admin = $this->admin();
        $code = $this->buy($admin, [$this->item($this->individual)])->json('data.orders.0.code');

        $this->actingAs($admin)
            ->postJson("/api/admin/orders/{$code}/pay-manual", [
                'justification' => 'Tentativa no próprio pedido',
            ])->assertStatus(403);
    }

    public function test_comprovante_pdf_do_admin(): void
    {
        [, $order] = $this->paidOrder();
        $ticket = $order->tickets->first();

        $response = $this->actingAs($this->admin())
            ->get("/api/admin/tickets/{$ticket->code}/receipt")->assertOk();
        $this->assertStringContainsString('pdf', strtolower($response->headers->get('content-type')));
    }

    public function test_inscritos_trazem_codigo_do_pedido_e_pendencia(): void
    {
        $this->sellableEvent();
        $buyer = $this->buyer();
        $code = $this->buy($buyer, [$this->item($this->individual)])->json('data.orders.0.code');

        $list = $this->actingAs($this->admin())
            ->getJson("/api/admin/events/{$this->event->id}/attendees")->assertOk();
        $item = $list->json('data.items.0');
        $this->assertSame($code, $item['orderCode']);
        $this->assertTrue($item['paymentPending'], 'pedido reservado precisa de baixa');
        $this->assertFalse($item['printable'], 'sem PDF antes de confirmar');
    }
}
