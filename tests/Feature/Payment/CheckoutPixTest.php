<?php

namespace Tests\Feature\Payment;

use App\Domain\Events\Models\PaymentStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

/**
 * US1 — cobrança Pix (quickstart §US1).
 */
class CheckoutPixTest extends PaymentTestCase
{
    use RefreshDatabase;

    public function test_cria_cobranca_pix_com_qr_e_validade_da_reserva(): void
    {
        [$buyer, $order] = $this->pendingOrder();

        $response = $this->createPixCharge($buyer, $order)->assertCreated();

        $response->assertJsonPath('data.method', 'pix')
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.amount', '200.00');

        $this->assertStringContainsString('br.gov.bcb.pix', $response->json('data.pixQrCode'));
        $this->assertStringContainsString('<svg', $response->json('data.pixQrCodeSvg'));

        // Validade alinhada ao prazo da reserva
        $this->assertEqualsWithDelta(
            Carbon::parse($order->reserved_until)->timestamp,
            Carbon::parse($response->json('data.dueDate'))->timestamp,
            5
        );
    }

    public function test_nova_cobranca_expira_a_anterior_uma_ativa_por_pedido(): void
    {
        [$buyer, $order] = $this->pendingOrder();

        $this->createPixCharge($buyer, $order)->assertCreated();
        $this->createPixCharge($buyer, $order)->assertCreated();

        $statuses = $order->payments()->with('status')->get()->pluck('status.slug');
        $this->assertSame(
            [PaymentStatus::EXPIRED, PaymentStatus::PENDING],
            $statuses->sort()->values()->all()
        );
    }

    public function test_dono_de_outro_pedido_recebe_403_e_anonimo_401(): void
    {
        $this->sellableEvent();
        $owner = $this->buyer();

        // Pedido via service (sem HTTP) para o cheque anônimo ficar limpo
        $orders = app(\App\Domain\Events\Services\TicketPurchaseService::class)
            ->purchase($this->event, $owner, [$this->item($this->individual)]);
        $order = $orders[0];

        $this->postJson("/api/orders/{$order->code}/checkout/pix")->assertStatus(401);

        $this->actingAs($this->buyer())
            ->postJson("/api/orders/{$order->code}/checkout/pix")
            ->assertStatus(403);
    }

    public function test_pedido_fora_do_prazo_ou_meio_desabilitado_recusa(): void
    {
        [$buyer, $order] = $this->pendingOrder();

        // Meio desabilitado
        $this->event->update(['allow_pix' => false]);
        $this->createPixCharge($buyer, $order)
            ->assertStatus(409)->assertJsonPath('type', 'method_disabled');
        $this->event->update(['allow_pix' => true]);

        // Reserva vencida
        Carbon::setTestNow(now()->addHours(2));
        $this->createPixCharge($buyer, $order)
            ->assertStatus(409)->assertJsonPath('type', 'terminal_status');
        Carbon::setTestNow();
    }

    public function test_payment_status_retorna_situacao_do_pedido(): void
    {
        [$buyer, $order] = $this->pendingOrder();
        $this->createPixCharge($buyer, $order);

        $this->actingAs($buyer)
            ->getJson("/api/orders/{$order->code}/payment-status")
            ->assertOk()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.paidAt', null);
    }
}
