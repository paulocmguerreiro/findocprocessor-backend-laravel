<?php

declare(strict_types=1);

use App\Events\DocumentoMarcadoErroEvent;
use App\Events\DocumentoMarcadoPerigosoEvent;
use App\Events\DocumentoProcessadoEvent;
use App\Events\DocumentoReprocessadoEvent;
use App\Features\Documento\Reprocessar\ModoReprocessamento;
use App\Models\Documento;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

it('os events de domínio do Documento disparam após o commit', function (string $evento): void {
    expect($evento)->toImplement(ShouldDispatchAfterCommit::class);
})->with([
    DocumentoProcessadoEvent::class,
    DocumentoMarcadoErroEvent::class,
    DocumentoMarcadoPerigosoEvent::class,
    DocumentoReprocessadoEvent::class,
]);

it('DocumentoProcessadoEvent transporta o documento', function (): void {
    $documento = new Documento;

    expect((new DocumentoProcessadoEvent($documento))->documento)->toBe($documento);
});

it('DocumentoMarcadoErroEvent transporta documento e mensagem de erro', function (): void {
    $documento = new Documento;

    $evento = new DocumentoMarcadoErroEvent($documento, 'timeout do serviço');

    expect($evento->documento)->toBe($documento)
        ->and($evento->mensagemErro)->toBe('timeout do serviço');
});

it('DocumentoMarcadoPerigosoEvent transporta documento e motivo', function (): void {
    $documento = new Documento;

    $evento = new DocumentoMarcadoPerigosoEvent($documento, 'injecção detectada');

    expect($evento->documento)->toBe($documento)
        ->and($evento->motivo)->toBe('injecção detectada');
});

it('DocumentoReprocessadoEvent transporta documento e modo', function (): void {
    $documento = new Documento;

    $evento = new DocumentoReprocessadoEvent($documento, ModoReprocessamento::Modelo);

    expect($evento->documento)->toBe($documento)
        ->and($evento->modo)->toBe(ModoReprocessamento::Modelo);
});
