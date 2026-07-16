<?php

declare(strict_types=1);

use App\Events\DocumentoMarcadoErro;
use App\Features\Documento\MarcarErro\MarcarErroDocumentoAction;
use App\Features\Documento\MarcarErro\MarcarErroDocumentoDto;
use App\Models\Documento;
use App\Shared\Enums\EstadoDocumento;
use App\Shared\Exceptions\TransicaoInvalidaException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

// Transição de sistema (pipeline): corre sem utilizador autenticado, sem Gate.
beforeEach(function (): void {
    Storage::fake('enviado');
    Storage::fake('erro');
});

it('transiciona AnaliseIaLocal → Erro: move enviado → erro, regista o motivo e emite o evento (passo de sistema)', function (): void {
    $documento = Documento::factory()->analiseIaLocal()->create();
    Storage::disk('enviado')->put($documento->nome_ficheiro_storage, 'conteudo');

    Event::fake([DocumentoMarcadoErro::class]);

    $resultado = app(MarcarErroDocumentoAction::class)->handle($documento, new MarcarErroDocumentoDto('timeout do serviço'));

    expect($resultado->estado)->toBe(EstadoDocumento::Erro)
        ->and($resultado->disco_storage)->toBe('erro');

    Storage::disk('erro')->assertExists($documento->nome_ficheiro_storage);
    Storage::disk('enviado')->assertMissing($documento->nome_ficheiro_storage);
    $this->assertDatabaseHas('etapas_documento', [
        'id_documento' => $documento->id,
        'estado' => EstadoDocumento::Erro->value,
        'motivo' => 'timeout do serviço',
        'id_utilizador' => null,
    ]);

    Event::assertDispatched(
        DocumentoMarcadoErro::class,
        fn (DocumentoMarcadoErro $evento): bool => $evento->mensagemErro === 'timeout do serviço',
    );
});

it('rejeita a transição a partir de um estado inválido', function (): void {
    $documento = Documento::factory()->processado()->create();

    expect(fn (): Documento => app(MarcarErroDocumentoAction::class)->handle($documento, new MarcarErroDocumentoDto('erro')))
        ->toThrow(TransicaoInvalidaException::class);

    $this->assertDatabaseCount('etapas_documento', 0);
});
