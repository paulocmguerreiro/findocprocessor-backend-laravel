<?php

declare(strict_types=1);

use App\Events\DocumentoMarcadoErro;
use App\Events\DocumentoMarcadoPerigoso;
use App\Events\DocumentoProcessado;
use App\Events\DocumentoReprocessado;
use App\Features\Documento\Reprocessar\ModoReprocessamento;
use App\Models\Documento;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

it('os events de domínio do Documento disparam após o commit', function (string $evento): void {
    expect($evento)->toImplement(ShouldDispatchAfterCommit::class);
})->with([
    DocumentoProcessado::class,
    DocumentoMarcadoErro::class,
    DocumentoMarcadoPerigoso::class,
    DocumentoReprocessado::class,
]);

it('DocumentoProcessado transporta o documento', function (): void {
    $documento = new Documento;

    expect((new DocumentoProcessado($documento))->documento)->toBe($documento);
});

it('DocumentoMarcadoErro transporta documento e mensagem de erro', function (): void {
    $documento = new Documento;

    $evento = new DocumentoMarcadoErro($documento, 'timeout do serviço');

    expect($evento->documento)->toBe($documento)
        ->and($evento->mensagemErro)->toBe('timeout do serviço');
});

it('DocumentoMarcadoPerigoso transporta documento e motivo', function (): void {
    $documento = new Documento;

    $evento = new DocumentoMarcadoPerigoso($documento, 'injecção detectada');

    expect($evento->documento)->toBe($documento)
        ->and($evento->motivo)->toBe('injecção detectada');
});

it('DocumentoReprocessado transporta documento e modo', function (): void {
    $documento = new Documento;

    $evento = new DocumentoReprocessado($documento, ModoReprocessamento::Modelo);

    expect($evento->documento)->toBe($documento)
        ->and($evento->modo)->toBe(ModoReprocessamento::Modelo);
});
