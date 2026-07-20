<?php

declare(strict_types=1);

use App\Features\Documento\Operacoes\TransicoesEstado\MarcarAnaliseTextoDocumentoAction;
use App\Models\Documento;
use App\Shared\Enums\EstadoDocumento;
use App\Shared\Exceptions\TransicaoInvalidaException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

// Transição de sistema (pipeline): corre sem utilizador autenticado, sem Gate.
beforeEach(function (): void {
    Storage::fake('entrada');
});

it('transiciona AnaliseMalware → AnaliseTexto sem mover o ficheiro (passo de sistema, sem login)', function (): void {
    $documento = Documento::factory()->analiseMalware()->create();
    Storage::disk('entrada')->put($documento->nome_ficheiro_storage, 'conteudo');

    $resultado = app(MarcarAnaliseTextoDocumentoAction::class)->handle($documento);

    expect($resultado->estado)->toBe(EstadoDocumento::AnaliseTexto)
        ->and($resultado->disco_storage)->toBe('entrada');

    Storage::disk('entrada')->assertExists($documento->nome_ficheiro_storage);
    $this->assertDatabaseHas('etapas_documento', [
        'id_documento' => $documento->id,
        'estado' => EstadoDocumento::AnaliseTexto->value,
        'motivo' => 'análise de malware concluída',
        'id_utilizador' => null,
    ]);
});

it('rejeita a transição a partir de um estado inválido', function (): void {
    $documento = Documento::factory()->processado()->create();

    expect(fn (): Documento => app(MarcarAnaliseTextoDocumentoAction::class)->handle($documento))
        ->toThrow(TransicaoInvalidaException::class);

    $this->assertDatabaseCount('etapas_documento', 0);
});
