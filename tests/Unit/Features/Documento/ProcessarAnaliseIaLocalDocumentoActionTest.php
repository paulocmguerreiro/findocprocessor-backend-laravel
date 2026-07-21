<?php

declare(strict_types=1);

use App\Features\Documento\Processamento\ProcessarAnaliseIaLocalDocumentoAction;
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
    config(['extracao.local.activa' => true]);
    // Binding por omissão sem rede — os testes que precisam de um veredicto concreto
    // sobrepõem-no via fingirClienteIaLocal(); os restantes (guarda/sem candidato)
    // instanciam o orquestrador sem nunca chamar extrair().
    app()->instance(ClienteIAInterface::class, Mockery::mock(ClienteIAInterface::class));
});

function documentoIaLocal(string $texto = 'texto do parser'): Documento
{
    $documento = Documento::factory()->analiseIaLocal()->create([
        'id_responsavel' => criarAdmin()->id,
        'nome_ficheiro_original' => 'fatura.pdf',
    ]);
    Storage::disk('enviado')->put($documento->nome_ficheiro_storage, 'conteudo');
    ExtracaoDocumento::factory()->for($documento, 'documento')->create(['texto_extraido' => $texto, 'extracao_tentativas' => 0]);

    return $documento;
}

function fingirClienteIaLocal(ResultadoExtracaoIA $resultado): void
{
    app()->instance(ClienteIAInterface::class, Mockery::mock(ClienteIAInterface::class, function ($mock) use ($resultado): void {
        $mock->shouldReceive('extrair')->once()->with(Mockery::type('string'), CamadaIA::Local)->andReturn($resultado);
    }));
}

it('veredicto completo → reconcilia e vai a Processado', function (): void {
    Entidade::factory()->empresaAplicacao()->create();
    $documento = documentoIaLocal();
    $tipo = TipoDocumento::factory()->create(['posicao_empresa_mae' => PosicaoEmpresaMae::Cliente, 'espera_fornecedor' => true]);
    fingirClienteIaLocal(ResultadoExtracaoIA::completo($tipo, $tipo->id_categoria, Carbon::parse('2026-06-25'), '509999999', 'ACME Lda', null, null, 100.0));

    $resultado = app(ProcessarAnaliseIaLocalDocumentoAction::class)->handle();

    expect($resultado?->id)->toBe($documento->id)
        ->and($resultado?->estado)->toBe(EstadoDocumento::Processado);
});

it('camada local inactiva → escala para AnaliseCloud sem contar tentativa (RN-04)', function (): void {
    config(['extracao.local.activa' => false]);
    $documento = documentoIaLocal();

    $resultado = app(ProcessarAnaliseIaLocalDocumentoAction::class)->handle();

    expect($resultado?->estado)->toBe(EstadoDocumento::AnaliseCloud);
    $this->assertDatabaseHas('extracoes_documento', [
        'id_documento' => $documento->id,
        'extracao_tentativas' => 0,
        'texto_extraido' => 'texto do parser',
    ]);
});

it('veredicto inconclusivo → escala para AnaliseCloud (texto preservado)', function (ResultadoExtracaoIA $resultado): void {
    $documento = documentoIaLocal();
    fingirClienteIaLocal($resultado);

    $processado = app(ProcessarAnaliseIaLocalDocumentoAction::class)->handle();

    expect($processado?->estado)->toBe(EstadoDocumento::AnaliseCloud);
    $this->assertDatabaseHas('extracoes_documento', [
        'id_documento' => $documento->id,
        'texto_extraido' => 'texto do parser',
    ]);
})->with([
    'desconhecido' => [ResultadoExtracaoIA::desconhecido()],
    'incompleto' => [ResultadoExtracaoIA::incompleto(['nif_fornecedor'])],
]);

it('veredicto perigoso → Perigoso', function (): void {
    $documento = documentoIaLocal();
    fingirClienteIaLocal(ResultadoExtracaoIA::perigoso('prompt injection detectado'));

    $resultado = app(ProcessarAnaliseIaLocalDocumentoAction::class)->handle();

    expect($resultado?->estado)->toBe(EstadoDocumento::Perigoso);
});

it('falha técnica → conta tentativa e mantém em AnaliseIaLocal (texto preservado)', function (): void {
    $documento = documentoIaLocal();
    fingirClienteIaLocal(ResultadoExtracaoIA::falhaTecnica('timeout do modelo local'));

    $resultado = app(ProcessarAnaliseIaLocalDocumentoAction::class)->handle();

    expect($resultado?->estado)->toBe(EstadoDocumento::AnaliseIaLocal);
    $this->assertDatabaseHas('extracoes_documento', [
        'id_documento' => $documento->id,
        'extracao_tentativas' => 1,
        'texto_extraido' => 'texto do parser',
    ]);
});

it('à max_tentativas de falha técnica vai a Erro', function (): void {
    $documento = Documento::factory()->analiseIaLocal()->create(['id_responsavel' => criarAdmin()->id]);
    Storage::disk('enviado')->put($documento->nome_ficheiro_storage, 'conteudo');
    ExtracaoDocumento::factory()->comTentativas(config()->integer('extracao.max_tentativas') - 1)
        ->for($documento, 'documento')->create(['texto_extraido' => 'texto']);
    fingirClienteIaLocal(ResultadoExtracaoIA::falhaTecnica('timeout de novo'));

    $resultado = app(ProcessarAnaliseIaLocalDocumentoAction::class)->handle();

    expect($resultado?->estado)->toBe(EstadoDocumento::Erro);
});

it('devolve null quando não há documento em AnaliseIaLocal para reclamar', function (): void {
    Documento::factory()->analiseCloud()->create();

    expect(app(ProcessarAnaliseIaLocalDocumentoAction::class)->handle())->toBeNull();
});
