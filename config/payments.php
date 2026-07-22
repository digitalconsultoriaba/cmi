<?php

return [
    // Drivers atrás do PaymentGatewayContract (constituição, IV).
    // 'fake' é o default de dev/teste; 'sicoob' entra por env quando houver credenciais.
    'pix_driver' => env('PAYMENTS_PIX_DRIVER', 'fake'),
    'card_driver' => env('PAYMENTS_CARD_DRIVER', 'fake'),

    'sicoob' => [
        'base_url' => env('SICOOB_SANDBOX', true)
            ? 'https://sandbox.sicoob.com.br/sicoob/sandbox'
            : 'https://api.sicoob.com.br',
        'auth_url' => env('SICOOB_AUTH_URL', 'https://auth.sicoob.com.br/auth/realms/cooperado/protocol/openid-connect/token'),
        'client_id' => env('SICOOB_CLIENT_ID'),
        'cert_path' => env('SICOOB_CERT_PATH'),
        'cert_key_path' => env('SICOOB_CERT_KEY_PATH'),
        'scopes' => 'cob.read cob.write cobv.read cobv.write pix.read',
        'webhook_secret' => env('SICOOB_WEBHOOK_SECRET'),
    ],

    'card' => [
        'webhook_secret' => env('CARD_WEBHOOK_SECRET'),
    ],

    // PIX via microsserviço Boletos SICOOB V2 (spec 015). O microsserviço fala
    // com o SICOOB (OAuth/mTLS); o cmi só consome a API dele com Bearer token.
    'boletos' => [
        'base_url' => env('BOLETOS_API_URL', 'http://host.docker.internal:18100'),
        'token' => env('BOLETOS_API_TOKEN'),
        'pix_expiration' => (int) env('BOLETOS_PIX_EXPIRACAO', 3600),
        // Webhook de pagamento (doc v1.1.0 §5.1): URL pública HTTPS deste app que
        // recebe o aviso de pagamento (enviada como `notificationUrl` na cobrança;
        // vazio = só polling). `notify_secret` valida a assinatura HMAC do aviso.
        'notify_url' => env('BOLETOS_PIX_NOTIFY_URL'),
        'notify_secret' => env('SICOOB_PIX_NOTIFY_SECRET'),
    ],

    // ASAAS — Checkout hospedado de cartão (spec 015). Sandbox por env.
    'asaas' => [
        'base_url' => env('ASAAS_SANDBOX', true)
            ? 'https://api-sandbox.asaas.com/v3'
            : 'https://api.asaas.com/v3',
        'api_key' => env('ASAAS_API_KEY'),
        'webhook_secret' => env('ASAAS_WEBHOOK_SECRET'),
        'max_installments' => (int) env('ASAAS_MAX_INSTALLMENTS', 12),
        // Base do frontend para montar as URLs de retorno (callback) do checkout.
        'frontend_url' => env('FRONTEND_URL', env('APP_URL', 'http://localhost:5173')),
    ],
];
