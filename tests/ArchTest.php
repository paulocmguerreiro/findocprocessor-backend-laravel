<?php

declare(strict_types=1);
use App\Features\CategoriaDocumento\Actualizar\ActualizarCategoriaRequest;
use App\Features\CategoriaDocumento\Criar\CriarCategoriaRequest;
use App\Http\Controllers\Controller;

arch()->preset()->laravel()->ignoring(['App\Shared\Enums', 'App\Features']);
arch()->preset()->security();

arch('strict types')
    ->expect('App')
    ->toUseStrictTypes();

arch('no debug functions')
    ->expect(['dd', 'dump', 'ray', 'var_dump'])
    ->not->toBeUsed();

arch('controllers are final')
    ->expect('App\Http\Controllers')
    ->toBeFinal()
    ->ignoring(Controller::class);

arch('actions are final')
    ->expect('App\Features')
    ->toBeFinal()
    ->ignoring([
        CriarCategoriaRequest::class,
        ActualizarCategoriaRequest::class,
    ]);
