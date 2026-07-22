<?php

namespace Tests\Feature\Payment;

use App\Domain\Events\Payments\AsaasClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Driver ASAAS testado com Http::fake() (validação contra o sandbox oficial é
 * etapa manual do quickstart, dependente da chave). Spec 015.
 */
class AsaasClientTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'payments.asaas.api_key' => 'test-key',
            'payments.asaas.base_url' => 'https://asaas.test/v3',
        ]);
    }

    public function test_cria_checkout_com_header_e_encaminha_payload(): void
    {
        Http::fake([
            'asaas.test/v3/checkouts' => Http::response(['id' => 'chk_1', 'link' => 'https://asaas.test/c/chk_1']),
        ]);

        $res = app(AsaasClient::class)->createCheckout(['billingTypes' => ['CREDIT_CARD']]);

        $this->assertSame('chk_1', $res['id']);
        $this->assertSame('https://asaas.test/c/chk_1', $res['link']);

        Http::assertSent(fn ($request) => $request->hasHeader('access_token', 'test-key')
            && str_contains($request->url(), '/v3/checkouts')
            && $request['billingTypes'] === ['CREDIT_CARD']);
    }

    public function test_lista_pagamentos_por_external_reference(): void
    {
        Http::fake([
            'asaas.test/v3/payments*' => Http::response(['object' => 'list', 'data' => [
                ['id' => 'pay_1', 'status' => 'CONFIRMED'],
            ]]),
        ]);

        $res = app(AsaasClient::class)->listPayments(['externalReference' => 'ORD-XYZ']);

        $this->assertSame('CONFIRMED', $res['data'][0]['status']);
        Http::assertSent(fn ($request) => str_contains($request->url(), '/v3/payments')
            && str_contains($request->url(), 'externalReference=ORD-XYZ')
            && $request->hasHeader('access_token', 'test-key'));
    }
}
