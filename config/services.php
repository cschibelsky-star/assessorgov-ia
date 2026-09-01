<?php

return [
    'asaas' => [
        'webhook_token' => env('ASAAS_WEBHOOK_TOKEN'),
    ],

    'pncp' => [
        'base_url' => env('PNCP_BASE_URL', 'https://pncp.gov.br/api/consulta'),
        'timeout' => (int) env('PNCP_TIMEOUT', 20),
        'connect_timeout' => (int) env('PNCP_CONNECT_TIMEOUT', 5),
        'retry_attempts' => (int) env('PNCP_RETRY_ATTEMPTS', 3),
        'retry_sleep_ms' => (int) env('PNCP_RETRY_SLEEP_MS', 750),
    ],
];
