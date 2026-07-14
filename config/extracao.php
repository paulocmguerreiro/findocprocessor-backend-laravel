<?php

declare(strict_types=1);

return [
    'threshold_caracteres' => 50,
    'ttl_lease' => env('EXTRACAO_TTL_LEASE', 300), // segundos — afinado na #98
    'max_tentativas' => 3,
    'camada_local_activa' => filled(env('LLM_LOCAL_URL')) && filled(env('LLM_LOCAL_MODEL')),
    'camada_cloud_activa' => filled(env('LLM_CLOUD_URL')) && filled(env('LLM_CLOUD_MODEL')) && filled(env('LLM_CLOUD_KEY')),
    'ocr' => [
        'dpi' => 300,
        'linguas' => ['por', 'eng'],
    ],
];
