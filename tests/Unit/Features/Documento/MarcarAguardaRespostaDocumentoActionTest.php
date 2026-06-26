<?php

declare(strict_types=1);

use App\Features\Documento\MarcarAguardaResposta\MarcarAguardaRespostaDocumentoAction;
use App\Models\Documento;
use App\Models\User;
use App\Shared\Enums\EstadoDocumento;
use App\Shared\Exceptions\TransicaoInvalidaException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Storage::fake('enviado');
    $this->actingAs(User::factory()->create());
});

it('transiciona Enviado → AguardaResposta sem mover o ficheiro', function (): void {
    $documento = Documento::factory()->enviado()->create();
    Storage::disk('enviado')->put($documento->nome_ficheiro_storage, 'conteudo');

    $resultado = app(MarcarAguardaRespostaDocumentoAction::class)->handle($documento);

    expect($resultado->status)->toBe(EstadoDocumento::AguardaResposta)
        ->and($resultado->disco_storage)->toBe('enviado');

    Storage::disk('enviado')->assertExists($documento->nome_ficheiro_storage);
    $this->assertDatabaseHas('etapas_documento', [
        'id_documento' => $documento->id,
        'estado' => EstadoDocumento::AguardaResposta->value,
        'motivo' => 'a aguardar resposta da extracção',
    ]);
});

it('rejeita a transição a partir de um estado inválido', function (): void {
    $documento = Documento::factory()->pendente()->create();

    expect(fn (): Documento => app(MarcarAguardaRespostaDocumentoAction::class)->handle($documento))
        ->toThrow(TransicaoInvalidaException::class);

    $this->assertDatabaseCount('etapas_documento', 0);
});
