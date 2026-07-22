<?php

namespace App\Domain\Events\Payments;

use App\Domain\Events\Models\Order;

/**
 * Capacidade opcional de um driver: criar um checkout hospedado (redirect).
 * Segregada do PaymentGatewayContract porque nem todo provedor tem essa forma
 * de cobrança — só quem implementa esta interface expõe o fluxo hospedado.
 */
interface SupportsHostedCheckout
{
    /**
     * Cria um checkout hospedado de cartão para o pedido. A baixa nunca ocorre
     * aqui — chega por webhook (RegisterPayment é o ponto único).
     *
     * $customerData (opcional) pré-preenche o cadastro do comprador na página
     * hospedada (nome, email, cpfCnpj, phoneNumber, postalCode, address,
     * addressNumber, complement, province) — o provedor exige o conjunto
     * completo ou nada.
     */
    public function createCardCheckout(Order $order, int $installments, ?array $customerData = null): HostedCheckout;
}
