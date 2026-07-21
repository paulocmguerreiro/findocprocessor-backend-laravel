<?php

declare(strict_types=1);

use App\Features\Documento\Processamento\ProcessarAnaliseCloudDocumentoAction;
use App\Infrastructure\AI\CamadaIA;
use App\Infrastructure\AI\ClienteIAInterface;
use App\Infrastructure\AI\ResultadoExtracaoIA;
use App\Models\Documento;
use App\Models\Entidade;
use App\Models\ExtracaoDocumento;
use App\Models\TipoDocumento;
use App\Shared\Enums\EstadoDocumento;
use App\Shared\Enums\PosicaoEmpresaMae;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Storage::fake('enviado');
    Storage::fake('processado');
    Storage::fake('perigoso');
    Storage::fake('erro');
    config(['extracao.cloud.activa' => true]);
    app()->instance(ClienteIAInterface::class, Mockery::mock(ClienteIAInterface::class));
});

function documentoAnaliseCloud(string $texto = 'texto do parser'): Documento
{
    $documento = Documento::factory()->analiseCloud()->create([
        'id_responsavel' => criarAdmin()->id,
        'nome_ficheiro_original' => 'fatura.pdf',
    ]);
    Storage::disk('enviado')->put($documento->nome_ficheiro_storage, 'conteudo');
    ExtracaoDocumento::factory()->for($documento, 'documento')->create(['texto_extraido' => $texto, 'extracao_tentativas' => 0]);

    return $documento;
}

function fingirClienteIaCloud(ResultadoExtracaoIA $resultado): void
{
    app()->instance(ClienteIAInterface::class, Mockery::mock(ClienteIAInterface::class, function ($mock) use ($resultado): void {
        $mock->shouldReceive('extrair')->once()->with(Mockery::type('string'), CamadaIA::Cloud)->andReturn($resultado);
    }));
}

it('camada cloud inactiva → Erro (sem contar tentativa)', function (): void {
    config(['extracao.cloud.activa' => false]);
    $documento = documentoAnaliseCloud();

    $resultado = app(ProcessarAnaliseCloudDocumentoAction::class)->handle();

    expect($resultado?->estado)->toBe(EstadoDocumento::Erro);
    $this->assertDatabaseHas('etapas_documento', [
        'id_documento' => $documento->id,
        'estado' => EstadoDocumento::Erro->value,
        'motivo' => 'sem LLM cloud disponível',
    ]);
});

it('veredicto completo → reconcilia e vai a Processado', function (): void {
    Entidade::factory()->empresaAplicacao()->create();
    $documento = documentoAnaliseCloud();
    $tipo = TipoDocumento::factory()->create(['posicao_empresa_mae' => PosicaoEmpresaMae::Cliente, 'espera_fornecedor' => true]);
    fingirClienteIaCloud(ResultadoExtracaoIA::completo($tipo, $tipo->id_categoria, Carbon::parse('2026-06-25'), '509999999', 'ACME Lda', null, null, 100.0));

    $resultado = app(ProcessarAnaliseCloudDocumentoAction::class)->handle();

    expect($resultado?->id)->toBe($documento->id)
        ->and($resultado?->estado)->toBe(EstadoDocumento::Processado);
});

it('veredicto inconclusivo → Erro (cloud é a última camada)', function (ResultadoExtracaoIA $resultado): void {
    documentoAnaliseCloud();
    fingirClienteIaCloud($resultado);

    $processado = app(ProcessarAnaliseCloudDocumentoAction::class)->handle();

    expect($processado?->estado)->toBe(EstadoDocumento::Erro);
})->with([
    'desconhecido' => [ResultadoExtracaoIA::desconhecido()],
    'incompleto' => [ResultadoExtracaoIA::incompleto(['nif_fornecedor'])],
]);

it('veredicto perigoso → Perigoso', function (): void {
    documentoAnaliseCloud();
    fingirClienteIaCloud(ResultadoExtracaoIA::perigoso('prompt injection detectado'));

    $resultado = app(ProcessarAnaliseCloudDocumentoAction::class)->handle();

    expect($resultado?->estado)->toBe(EstadoDocumento::Perigoso);
});

it('falha técnica → conta tentativa e mantém em AnaliseCloud (texto preservado)', function (): void {
    $documento = documentoAnaliseCloud();
    fingirClienteIaCloud(ResultadoExtracaoIA::falhaTecnica('timeout do modelo cloud'));

    $resultado = app(ProcessarAnaliseCloudDocumentoAction::class)->handle();

    expect($resultado?->estado)->toBe(EstadoDocumento::AnaliseCloud);
    $this->assertDatabaseHas('extracoes_documento', [
        'id_documento' => $documento->id,
        'extracao_tentativas' => 1,
        'texto_extraido' => 'texto do parser',
    ]);
});

it('à max_tentativas de falha técnica vai a Erro', function (): void {
    $documento = Documento::factory()->analiseCloud()->create(['id_responsavel' => criarAdmin()->id]);
    Storage::disk('enviado')->put($documento->nome_ficheiro_storage, 'conteudo');
    ExtracaoDocumento::factory()->comTentativas(config()->integer('extracao.max_tentativas') - 1)
        ->for($documento, 'documento')->create(['texto_extraido' => 'texto']);
    fingirClienteIaCloud(ResultadoExtracaoIA::falhaTecnica('timeout de novo'));

    $resultado = app(ProcessarAnaliseCloudDocumentoAction::class)->handle();

    expect($resultado?->estado)->toBe(EstadoDocumento::Erro);
});

it('devolve null quando não há documento em AnaliseCloud para reclamar', function (): void {
    Documento::factory()->analiseIaLocal()->create();

    expect(app(ProcessarAnaliseCloudDocumentoAction::class)->handle())->toBeNull();
});
