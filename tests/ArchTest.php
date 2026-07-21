<?php

declare(strict_types=1);
use App\Console\Commands\Extracao\EtapaExtracaoCommand;
use App\Features\CategoriaDocumento\Actualizar\ActualizarCategoriaRequest;
use App\Features\CategoriaDocumento\Criar\CriarCategoriaRequest;
use App\Features\CategoriaDocumento\Listar\CampoOrdenacaoCategorias;
use App\Features\Documento\Operacoes\Reprocessar\ModoReprocessamento;
use App\Features\Documento\Pesquisa\Listar\CampoOrdenacaoDocumentos;
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
use App\Infrastructure\AI\ClienteIAInterface;
use App\Infrastructure\AI\VeredictoExtracaoIA;
use App\Infrastructure\Malware\AnalisadorMalwareInterface;
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
    ->ignoring([AnalisadorMalwareInterface::class, EstadoAnaliseMalware::class, CamadaIA::class, VeredictoExtracaoIA::class, ClienteIAInterface::class]);

// RN-01/CA-02 (#90): todo Job de pipeline disparado a partir de uma Action de
// escrita implementa ShouldQueueAfterCommit — nunca ShouldDispatchAfterCommit
// (interface exclusiva de Events/Broadcasting, ver 04-infra/transactions.md).
arch('jobs implementam ShouldQueueAfterCommit')
    ->expect('App\Jobs')
    ->toImplement(ShouldQueueAfterCommit::class);

// Convenção decidida via /ajusta-workflow (2026-07-21, ver 02-shared/convencoes-nomenclatura.md):
// substitui o prefixo Contrato<Nome> por sufixo <Nome>Interface.
arch('interfaces têm sufixo Interface')
    ->expect('App')
    ->interfaces()
    ->toHaveSuffix('Interface');

// Isenção: App\Http\Controllers e App\Console\Commands já são obrigados pelo preset Laravel a
// terminar em Controller/Command respectivamente (toda a hierarquia, incl. bases abstractas) —
// acrescentar Abstract violaria essa regra (ver nota em convencoes-nomenclatura.md).
arch('classes abstractas têm sufixo Abstract')
    ->expect('App')
    ->abstractClasses()
    ->toHaveSuffix('Abstract')
    ->ignoring([Controller::class, EtapaExtracaoCommand::class]);
