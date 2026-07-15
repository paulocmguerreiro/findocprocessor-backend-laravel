<?php

declare(strict_types=1);

use App\Events\DocumentoProcessado;
use App\Features\Documento\TransicionarProcessado\TransicionarProcessadoDocumentoAction;
use App\Features\Documento\TransicionarProcessado\TransicionarProcessadoDocumentoDto;
use App\Models\CategoriaDocumento;
use App\Models\Documento;
use App\Models\Entidade;
use App\Shared\Enums\EstadoDocumento;
use App\Shared\Exceptions\TransicaoInvalidaException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

// Mantém Gate::authorize('update') — escreve os dados de negócio extraídos
// (não é mera flag de estado como as Marcar*). Requer permissão documentos.actualizar.
beforeEach(function (): void {
    Storage::fake('enviado');
    Storage::fake('processado');
    $this->actingAs(criarAdmin());
});

function dtoTransicao(): TransicionarProcessadoDocumentoDto
{
    return new TransicionarProcessadoDocumentoDto(
        idFornecedor: Entidade::factory()->create(['nome' => 'Fornecedor Lda'])->id,
        idCliente: Entidade::factory()->create()->id,
        idCategoria: CategoriaDocumento::factory()->create(['nome' => 'Despesas'])->id,
        valor: 250.0,
        dataDocumento: Carbon::parse('2026-06-25'),
    );
}

it('transiciona AguardaResposta → Processado: preenche domínio, move+renomeia e emite o evento', function (): void {
    $documento = Documento::factory()->aguardaResposta()->create(['nome_ficheiro_original' => 'scan.pdf']);
    Storage::disk('enviado')->put($documento->nome_ficheiro_storage, 'conteudo');
    $dados = dtoTransicao();

    Event::fake([DocumentoProcessado::class]);

    $resultado = app(TransicionarProcessadoDocumentoAction::class)->handle($documento, $dados);

    expect($resultado->estado)->toBe(EstadoDocumento::Processado)
        ->and($resultado->disco_storage)->toBe('processado')
        ->and($resultado->nome_ficheiro_storage)->toBe('2026-06-25-fornecedor-lda-despesas.pdf')
        ->and($resultado->id_fornecedor)->toBe($dados->idFornecedor);

    Storage::disk('processado')->assertExists('2026-06-25-fornecedor-lda-despesas.pdf');
    Storage::disk('enviado')->assertMissing($documento->nome_ficheiro_storage);
    $this->assertDatabaseHas('etapas_documento', [
        'id_documento' => $documento->id,
        'estado' => EstadoDocumento::Processado->value,
        'motivo' => 'processamento concluído',
    ]);

    Event::assertDispatched(DocumentoProcessado::class);
});

it('rejeita a transição a partir de um estado inválido', function (): void {
    $documento = Documento::factory()->pendente()->create();

    expect(fn (): Documento => app(TransicionarProcessadoDocumentoAction::class)->handle($documento, dtoTransicao()))
        ->toThrow(TransicaoInvalidaException::class);

    $this->assertDatabaseCount('etapas_documento', 0);
});

it('guest (sem autenticação) é rejeitado', function (): void {
    $documento = Documento::factory()->aguardaResposta()->create();
    auth()->logout();

    expect(fn (): Documento => app(TransicionarProcessadoDocumentoAction::class)->handle($documento, dtoTransicao()))
        ->toThrow(AuthorizationException::class);
});

describe('sem permissão de escrita', function (): void {
    beforeEach(fn () => $this->actingAs(criarUtilizador()));

    it('lança AuthorizationException quando utilizador não tem permissão de escrita', function (): void {
        $documento = Documento::factory()->aguardaResposta()->create();

        expect(fn (): Documento => app(TransicionarProcessadoDocumentoAction::class)->handle($documento, dtoTransicao()))
            ->toThrow(AuthorizationException::class);
    });
});
