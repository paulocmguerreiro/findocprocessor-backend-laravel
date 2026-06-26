<?php

declare(strict_types=1);

use App\Events\DocumentoReprocessado;
use App\Features\Documento\Reprocessar\ModoReprocessamento;
use App\Features\Documento\Reprocessar\ReprocessarDocumentoAction;
use App\Features\Documento\Reprocessar\ReprocessarDocumentoDto;
use App\Models\Documento;
use App\Shared\Enums\EstadoDocumento;
use App\Shared\Exceptions\TransicaoInvalidaException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Storage::fake('erro');
    Storage::fake('entrada');
    $this->actingAs(criarAdmin());
});

it('transiciona Erro → AguardaEnvio: move erro → entrada, regista o modo e emite o evento', function (): void {
    $documento = Documento::factory()->erro()->create();
    Storage::disk('erro')->put($documento->nome_ficheiro_storage, 'conteudo');

    Event::fake([DocumentoReprocessado::class]);

    $resultado = app(ReprocessarDocumentoAction::class)->handle($documento, new ReprocessarDocumentoDto(ModoReprocessamento::Modelo));

    expect($resultado->status)->toBe(EstadoDocumento::AguardaEnvio)
        ->and($resultado->disco_storage)->toBe('entrada');

    Storage::disk('entrada')->assertExists($documento->nome_ficheiro_storage);
    Storage::disk('erro')->assertMissing($documento->nome_ficheiro_storage);
    $this->assertDatabaseHas('etapas_documento', [
        'id_documento' => $documento->id,
        'estado' => EstadoDocumento::AguardaEnvio->value,
        'motivo' => ModoReprocessamento::Modelo->value,
    ]);

    Event::assertDispatched(
        DocumentoReprocessado::class,
        fn (DocumentoReprocessado $evento): bool => $evento->modo === ModoReprocessamento::Modelo,
    );
});

it('rejeita a transição a partir de um estado inválido', function (): void {
    // Processado → AguardaEnvio não consta do mapa (≠ Pendente/Erro → AguardaEnvio).
    $documento = Documento::factory()->processado()->create();

    expect(fn (): Documento => app(ReprocessarDocumentoAction::class)->handle($documento, new ReprocessarDocumentoDto(ModoReprocessamento::Ferramenta)))
        ->toThrow(TransicaoInvalidaException::class);

    $this->assertDatabaseCount('etapas_documento', 0);
});

describe('sem permissão de escrita', function (): void {
    beforeEach(fn () => $this->actingAs(criarUtilizador()));

    it('lança AuthorizationException quando utilizador não tem permissão de escrita', function (): void {
        $documento = Documento::factory()->erro()->create();

        expect(fn (): Documento => app(ReprocessarDocumentoAction::class)->handle($documento, new ReprocessarDocumentoDto(ModoReprocessamento::Modelo)))
            ->toThrow(AuthorizationException::class);
    });
});
