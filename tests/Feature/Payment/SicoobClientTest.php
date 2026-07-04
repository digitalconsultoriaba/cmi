<?php

namespace Tests\Feature\Payment;

use App\Domain\Events\Payments\SicoobClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Polish — driver Sicoob real testado com Http::fake() (validação contra o
 * sandbox oficial é etapa manual do quickstart, dependente de credenciais).
 */
class SicoobClientTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::forget('sicoob:access_token');
        config([
            'payments.sicoob.client_id' => 'test-client',
            'payments.sicoob.auth_url' => 'https://auth.sicoob.test/token',
            'payments.sicoob.base_url' => 'https://api.sicoob.test',
        ]);
    }

    public function test_autentica_e_cria_cobranca_pix(): void
    {
        Http::fake([
            'auth.sicoob.test/*' => Http::response(['access_token' => 'token-123', 'expires_in' => 300]),
            'api.sicoob.test/pix/api/v2/cob/*' => Http::response([
                'txid' => 'abc123',
                'status' => 'ATIVA',
                'pixCopiaECola' => '00020126...sicoob...6304ABCD',
            ]),
        ]);

        $client = app(SicoobClient::class);
        $response = $client->createPixCharge('abc123', [
            'calendario' => ['expiracao' => 1800],
            'valor' => ['original' => '200.00'],
        ]);

        $this->assertSame('ATIVA', $response['status']);
        $this->assertStringContainsString('sicoob', $response['pixCopiaECola']);

        // Token OAuth2 obtido via client_credentials e reutilizado (cache)
        Http::assertSent(fn ($request) => str_contains($request->url(), 'auth.sicoob.test')
            && $request['grant_type'] === 'client_credentials');
        $client->getPixCharge('abc123');
        $this->assertSame(
            1,
            collect(Http::recorded())->filter(fn ($pair) => str_contains($pair[0]->url(), 'auth.'))->count(),
            'token cacheado — uma autenticação só'
        );
    }

    public function test_consulta_cobranca_e_mapeia_status(): void
    {
        Http::fake([
            'auth.sicoob.test/*' => Http::response(['access_token' => 'token-123']),
            'api.sicoob.test/pix/api/v2/cob/pago' => Http::response([
                'status' => 'CONCLUIDA',
                'pix' => [['valor' => '200.00', 'horario' => now()->toISOString()]],
            ]),
        ]);

        $response = app(SicoobClient::class)->getPixCharge('pago');

        $this->assertSame('CONCLUIDA', $response['status']);
        $this->assertSame('200.00', $response['pix'][0]['valor']);
    }

    public function test_falha_de_autenticacao_gera_erro_claro(): void
    {
        Http::fake([
            'auth.sicoob.test/*' => Http::response(['error' => 'invalid_client'], 401),
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/certificado A1|autenticação/');

        app(SicoobClient::class)->getPixCharge('qualquer');
    }
}
