<?php

declare(strict_types=1);

return [
    'threshold_caracteres' => 50,
    'ttl_lease' => (int) env('EXTRACAO_TTL_LEASE', 300), // segundos — afinado na #98; cast: env() devolve string quando definido, e config()->integer() exige int
    'max_tentativas' => 3,
    // Provider/modelo/ligação agrupados por camada — expostos aqui (em vez de
    // env() directo) para que ClienteExtracaoIAPrism nunca chame env() fora de
    // ficheiro de config. `provider` é o nome de Prism\Prism\Enums\Provider
    // (ex.: 'ollama', 'anthropic', 'openai', 'openrouter') — trocar de
    // provider/modelo é só .env, sem alterações de código (ClienteExtracaoIAPrism
    // passa `url`/`key` como override a Prism::structured()->using(), por cima
    // dos defaults de config/prism.php para esse provider).
    'local' => [
        'provider' => env('LLM_LOCAL_PROVIDER', 'ollama'),
        'modelo' => env('LLM_LOCAL_MODEL'),
        'url' => env('LLM_LOCAL_URL', 'http://localhost:11434'),
        'activa' => filled(env('LLM_LOCAL_URL')) && filled(env('LLM_LOCAL_MODEL')),
    ],
    'cloud' => [
        'provider' => env('LLM_CLOUD_PROVIDER', 'anthropic'),
        'modelo' => env('LLM_CLOUD_MODEL'),
        'url' => env('LLM_CLOUD_URL'),
        'key' => env('LLM_CLOUD_KEY', ''),
        'activa' => filled(env('LLM_CLOUD_URL')) && filled(env('LLM_CLOUD_MODEL')) && filled(env('LLM_CLOUD_KEY')),
    ],
    'ocr' => [
        'dpi' => 300,
        'linguas' => ['por', 'eng'],
    ],
];
