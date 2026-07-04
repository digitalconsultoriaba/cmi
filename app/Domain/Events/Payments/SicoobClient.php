<?php

namespace App\Domain\Events\Payments;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Cliente da API Pix/Boleto do Sicoob (v3): OAuth2 client_credentials +
 * mTLS com certificado A1. Credenciais SÓ por env (constituição, IV).
 * Testado com Http::fake(); sandbox real é etapa manual do quickstart.
 */
class SicoobClient
{
    private const TOKEN_CACHE_KEY = 'sicoob:access_token';

    public function createPixCharge(string $txid, array $body): array
    {
        return $this->request()
            ->put($this->baseUrl()."/pix/api/v2/cob/{$txid}", $body)
            ->throw()
            ->json();
    }

    public function createHybridBoleto(array $body): array
    {
        // Boleto híbrido: "hibrido": true → linha digitável + QR Pix na mesma cobrança
        return $this->request()
            ->post($this->baseUrl().'/cobranca-bancaria/v3/boletos', array_merge($body, ['hibrido' => true]))
            ->throw()
            ->json();
    }

    public function getPixCharge(string $txid): array
    {
        return $this->request()
            ->get($this->baseUrl()."/pix/api/v2/cob/{$txid}")
            ->throw()
            ->json();
    }

    public function cancelPixCharge(string $txid): void
    {
        $this->request()
            ->patch($this->baseUrl()."/pix/api/v2/cob/{$txid}", ['status' => 'REMOVIDA_PELO_USUARIO_RECEBEDOR'])
            ->throw();
    }

    public static function newTxid(): string
    {
        // txid Pix: 26–35 alfanuméricos
        return Str::lower(Str::random(32));
    }

    private function request(): PendingRequest
    {
        $request = Http::withToken($this->accessToken())
            ->acceptJson()
            ->timeout(15);

        // mTLS: certificado A1 fora do VCS (paths por env)
        if ($cert = config('payments.sicoob.cert_path')) {
            $request = $request->withOptions([
                'cert' => $cert,
                'ssl_key' => config('payments.sicoob.cert_key_path'),
            ]);
        }

        return $request;
    }

    private function accessToken(): string
    {
        return Cache::remember(self::TOKEN_CACHE_KEY, now()->addMinutes(4), function () {
            $response = Http::asForm()
                ->timeout(15)
                ->post((string) config('payments.sicoob.auth_url'), [
                    'grant_type' => 'client_credentials',
                    'client_id' => config('payments.sicoob.client_id'),
                    'scope' => config('payments.sicoob.scopes'),
                ]);

            if ($response->failed()) {
                throw new RuntimeException(
                    'Sicoob: falha de autenticação (verifique certificado A1/client_id): '
                    .$response->status()
                );
            }

            return (string) $response->json('access_token');
        });
    }

    private function baseUrl(): string
    {
        return rtrim((string) config('payments.sicoob.base_url'), '/');
    }
}
