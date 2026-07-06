<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

Route::get('/', function () {
    return view('welcome');
});

// Documentação interactiva da API (Swagger UI) — apenas fora de produção.
// Em produção estas rotas não são registadas: nem a UI nem a spec ficam expostas.
if (! app()->isProduction()) {
    Route::view('/docs', 'docs');

    Route::get('/openapi.yaml', fn (): BinaryFileResponse => response()->file(
        base_path('openapi.yaml'),
        ['Content-Type' => 'application/yaml'],
    ));
}
