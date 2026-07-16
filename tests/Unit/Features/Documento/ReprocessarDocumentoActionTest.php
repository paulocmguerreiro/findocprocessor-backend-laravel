<?php

declare(strict_types=1);

use App\Events\DocumentoReprocessadoEvent;
use App\Features\Documento\Reprocessar\ModoReprocessamento;
use App\Features\Documento\Reprocessar\ReprocessarDocumentoAction;
use App\Features\Documento\Reprocessar\ReprocessarDocumentoDto;
use App\Models\Documento;
use App\Models\ExtracaoDocumento;
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

it('transiciona Erro → Pendente: move erro → entrada, regista o modo e emite o evento', function (): void {
    $documento = Documento::factory()->erro()->create();
    Storage::disk('erro')->put($documento->nome_ficheiro_storage, 'conteudo');

    Event::fake([DocumentoReprocessadoEvent::class]);

    $resultado = app(ReprocessarDocumentoAction::class)->handle($documento, new ReprocessarDocumentoDto(ModoReprocessamento::Modelo));

    expect($resultado->estado)->toBe(EstadoDocumento::Pendente)
        ->and($resultado->disco_storage)->toBe('entrada');

    Storage::disk('entrada')->assertExists($documento->nome_ficheiro_storage);
    Storage::disk('erro')->assertMissing($documento->nome_ficheiro_storage);
    $this->assertDatabaseHas('etapas_documento', [
        'id_documento' => $documento->id,
        'estado' => EstadoDocumento::Pendente->value,
        'motivo' => ModoReprocessamento::Modelo->value,
    ]);

    Event::assertDispatched(
        DocumentoReprocessadoEvent::class,
        fn (DocumentoReprocessadoEvent $evento): bool => $evento->modo === ModoReprocessamento::Modelo,
    );
});

it('rejeita a transição a partir de um estado inválido', function (): void {
    // Processado → Pendente não consta do mapa (só Erro → Pendente reabre o pipeline).
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

it('exige utilizador autenticado (guest é rejeitado)', function (): void {
    auth()->logout();

    $documento = Documento::factory()->erro()->create();

    expect(fn (): Documento => app(ReprocessarDocumentoAction::class)->handle($documento, new ReprocessarDocumentoDto(ModoReprocessamento::Modelo)))
        ->toThrow(AuthorizationException::class);
});

describe('Limpeza defensiva de extracoes_documento residual (RF-10)', function (): void {
    it('elimina uma linha de ExtracaoDocumento residual ao reabrir (rede de segurança)', function (): void {
        // Caso raro: a linha não foi eliminada ao entrar em Erro (a via normal é
        // RegraEliminarExtracaoTerminal). O delete defensivo garante que não sobra.
        $documento = Documento::factory()->erro()->create();
        ExtracaoDocumento::factory()->comDadosExtraidos()->for($documento, 'documento')->create();
        Storage::disk('erro')->put($documento->nome_ficheiro_storage, 'conteudo');

        app(ReprocessarDocumentoAction::class)->handle($documento, new ReprocessarDocumentoDto(ModoReprocessamento::Modelo));

        $this->assertDatabaseCount('extracoes_documento', 0);
    });

    it('não falha quando não existe linha de ExtracaoDocumento (caso normal pós-Erro)', function (): void {
        $documento = Documento::factory()->erro()->create();
        Storage::disk('erro')->put($documento->nome_ficheiro_storage, 'conteudo');

        $resultado = app(ReprocessarDocumentoAction::class)->handle($documento, new ReprocessarDocumentoDto(ModoReprocessamento::Modelo));

        expect($resultado->estado)->toBe(EstadoDocumento::Pendente);
        $this->assertDatabaseCount('extracoes_documento', 0);
    });
});
