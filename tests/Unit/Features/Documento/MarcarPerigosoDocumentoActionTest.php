<?php

declare(strict_types=1);

use App\Events\DocumentoMarcadoPerigoso;
use App\Features\Documento\MarcarPerigoso\MarcarPerigosoDocumentoAction;
use App\Features\Documento\MarcarPerigoso\MarcarPerigosoDocumentoDto;
use App\Models\Documento;
use App\Shared\Enums\EstadoDocumento;
use App\Shared\Exceptions\TransicaoInvalidaException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

// Transição de sistema (pipeline): corre sem utilizador autenticado, sem Gate.
beforeEach(function (): void {
    Storage::fake('entrada');
    Storage::fake('enviado');
    Storage::fake('perigoso');
});

it('marca Perigoso a partir de AnaliseMalware (scan) e move para o disco perigoso', function (): void {
    $documento = Documento::factory()->analiseMalware()->create();
    Storage::disk('entrada')->put($documento->nome_ficheiro_storage, 'conteudo');

    Event::fake([DocumentoMarcadoPerigoso::class]);

    $resultado = app(MarcarPerigosoDocumentoAction::class)->handle($documento, new MarcarPerigosoDocumentoDto('injecção detectada'));

    expect($resultado->estado)->toBe(EstadoDocumento::Perigoso)
        ->and($resultado->disco_storage)->toBe('perigoso');

    Storage::disk('perigoso')->assertExists($documento->nome_ficheiro_storage);
    Storage::disk('entrada')->assertMissing($documento->nome_ficheiro_storage);
    $this->assertDatabaseHas('etapas_documento', [
        'id_documento' => $documento->id,
        'estado' => EstadoDocumento::Perigoso->value,
        'motivo' => 'injecção detectada',
        'id_utilizador' => null,
    ]);

    Event::assertDispatched(DocumentoMarcadoPerigoso::class);
});

it('marca Perigoso a partir de AnaliseIaLocal (guardrail)', function (): void {
    $documento = Documento::factory()->analiseIaLocal()->create();
    Storage::disk('enviado')->put($documento->nome_ficheiro_storage, 'conteudo');

    $resultado = app(MarcarPerigosoDocumentoAction::class)->handle($documento, new MarcarPerigosoDocumentoDto('conteúdo suspeito'));

    expect($resultado->estado)->toBe(EstadoDocumento::Perigoso);
    Storage::disk('perigoso')->assertExists($documento->nome_ficheiro_storage);
});

it('rejeita a transição a partir de um estado inválido', function (): void {
    $documento = Documento::factory()->pendente()->create();

    expect(fn (): Documento => app(MarcarPerigosoDocumentoAction::class)->handle($documento, new MarcarPerigosoDocumentoDto('x')))
        ->toThrow(TransicaoInvalidaException::class);

    $this->assertDatabaseCount('etapas_documento', 0);
});
