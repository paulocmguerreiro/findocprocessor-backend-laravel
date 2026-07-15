<?php

declare(strict_types=1);

use App\Features\Documento\MarcarAguardaEnvio\MarcarAguardaEnvioDocumentoAction;
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

it('transiciona Pendente → AguardaEnvio sem mover o ficheiro (passo de sistema, sem login)', function (): void {
    $documento = Documento::factory()->pendente()->create();
    Storage::disk('entrada')->put($documento->nome_ficheiro_storage, 'conteudo');

    $resultado = app(MarcarAguardaEnvioDocumentoAction::class)->handle($documento);

    expect($resultado->estado)->toBe(EstadoDocumento::AguardaEnvio)
        ->and($resultado->disco_storage)->toBe('entrada');

    Storage::disk('entrada')->assertExists($documento->nome_ficheiro_storage);
    $this->assertDatabaseHas('etapas_documento', [
        'id_documento' => $documento->id,
        'estado' => EstadoDocumento::AguardaEnvio->value,
        'motivo' => 'pronto para envio',
        'id_utilizador' => null,
    ]);
});

it('rejeita a transição a partir de um estado inválido', function (): void {
    $documento = Documento::factory()->processado()->create();

    expect(fn (): Documento => app(MarcarAguardaEnvioDocumentoAction::class)->handle($documento))
        ->toThrow(TransicaoInvalidaException::class);

    $this->assertDatabaseCount('etapas_documento', 0);
});
