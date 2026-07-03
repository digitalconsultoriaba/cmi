<?php

namespace Tests\Feature\Purchase;

use App\Domain\Events\Models\EventStatus;
use App\Domain\Events\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * US2 — compra em grupo (quickstart §US2).
 */
class PurchaseTest extends PurchaseTestCase
{
    use RefreshDatabase;

    public function test_compra_valida_cria_pedido_pendente_com_snapshot_e_ttl(): void
    {
        $this->sellableEvent();
        $buyer = $this->buyer();

        $response = $this->buy($buyer, [
            $this->item($this->individual, ['participant_name' => 'Ana', 'participant_email' => 'ana@x.com']),
            $this->item($this->individual, ['participant_name' => 'Bia']),
        ])->assertCreated();

        $order = $response->json('data.orders.0');
        $this->assertSame('pending', $order['status']);
        $this->assertSame('400.00', $order['totalAmount'], '2 × preço efetivo do lote (200,00)');
        $this->assertNotNull($order['reservedUntil']);
        $this->assertCount(2, $order['tickets']);
        $this->assertSame('200.00', $order['tickets'][0]['unitPrice']);
        $this->assertStringStartsWith('ORD-', $order['code']);

        // Buyer congelado
        $stored = Order::query()->where('code', $order['code'])->firstOrFail();
        $this->assertSame($buyer->name, $stored->buyer_name);
        $this->assertSame($buyer->email, $stored->buyer_email);

        // TTL do evento (30 min)
        $this->assertEqualsWithDelta(
            now()->addMinutes(30)->timestamp,
            $stored->reserved_until->timestamp,
            5
        );
    }

    public function test_snapshot_nao_acompanha_mudancas_do_catalogo(): void
    {
        $this->sellableEvent();

        $response = $this->buy($this->buyer(), [$this->item($this->individual)])->assertCreated();
        $code = $response->json('data.orders.0.code');

        $this->lot->update(['price_override' => '999.00']);
        $this->individual->update(['price' => '888.00']);

        $order = Order::query()->where('code', $code)->firstOrFail();
        $this->assertSame('200.00', $order->tickets->first()->unit_price);
        $this->assertSame('200.00', $order->total_amount);
    }

    public function test_casal_ocupa_duas_vagas_e_registra_acompanhante(): void
    {
        $this->sellableEvent(['total_capacity' => 10]);

        $this->buy($this->buyer(), [
            $this->item($this->couple, [
                'participant_name' => 'Carlos',
                'companion_name' => 'Diana',
            ]),
        ])->assertCreated();

        $this->assertSame(2, $this->event->fresh()->ticketsSold(), 'casal conta 2 assentos');
        $this->assertSame(
            'Diana',
            $this->event->tickets()->first()->companion_name
        );
    }

    public function test_casal_sem_acompanhante_recusa(): void
    {
        $this->sellableEvent();

        $this->buy($this->buyer(), [$this->item($this->couple)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['items.0.companion_name']);
    }

    public function test_camisa_obrigatoria_quando_o_evento_exige(): void
    {
        $this->sellableEvent(['requires_shirt' => true]);

        $this->buy($this->buyer(), [$this->item($this->individual)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['items.0.shirt_size_id']);
    }

    public function test_guest_sem_login_recebe_401(): void
    {
        $this->sellableEvent();

        $this->postJson('/api/orders', [
            'event_slug' => $this->event->slug,
            'items' => [$this->item($this->individual)],
        ])->assertStatus(401);
    }

    public function test_carrinho_vazio_e_acima_do_limite_recusam(): void
    {
        $this->sellableEvent();
        $buyer = $this->buyer();

        $this->buy($buyer, [])->assertUnprocessable()->assertJsonValidationErrors(['items']);

        $tooMany = array_map(fn () => $this->item($this->individual), range(1, 21));
        $this->buy($buyer, $tooMany)->assertUnprocessable()->assertJsonValidationErrors(['items']);
    }

    public function test_evento_nao_vendavel_recusa_como_conflito(): void
    {
        // Janela encerrada
        $this->sellableEvent([
            'sales_start_at' => now()->subDays(30),
            'sales_end_at' => now()->subDay(),
        ]);
        $this->buy($this->buyer(), [$this->item($this->individual)])
            ->assertStatus(409)->assertJsonPath('type', 'sales_closed');

        // Evento em rascunho
        $this->sellableEvent(['status_id' => EventStatus::idFor(EventStatus::DRAFT)]);
        $this->buy($this->buyer(), [$this->item($this->individual)])
            ->assertStatus(409)->assertJsonPath('type', 'sales_closed');
    }
}
