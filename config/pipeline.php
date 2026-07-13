<?php

declare(strict_types=1);

return [
    'reconciliacao_limiar_minutos' => (int) env('PIPELINE_RECONCILIACAO_LIMIAR_MINUTOS', 15),

    // 'host' vazio ou 'port' zero desligam a camada (fail-safe) — porta 0 nunca é válida.
    'malware' => [
        'host' => env('CLAMAV_HOST', ''),
        'port' => (int) env('CLAMAV_PORT', 0),
        'timeout_segundos' => (int) env('CLAMAV_TIMEOUT_SEGUNDOS', 5),
    ],
];
