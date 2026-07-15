<?php

declare(strict_types=1);

use App\Features\Documento\RecepcaoUpload\DocumentoDuplicadoException;
use App\Features\Documento\RecepcaoUpload\ReceberUploadDocumentoAction;
use App\Features\Documento\RecepcaoUpload\ReceberUploadDocumentoDto;
use App\Models\Documento;
use App\Shared\Enums\EstadoDocumento;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Storage::fake('entrada');
    $this->actingAs(criarAdmin());
});

it('calcula o hash, escreve em entrada e cria o documento em Pendente', function (): void {
    $utilizador = auth()->user();
    $ficheiro = UploadedFile::fake()->create('fatura.pdf', 100);
    $hashEsperado = hash_file('sha256', (string) $ficheiro->getRealPath());

    $documento = app(ReceberUploadDocumentoAction::class)->handle(new ReceberUploadDocumentoDto($ficheiro));

    expect($documento->estado)->toBe(EstadoDocumento::Pendente)
        ->and($documento->disco_storage)->toBe('entrada')
        ->and($documento->hash_sha256)->toBe($hashEsperado)
        ->and($documento->nome_ficheiro_original)->toBe('fatura.pdf')
        ->and($documento->id_responsavel)->toBe($utilizador?->getAuthIdentifier());

    Storage::disk('entrada')->assertExists($documento->nome_ficheiro_storage);

    $this->assertDatabaseHas('etapas_documento', [
        'id_documento' => $documento->id,
        'estado' => EstadoDocumento::Pendente->value,
        'motivo' => 'upload recebido',
        'id_utilizador' => $utilizador?->getAuthIdentifier(),
    ]);
});

it('rejeita ficheiro com hash já existente (sem o gravar)', function (): void {
    $ficheiro = UploadedFile::fake()->create('repetido.pdf', 80);
    $hash = hash_file('sha256', (string) $ficheiro->getRealPath());
    Documento::factory()->create(['hash_sha256' => $hash]);

    expect(fn (): Documento => app(ReceberUploadDocumentoAction::class)->handle(new ReceberUploadDocumentoDto($ficheiro)))
        ->toThrow(DocumentoDuplicadoException::class);

    $this->assertDatabaseCount('documentos', 1);
});

it('compensa apagando o ficheiro quando a transação falha', function (): void {
    $ficheiro = UploadedFile::fake()->create('falha.pdf', 30);
    $nomeStorage = $ficheiro->hashName();

    // Faz a invalidação de cache (último passo da transação) rebentar.
    Cache::shouldReceive('tags')->andThrow(new RuntimeException('falha na cache'));

    expect(fn (): Documento => app(ReceberUploadDocumentoAction::class)->handle(new ReceberUploadDocumentoDto($ficheiro)))
        ->toThrow(RuntimeException::class, 'falha na cache');

    Storage::disk('entrada')->assertMissing($nomeStorage);
    $this->assertDatabaseCount('documentos', 0);
});

it('lança quando a escrita do ficheiro no disco de entrada falha', function (): void {
    $ficheiro = UploadedFile::fake()->create('falha.pdf', 10);

    $disco = Mockery::mock(Filesystem::class);
    $disco->shouldReceive('putFileAs')->andReturnFalse();
    Storage::shouldReceive('disk')->andReturn($disco);

    expect(fn (): Documento => app(ReceberUploadDocumentoAction::class)->handle(new ReceberUploadDocumentoDto($ficheiro)))
        ->toThrow(RuntimeException::class, 'Falha ao guardar o ficheiro no disco de entrada.');

    $this->assertDatabaseCount('documentos', 0);
});

it('exige utilizador autenticado (guest é rejeitado)', function (): void {
    auth()->logout();
    $ficheiro = UploadedFile::fake()->create('x.pdf', 10);

    expect(fn (): Documento => app(ReceberUploadDocumentoAction::class)->handle(new ReceberUploadDocumentoDto($ficheiro)))
        ->toThrow(AuthorizationException::class);
});

describe('sem permissão de escrita', function (): void {
    beforeEach(fn () => $this->actingAs(criarUtilizador()));

    it('lança AuthorizationException quando utilizador não tem permissão de escrita', function (): void {
        $ficheiro = UploadedFile::fake()->create('fatura.pdf', 100);

        expect(fn (): Documento => app(ReceberUploadDocumentoAction::class)->handle(new ReceberUploadDocumentoDto($ficheiro)))
            ->toThrow(AuthorizationException::class);

        $this->assertDatabaseCount('documentos', 0);
    });
});
