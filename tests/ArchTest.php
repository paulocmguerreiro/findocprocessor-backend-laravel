<?php

declare(strict_types=1);
use App\Features\CategoriaDocumento\Actualizar\ActualizarCategoriaRequest;
use App\Features\CategoriaDocumento\Criar\CriarCategoriaRequest;
use App\Features\CategoriaDocumento\Listar\CampoOrdenacaoCategorias;
use App\Features\Documento\Listar\CampoOrdenacaoDocumentos;
use App\Features\Documento\Reprocessar\ModoReprocessamento;
use App\Features\Entidade\Actualizar\ActualizarEntidadeRequest;
use App\Features\Entidade\ComFlagsEfectivosEmpresaMae;
use App\Features\Entidade\Criar\CriarEntidadeRequest;
use App\Features\Entidade\Listar\CampoOrdenacaoEntidades;
use App\Features\Role\Listar\CampoOrdenacaoRoles;
use App\Features\TipoDocumento\Actualizar\ActualizarTipoDocumentoRequest;
use App\Features\TipoDocumento\Criar\CriarTipoDocumentoRequest;
use App\Features\TipoDocumento\Listar\CampoOrdenacaoTiposDocumento;
use App\Features\Utilizador\Actualizar\ActualizarUtilizadorRequest;
use App\Features\Utilizador\Criar\CriarUtilizadorRequest;
use App\Features\Utilizador\Listar\CampoOrdenacaoUtilizadores;
use App\Http\Controllers\Controller;

arch()->preset()->laravel()->ignoring(['App\Shared\Enums', 'App\Shared\Cache', 'App\Features', 'App\Shared\Exceptions']);
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
        CampoOrdenacaoDocumentos::class,
        ModoReprocessamento::class,
        CampoOrdenacaoUtilizadores::class,
        CriarUtilizadorRequest::class,
        ActualizarUtilizadorRequest::class,
        CriarTipoDocumentoRequest::class,
        ActualizarTipoDocumentoRequest::class,
        CampoOrdenacaoTiposDocumento::class,
    ]);
