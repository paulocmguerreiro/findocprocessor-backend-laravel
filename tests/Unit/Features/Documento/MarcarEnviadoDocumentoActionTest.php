<?php

declare(strict_types=1);

use App\Features\Documento\MarcarEnviado\MarcarEnviadoDocumentoAction;
use App\Models\Documento;
use App\Shared\Enums\EstadoDocumento;
use App\Shared\Exceptions\TransicaoInvalidaException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Storage::fake('entrada');
    Storage::fake('enviado');
    $this->actingAs(criarAdmin());
});

it('transiciona AguardaEnvio → Enviado e move o ficheiro entrada → enviado', function (): void {
    $documento = Documento::factory()->aguardaEnvio()->create();
    Storage::disk('entrada')->put($documento->nome_ficheiro_storage, 'conteudo');

    $resultado = app(MarcarEnviadoDocumentoAction::class)->handle($documento);

    expect($resultado->status)->toBe(EstadoDocumento::Enviado)
        ->and($resultado->disco_storage)->toBe('enviado');

    Storage::disk('enviado')->assertExists($documento->nome_ficheiro_storage);
    Storage::disk('entrada')->assertMissing($documento->nome_ficheiro_storage);
    $this->assertDatabaseHas('etapas_documento', [
        'id_documento' => $documento->id,
        'estado' => EstadoDocumento::Enviado->value,
        'motivo' => 'enviado para extracção',
    ]);
});

it('compensa repondo o ficheiro na origem quando a transação falha', function (): void {
    $documento = Documento::factory()->aguardaEnvio()->create();
    Storage::disk('entrada')->put($documento->nome_ficheiro_storage, 'conteudo');

    Cache::shouldReceive('tags')->andThrow(new RuntimeException('falha na cache'));

    expect(fn (): Documento => app(MarcarEnviadoDocumentoAction::class)->handle($documento))
        ->toThrow(RuntimeException::class, 'falha na cache');

    // Ficheiro reposto em entrada; estado e histórico intactos.
    Storage::disk('entrada')->assertExists($documento->nome_ficheiro_storage);
    Storage::disk('enviado')->assertMissing($documento->nome_ficheiro_storage);
    $this->assertDatabaseHas('documentos', [
        'id' => $documento->id,
        'status' => EstadoDocumento::AguardaEnvio->value,
        'disco_storage' => 'entrada',
    ]);
    $this->assertDatabaseCount('etapas_documento', 0);
});

it('rejeita a transição a partir de um estado inválido', function (): void {
    $documento = Documento::factory()->processado()->create();

    expect(fn (): Documento => app(MarcarEnviadoDocumentoAction::class)->handle($documento))
        ->toThrow(TransicaoInvalidaException::class);

    $this->assertDatabaseCount('etapas_documento', 0);
});

it('exige utilizador autenticado (guest é rejeitado)', function (): void {
    auth()->logout();
    $documento = Documento::factory()->aguardaEnvio()->create();

    expect(fn (): Documento => app(MarcarEnviadoDocumentoAction::class)->handle($documento))
        ->toThrow(AuthorizationException::class);
});
