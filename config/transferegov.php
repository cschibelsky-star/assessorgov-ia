<?php

return [
    'base_url' => env('TRANSFEREGOV_BASE_URL', 'https://api-publica.transferegov.gestao.gov.br'),
    'timeout' => (int) env('TRANSFEREGOV_TIMEOUT', 20),
    'modules' => [
        'parcerias' => '/parcerias',
        'especiais' => '/especiais',
    ],
];
