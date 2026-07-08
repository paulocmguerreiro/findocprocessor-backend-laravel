<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS)
    |--------------------------------------------------------------------------
    |
    | A API é stateless e autentica por Bearer token (Sanctum) — não usa
    | cookies de sessão — por isso `supports_credentials` fica a `false`.
    | As origens permitidas vêm de `CORS_ALLOWED_ORIGINS` (lista separada por
    | vírgulas); por defeito só o frontend Angular em desenvolvimento.
    |
    */

    'paths' => ['api/*'],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'],

    'allowed_origins' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('CORS_ALLOWED_ORIGINS', 'http://localhost:4200')),
    ))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];
