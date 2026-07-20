<?php

declare(strict_types=1);

use App\Features\Documento\Atribuicao\ReivindicarDocumentoPendenteAction;
use App\Models\Documento;
use App\Shared\Enums\EstadoDocumento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Storage::fake('entrada');
});

it('reivindica um Documento Pendente, triando-o (scan desligado em teste) até AnaliseTexto', function (): void {
    $documento = Documento::factory()->pendente()->create();
    Storage::disk('entrada')->put($documento->nome_ficheiro_storage, 'conteudo');

    $resultado = app(ReivindicarDocumentoPendenteAction::class)->handle();

    expect($resultado)->not->toBeNull()
        ->and($resultado->id)->toBe($documento->id)
        ->and($resultado->estado)->toBe(EstadoDocumento::AnaliseTexto);
});

it('devolve null quando não há Documentos Pendentes', function (): void {
    Documento::factory()->processado()->create();

    $resultado = app(ReivindicarDocumentoPendenteAction::class)->handle();

    expect($resultado)->toBeNull();
});
