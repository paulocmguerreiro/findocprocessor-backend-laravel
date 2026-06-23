<?php

declare(strict_types=1);
use App\Features\CategoriaDocumento\Actualizar\ActualizarCategoriaRequest;
use App\Features\CategoriaDocumento\Criar\CriarCategoriaRequest;
use App\Features\CategoriaDocumento\Listar\CampoOrdenacaoCategorias;
use App\Features\Entidade\Actualizar\ActualizarEntidadeRequest;
use App\Features\Entidade\ComFlagsEfectivosEmpresaMae;
use App\Features\Entidade\Criar\CriarEntidadeRequest;
use App\Features\Entidade\Listar\CampoOrdenacaoEntidades;
use App\Features\Role\Listar\CampoOrdenacaoRoles;
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

// Enums em PHP são implicitamente final — `final enum` é syntax error.
arch('actions are final')
    ->expect('App\Features')
    ->toBeFinal()
    ->ignoring([
        CriarCategoriaRequest::class,
        ActualizarCategoriaRequest::class,
        CampoOrdenacaoCategorias::class,
        CriarEntidadeRequest::class,
        ActualizarEntidadeRequest::class,
        CampoOrdenacaoEntidades::class,
        ComFlagsEfectivosEmpresaMae::class,
        CampoOrdenacaoRoles::class,
    ]);
