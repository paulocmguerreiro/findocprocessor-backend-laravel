<?php

declare(strict_types=1);

use App\Features\Documento\Processamento\ConcluirExtracao\ConcluirExtracaoDocumentoAction;
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
    Storage::fake('erro');
});

function documentoParaConcluir(): Documento
{
    $documento = Documento::factory()->analiseIaLocal()->create([
        'id_responsavel' => criarAdmin()->id,
        'nome_ficheiro_original' => 'fatura.pdf',
    ]);
    Storage::disk('enviado')->put($documento->nome_ficheiro_storage, 'conteudo');
    ExtracaoDocumento::factory()->for($documento, 'documento')->create(['texto_extraido' => 'texto do parser']);

    return $documento;
}

function resultadoIaCompleto(TipoDocumento $tipo): ResultadoExtracaoIA
{
    return ResultadoExtracaoIA::completo(
        tipoDocumento: $tipo,
        idCategoria: $tipo->id_categoria,
        dataDocumento: Carbon::parse('2026-06-25'),
        nifFornecedor: '509999999',
        nomeFornecedor: 'ACME Lda',
        nifCliente: null,
        nomeCliente: null,
        valor: 100.0,
    );
}

it('reconcilia e transiciona para Processado, autenticado como o responsável, e restaura a sessão', function (): void {
    Entidade::factory()->empresaAplicacao()->create();
    $documento = documentoParaConcluir();
    $tipo = TipoDocumento::factory()->create(['posicao_empresa_mae' => PosicaoEmpresaMae::Cliente, 'espera_fornecedor' => true]);

    $resultado = app(ConcluirExtracaoDocumentoAction::class)->handle($documento, resultadoIaCompleto($tipo));

    expect($resultado->estado)->toBe(EstadoDocumento::Processado)
        ->and($resultado->id_fornecedor)->not->toBeNull()
        ->and(auth()->check())->toBeFalse();

    $this->assertDatabaseHas('etapas_documento', [
        'id_documento' => $documento->id,
        'estado' => EstadoDocumento::Processado->value,
    ]);
});

it('restaura o utilizador previamente autenticado após concluir (não faz logout)', function (): void {
    $anterior = criarAdmin();
    $this->actingAs($anterior);

    Entidade::factory()->empresaAplicacao()->create();
    $documento = documentoParaConcluir(); // id_responsavel é outro admin distinto
    $tipo = TipoDocumento::factory()->create(['posicao_empresa_mae' => PosicaoEmpresaMae::Cliente, 'espera_fornecedor' => true]);

    app(ConcluirExtracaoDocumentoAction::class)->handle($documento, resultadoIaCompleto($tipo));

    expect(auth()->id())->toBe($anterior->id);
});

it('vai a Erro quando a empresa mãe não está configurada (RN-06)', function (): void {
    $documento = documentoParaConcluir();
    $tipo = TipoDocumento::factory()->create(['posicao_empresa_mae' => PosicaoEmpresaMae::Cliente, 'espera_fornecedor' => true]);

    $resultado = app(ConcluirExtracaoDocumentoAction::class)->handle($documento, resultadoIaCompleto($tipo));

    expect($resultado->estado)->toBe(EstadoDocumento::Erro);
});
