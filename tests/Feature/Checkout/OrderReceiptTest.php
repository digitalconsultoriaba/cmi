<?php

namespace Tests\Feature\Checkout;

use App\Domain\Events\Models\Order;
use App\Domain\Events\Models\OrderStatus;
use App\Notifications\PaymentConfirmedPtBr;

/** Comprovante de compra em PDF: download + anexo no e-mail (spec 015). */
class OrderReceiptTest extends CheckoutTestCase
{
    private function order(string $status, ?string $document = '11144477735'): string
    {
        $this->seminarEvent();
        $code = $this->postJson('/api/public/orders', $this->guestPayload([$this->item()]))
            ->assertCreated()->json('data.order.code');
        Order::query()->where('code', $code)->update([
            'status_id' => OrderStatus::idFor($status),
            'buyer_document' => $document,
        ]);

        return $code;
    }

    public function test_pedido_pago_gera_pdf(): void
    {
        $code = $this->order(OrderStatus::PAID);

        $response = $this->get("/api/public/orders/{$code}/receipt");

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('content-type'));
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_pedido_pendente_recusa_comprovante(): void
    {
        $code = $this->order(OrderStatus::PENDING);

        $this->get("/api/public/orders/{$code}/receipt")->assertStatus(409);
    }

    public function test_email_de_confirmacao_anexa_comprovante(): void
    {
        $code = $this->order(OrderStatus::PAID);
        $order = Order::query()->where('code', $code)
            ->with(['event', 'tickets.ticketType', 'buyerUser'])->firstOrFail();

        $mail = (new PaymentConfirmedPtBr($order))->toMail($order->buyerUser);

        $this->assertCount(1, $mail->rawAttachments);
        $this->assertSame('comprovante-'.$code.'.pdf', $mail->rawAttachments[0]['name']);
        $this->assertStringStartsWith('%PDF', $mail->rawAttachments[0]['data']);
    }
}
