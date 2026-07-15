<?php

declare(strict_types=1);

use App\Events\DocumentoMarcadoErro;
use App\Events\DocumentoMarcadoPerigoso;
use App\Events\DocumentoProcessado;
use App\Features\Documento\Criar\RegistarDocumentoManualAction;
use App\Features\Documento\Criar\RegistarDocumentoManualDto;
use App\Features\Documento\RecepcaoUpload\DocumentoDuplicadoException;
use App\Infrastructure\Malware\ContratoAnalisadorMalware;
use App\Infrastructure\Malware\FalhaAnaliseMalwareException;
use App\Infrastructure\Malware\ResultadoAnaliseMalware;
use App\Models\CategoriaDocumento;
use App\Models\Documento;
use App\Models\Entidade;
use App\Shared\Enums\EstadoDocumento;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Storage::fake('processado');
    $this->actingAs(criarAdmin());
});

function dtoManual(array $sobrepor = []): RegistarDocumentoManualDto
{
    return new RegistarDocumentoManualDto(
        idFornecedor: $sobrepor['idFornecedor'] ?? Entidade::factory()->create(['nome' => 'Fornecedor Lda'])->id,
        idCliente: $sobrepor['idCliente'] ?? Entidade::factory()->create()->id,
        idCategoria: $sobrepor['idCategoria'] ?? CategoriaDocumento::factory()->create(['nome' => 'Despesas'])->id,
        valor: 123.45,
        dataDocumento: Carbon::parse('2026-06-25'),
        ficheiro: $sobrepor['ficheiro'] ?? UploadedFile::fake()->create('fatura.pdf', 100),
    );
}

it('regista directo em Processado: deriva hash + nome canónico, escreve no disco e emite o evento', function (): void {
    $utilizador = auth()->user();
    $ficheiro = UploadedFile::fake()->create('fatura.pdf', 100);
    $hashEsperado = hash_file('sha256', (string) $ficheiro->getRealPath());
    $dados = dtoManual(['ficheiro' => $ficheiro]);

    Event::fake([DocumentoProcessado::class]);

    $documento = app(RegistarDocumentoManualAction::class)->handle($dados);

    expect($documento->estado)->toBe(EstadoDocumento::Processado)
        ->and($documento->disco_storage)->toBe('processado')
        ->and($documento->nome_ficheiro_storage)->toBe('2026-06-25-fornecedor-lda-despesas.pdf')
        ->and($documento->hash_sha256)->toBe($hashEsperado)
        ->and($documento->nome_ficheiro_original)->toBe('fatura.pdf')
        ->and($documento->id_responsavel)->toBe($utilizador?->getAuthIdentifier());

    Storage::disk('processado')->assertExists('2026-06-25-fornecedor-lda-despesas.pdf');

    $this->assertDatabaseCount('etapas_documento', 1);
    $this->assertDatabaseHas('etapas_documento', [
        'id_documento' => $documento->id,
        'estado' => EstadoDocumento::Processado->value,
        'motivo' => 'registo manual',
        'id_utilizador' => $utilizador?->getAuthIdentifier(),
    ]);

    Event::assertDispatched(
        DocumentoProcessado::class,
        fn (DocumentoProcessado $evento): bool => $evento->documento->is($documento),
    );
});

it('regista em Perigoso quando o ficheiro está infectado: disco perigoso + evento DocumentoMarcadoPerigoso', function (): void {
    Storage::fake('perigoso');
    $ficheiro = UploadedFile::fake()->create('fatura.pdf', 100);
    $dados = dtoManual(['ficheiro' => $ficheiro]);

    app()->instance(ContratoAnalisadorMalware::class, Mockery::mock(ContratoAnalisadorMalware::class, function ($mock): void {
        $mock->shouldReceive('analisar')->once()->andReturn(ResultadoAnaliseMalware::infectado('Eicar-Signature'));
    }));

    Event::fake([DocumentoMarcadoPerigoso::class]);

    $documento = app(RegistarDocumentoManualAction::class)->handle($dados);

    expect($documento->estado)->toBe(EstadoDocumento::Perigoso)
        ->and($documento->disco_storage)->toBe('perigoso');

    Storage::disk('perigoso')->assertExists('2026-06-25-fornecedor-lda-despesas.pdf');

    $this->assertDatabaseHas('etapas_documento', [
        'id_documento' => $documento->id,
        'estado' => EstadoDocumento::Perigoso->value,
        'motivo' => 'Eicar-Signature',
    ]);

    Event::assertDispatched(
        DocumentoMarcadoPerigoso::class,
        fn (DocumentoMarcadoPerigoso $evento): bool => $evento->documento->is($documento) && $evento->motivo === 'Eicar-Signature',
    );
});

it('regista em Erro quando o scan falha: disco erro + evento DocumentoMarcadoErro', function (): void {
    Storage::fake('erro');
    $ficheiro = UploadedFile::fake()->create('fatura.pdf', 100);
    $dados = dtoManual(['ficheiro' => $ficheiro]);

    app()->instance(ContratoAnalisadorMalware::class, Mockery::mock(ContratoAnalisadorMalware::class, function ($mock): void {
        $mock->shouldReceive('analisar')->once()->andThrow(new FalhaAnaliseMalwareException('timeout do clamd'));
    }));

    Event::fake([DocumentoMarcadoErro::class]);

    $documento = app(RegistarDocumentoManualAction::class)->handle($dados);

    expect($documento->estado)->toBe(EstadoDocumento::Erro)
        ->and($documento->disco_storage)->toBe('erro');

    Storage::disk('erro')->assertExists('2026-06-25-fornecedor-lda-despesas.pdf');

    $this->assertDatabaseHas('etapas_documento', [
        'id_documento' => $documento->id,
        'estado' => EstadoDocumento::Erro->value,
        'motivo' => 'timeout do clamd',
    ]);

    Event::assertDispatched(
        DocumentoMarcadoErro::class,
        fn (DocumentoMarcadoErro $evento): bool => $evento->documento->is($documento) && $evento->mensagemErro === 'timeout do clamd',
    );
});

it('rejeita ficheiro com hash já existente (sem o gravar)', function (): void {
    $ficheiro = UploadedFile::fake()->create('repetido.pdf', 80);
    $hash = hash_file('sha256', (string) $ficheiro->getRealPath());
    Documento::factory()->create(['hash_sha256' => $hash]);

    expect(fn (): Documento => app(RegistarDocumentoManualAction::class)->handle(dtoManual(['ficheiro' => $ficheiro])))
        ->toThrow(DocumentoDuplicadoException::class);

    $this->assertDatabaseCount('documentos', 1);
});

it('compensa apagando o ficheiro quando a transação falha', function (): void {
    $ficheiro = UploadedFile::fake()->create('falha.pdf', 30);
    $dados = dtoManual(['ficheiro' => $ficheiro]);

    Cache::shouldReceive('tags')->andThrow(new RuntimeException('falha na cache'));

    expect(fn (): Documento => app(RegistarDocumentoManualAction::class)->handle($dados))
        ->toThrow(RuntimeException::class, 'falha na cache');

    Storage::disk('processado')->assertMissing('2026-06-25-fornecedor-lda-despesas.pdf');
    $this->assertDatabaseCount('documentos', 0);
});

it('lança quando a escrita do ficheiro no disco processado falha', function (): void {
    $dados = dtoManual();

    $disco = Mockery::mock(Filesystem::class);
    $disco->shouldReceive('putFileAs')->andReturnFalse();
    Storage::shouldReceive('disk')->andReturn($disco);

    expect(fn (): Documento => app(RegistarDocumentoManualAction::class)->handle($dados))
        ->toThrow(RuntimeException::class, 'Falha ao guardar o ficheiro no disco processado.');

    $this->assertDatabaseCount('documentos', 0);
});

it('exige utilizador autenticado (guest é rejeitado)', function (): void {
    auth()->logout();

    expect(fn (): Documento => app(RegistarDocumentoManualAction::class)->handle(dtoManual()))
        ->toThrow(AuthorizationException::class);
});

describe('sem permissão de escrita', function (): void {
    beforeEach(fn () => $this->actingAs(criarUtilizador()));

    it('lança AuthorizationException quando utilizador não tem permissão de escrita', function (): void {
        expect(fn (): Documento => app(RegistarDocumentoManualAction::class)->handle(dtoManual()))
            ->toThrow(AuthorizationException::class);

        $this->assertDatabaseCount('documentos', 0);
    });
});
