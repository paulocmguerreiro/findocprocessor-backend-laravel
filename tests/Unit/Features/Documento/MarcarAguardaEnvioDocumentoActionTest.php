<?php

declare(strict_types=1);

use App\Features\Documento\MarcarAguardaEnvio\MarcarAguardaEnvioDocumentoAction;
use App\Models\Documento;
use App\Shared\Enums\EstadoDocumento;
use App\Shared\Exceptions\TransicaoInvalidaException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Storage::fake('entrada');
    $this->actingAs(criarAdmin());
});

it('transiciona Pendente → AguardaEnvio sem mover o ficheiro', function (): void {
    $documento = Documento::factory()->pendente()->create();
    Storage::disk('entrada')->put($documento->nome_ficheiro_storage, 'conteudo');

    $resultado = app(MarcarAguardaEnvioDocumentoAction::class)->handle($documento);

    expect($resultado->status)->toBe(EstadoDocumento::AguardaEnvio)
        ->and($resultado->disco_storage)->toBe('entrada');

    Storage::disk('entrada')->assertExists($documento->nome_ficheiro_storage);
    $this->assertDatabaseHas('etapas_documento', [
        'id_documento' => $documento->id,
        'estado' => EstadoDocumento::AguardaEnvio->value,
        'motivo' => 'pronto para envio',
    ]);
});

it('rejeita a transição a partir de um estado inválido', function (): void {
    $documento = Documento::factory()->processado()->create();

    expect(fn (): Documento => app(MarcarAguardaEnvioDocumentoAction::class)->handle($documento))
        ->toThrow(TransicaoInvalidaException::class);

    $this->assertDatabaseCount('etapas_documento', 0);
});
