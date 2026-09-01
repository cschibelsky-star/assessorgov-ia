<?php

return [
    'asaas' => [
        'api_url' => env('ASAAS_API_URL', 'https://api-sandbox.asaas.com/v3'),
        'api_key' => env('ASAAS_API_KEY'),
        'webhook_token' => env('ASAAS_WEBHOOK_TOKEN'),
        'timeout' => (int) env('ASAAS_TIMEOUT', 15),
    ],
];
