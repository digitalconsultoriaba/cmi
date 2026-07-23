<?php

namespace App\Domain\Events\Payments;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Cliente da API ASAAS (v3) — Checkout hospedado de cartão. Autenticação por
 * chave estática no header `access_token` (sem OAuth). Credenciais SÓ por
 * config/env (constituição, IV). Testado com Http::fake(); o sandbox real é
 * etapa manual do quickstart.
 */
class AsaasClient
{
    public function createCheckout(array $body): array
    {
        return $this->request()
            ->post($this->baseUrl().'/checkouts', $body)
            ->throw()
            ->json();
    }

    /**
     * Lista pagamentos (filtros de query, ex.: externalReference). O ASAAS não
     * expõe consulta de checkout por id — o pagamento gerado herda o
     * externalReference do checkout, então é por ele que reconsultamos a baixa.
     */
    public function listPayments(array $query): array
    {
        return $this->request()
            ->get($this->baseUrl().'/payments', $query)
            ->throw()
            ->json();
    }

    /**
     * Consulta um parcelamento (installment) por id — traz `installmentCount`
     * (nº de parcelas) e `installmentValue`. O pagamento parcelado só carrega o
     * id do parcelamento; a contagem vem daqui.
     */
    public function getInstallment(string $id): array
    {
        return $this->request()
            ->get($this->baseUrl().'/installments/'.$id)
            ->throw()
            ->json();
    }

    private function request(): PendingRequest
    {
        return Http::withHeaders(['access_token' => (string) config('payments.asaas.api_key')])
            ->acceptJson()
            ->timeout(15);
    }

    private function baseUrl(): string
    {
        return rtrim((string) config('payments.asaas.base_url'), '/');
    }
}
