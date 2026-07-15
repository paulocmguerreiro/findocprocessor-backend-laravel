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
use App\Infrastructure\AI\CamadaIA;
use App\Infrastructure\AI\VeredictoExtracaoIA;
use App\Infrastructure\Malware\AnalisadorMalware;
use App\Infrastructure\Malware\EstadoAnaliseMalware;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;

arch()->preset()->laravel()->ignoring(['App\Shared\Enums', 'App\Shared\Cache', 'App\Features', 'App\Shared\Exceptions', 'App\Infrastructure\Malware', 'App\Infrastructure\Extracao', 'App\Infrastructure\AI']);
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

// Enums em PHP são implicitamente final — `final enum` é syntax error.
arch('infrastructure classes are final')
    ->expect('App\Infrastructure')
    ->toBeFinal()
    ->ignoring([AnalisadorMalware::class, EstadoAnaliseMalware::class, CamadaIA::class, VeredictoExtracaoIA::class]);

// RN-01/CA-02 (#90): todo Job de pipeline disparado a partir de uma Action de
// escrita implementa ShouldQueueAfterCommit — nunca ShouldDispatchAfterCommit
// (interface exclusiva de Events/Broadcasting, ver 04-infra/transactions.md).
arch('jobs implementam ShouldQueueAfterCommit')
    ->expect('App\Jobs')
    ->toImplement(ShouldQueueAfterCommit::class);
