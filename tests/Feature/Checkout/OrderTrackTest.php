<?php

namespace Tests\Feature\Checkout;

use App\Domain\Events\Models\Order;

/** Acompanhar pedidos por CPF (spec 015). */
class OrderTrackTest extends CheckoutTestCase
{
    private function orderWithDocument(string $document): string
    {
        $this->seminarEvent();
        $code = $this->postJson('/api/public/orders', $this->guestPayload([$this->item()]))
            ->assertCreated()->json('data.order.code');
        Order::query()->where('code', $code)->update(['buyer_document' => $document]);

        return $code;
    }

    public function test_acha_pedidos_por_cpf_com_ou_sem_mascara(): void
    {
        $code = $this->orderWithDocument('11144477735');

        $this->postJson('/api/public/orders/track', ['document' => '111.444.777-35'])
            ->assertOk()->assertJsonPath('data.0.code', $code);

        $this->postJson('/api/public/orders/track', ['document' => '11144477735'])
            ->assertOk()->assertJsonPath('data.0.code', $code);
    }

    public function test_pedido_guest_persiste_cpf_do_comprador(): void
    {
        // CPF enviado (mascarado) na criação do pedido é normalizado e gravado,
        // deixando a inscrição rastreável por CPF — inclusive a gratuita.
        $this->seminarEvent();
        $code = $this->postJson('/api/public/orders', $this->guestPayload(
            [$this->item()],
            ['document' => '111.444.777-35'],
        ))->assertCreated()->json('data.order.code');

        $this->assertDatabaseHas('orders', ['code' => $code, 'buyer_document' => '11144477735']);

        $this->postJson('/api/public/orders/track', ['document' => '11144477735'])
            ->assertOk()->assertJsonPath('data.0.code', $code);
    }

    public function test_cpf_sem_pedido_retorna_vazio(): void
    {
        $this->orderWithDocument('11144477735');

        $this->postJson('/api/public/orders/track', ['document' => '52998224725'])
            ->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_documento_curto_nao_consulta(): void
    {
        $this->postJson('/api/public/orders/track', ['document' => '123'])
            ->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_nao_vaza_pii_do_comprador(): void
    {
        $code = $this->orderWithDocument('11144477735');

        $this->postJson('/api/public/orders/track', ['document' => '11144477735'])
            ->assertOk()
            ->assertJsonMissingPath('data.0.buyerName')
            ->assertJsonMissingPath('data.0.buyerDocument')
            ->assertJsonPath('data.0.code', $code);
    }
}
