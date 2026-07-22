<?php

namespace Tests\Feature\Payment;

use App\Domain\Events\Models\Order;
use Illuminate\Support\Facades\Http;
use Tests\Feature\Checkout\CheckoutTestCase;

/**
 * Checkout hospedado de cartão (ASAAS): o endpoint público de cartão devolve
 * uma URL de redirect e cria um pagamento pendente — a baixa só vem no webhook.
 * Spec 015.
 */
class AsaasCheckoutTest extends CheckoutTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'payments.card_driver' => 'asaas',
            'payments.asaas.api_key' => 'test-key',
            'payments.asaas.base_url' => 'https://asaas.test/v3',
            'payments.asaas.frontend_url' => 'https://front.test',
            'payments.asaas.max_installments' => 12,
        ]);
    }

    private function pendingOrderCode(): string
    {
        $this->seminarEvent(['allow_card' => true]);

        return $this->postJson('/api/public/orders', $this->guestPayload([$this->item()]))
            ->assertCreated()
            ->json('data.order.code');
    }

    public function test_parcelado_retorna_redirect_cria_payment_e_envia_payload_correto(): void
    {
        Http::fake([
            'asaas.test/v3/checkouts' => Http::response(['id' => 'chk_1', 'link' => 'https://asaas.test/pay/chk_1']),
        ]);

        $code = $this->pendingOrderCode();

        $this->postJson("/api/public/orders/{$code}/checkout/card", ['installments' => 3])
            ->assertOk()
            ->assertJsonPath('data.redirectUrl', 'https://asaas.test/pay/chk_1');

        $order = Order::query()->where('code', $code)->firstOrFail();

        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'provider' => 'asaas',
            'method' => 'card',
            'provider_charge_id' => 'chk_1',
            'installments' => 3,
        ]);

        // Pedido segue pendente até o webhook confirmar.
        $this->assertSame('pending', $order->fresh()->status->slug);

        // Só cartão, parcelamento e correlação por externalReference (order.code).
        Http::assertSent(fn ($request) => str_contains($request->url(), '/v3/checkouts')
            && $request['billingTypes'] === ['CREDIT_CARD']
            && $request['chargeTypes'] === ['DETACHED', 'INSTALLMENT']
            && $request['installment']['maxInstallmentCount'] === 3
            && $request['externalReference'] === $code);
    }

    public function test_uma_parcela_usa_cobranca_unica_detached(): void
    {
        Http::fake([
            'asaas.test/v3/checkouts' => Http::response(['id' => 'chk_2', 'link' => 'https://asaas.test/pay/chk_2']),
        ]);

        $code = $this->pendingOrderCode();

        $this->postJson("/api/public/orders/{$code}/checkout/card", ['installments' => 1])->assertOk();

        Http::assertSent(fn ($request) => str_contains($request->url(), '/v3/checkouts')
            && $request['chargeTypes'] === ['DETACHED']
            && ! isset($request['installment']));
    }

    public function test_customer_data_pre_preenche_e_snapshota_cpf(): void
    {
        Http::fake([
            'asaas.test/v3/checkouts' => Http::response(['id' => 'chk_3', 'link' => 'https://asaas.test/pay/chk_3']),
        ]);

        $code = $this->pendingOrderCode();

        $this->postJson("/api/public/orders/{$code}/checkout/card", [
            'installments' => 1,
            'customerData' => [
                'name' => 'Comprador X', 'email' => 'x@ex.com', 'cpfCnpj' => '111.444.777-35',
                'phoneNumber' => '(27) 99999-0000', 'postalCode' => '29000-000',
                'address' => 'Av. Central', 'addressNumber' => '100', 'province' => 'Centro',
            ],
        ])->assertOk();

        // CPF/telefone/CEP vão só com dígitos; celular vira `mobilePhone`; CPF snapshotado.
        Http::assertSent(fn ($request) => str_contains($request->url(), '/v3/checkouts')
            && $request['customerData']['cpfCnpj'] === '11144477735'
            && $request['customerData']['mobilePhone'] === '27999990000'
            && $request['customerData']['postalCode'] === '29000000'
            && $request['customerData']['province'] === 'Centro');

        $this->assertDatabaseHas('orders', ['code' => $code, 'buyer_document' => '11144477735']);
    }

    public function test_customer_data_incompleto_recusa_422(): void
    {
        $code = $this->pendingOrderCode();

        // customerData presente porém sem CPF/endereço → validação required_with.
        $this->postJson("/api/public/orders/{$code}/checkout/card", [
            'installments' => 1,
            'customerData' => ['name' => 'Só Nome'],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['customerData.cpfCnpj', 'customerData.address']);
    }

    public function test_backend_normaliza_mascaras_e_valida_formato(): void
    {
        $code = $this->pendingOrderCode();

        // CPF com dígitos insuficientes (mesmo mascarado) → 422 no backend.
        $this->postJson("/api/public/orders/{$code}/checkout/card", [
            'installments' => 1,
            'customerData' => [
                'name' => 'C X', 'cpfCnpj' => '111.444.777', 'phoneNumber' => '(27) 99999-0000',
                'postalCode' => '01310-100', 'address' => 'Av', 'addressNumber' => '1', 'province' => 'Centro',
            ],
        ])->assertUnprocessable()->assertJsonValidationErrors(['customerData.cpfCnpj']);
    }
}
