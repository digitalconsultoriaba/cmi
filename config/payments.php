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
];
