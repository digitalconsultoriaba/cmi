<?php

namespace App\Domain\Events\Payments;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Cliente da API PIX do microsserviço Boletos SICOOB V2 (spec 015). Autenticação
 * por Bearer token (Sanctum). O microsserviço fala com o SICOOB por baixo — o cmi
 * NUNCA toca o SICOOB direto. Credenciais SÓ por config() (constituição, IV).
 */
class BoletosPixClient
{
    /** Cria uma cobrança PIX → { txid, copiaECola, location, status, ... }. */
    public function createCobranca(array $body): array
    {
        return $this->request()
            ->post($this->baseUrl().'/api/pix/cobranca', $body)
            ->throw()
            ->json('data');
    }

    /** Consulta o status de uma cobrança por txid. */
    public function getCobranca(string $txid): array
    {
        return $this->request()
            ->get($this->baseUrl().'/api/pix/cobranca/'.$txid)
            ->throw()
            ->json('data');
    }

    private function request(): PendingRequest
    {
        return Http::withToken((string) config('payments.boletos.token'))
            ->acceptJson()
            ->timeout(15);
    }

    private function baseUrl(): string
    {
        return rtrim((string) config('payments.boletos.base_url'), '/');
    }
}
