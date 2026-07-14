<?php

declare(strict_types=1);

use App\Events\DocumentoReprocessado;
use App\Features\Documento\Reprocessar\ModoReprocessamento;
use App\Features\Documento\Reprocessar\ReprocessarDocumentoAction;
use App\Features\Documento\Reprocessar\ReprocessarDocumentoDto;
use App\Models\Documento;
use App\Models\ExtracaoDocumento;
use App\Shared\Enums\EstadoDocumento;
use App\Shared\Enums\EtapaExtracao;
use App\Shared\Exceptions\TransicaoInvalidaException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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

it('exige utilizador autenticado (guest é rejeitado)', function (): void {
    auth()->logout();

    $documento = Documento::factory()->erro()->create();

    expect(fn (): Documento => app(ReprocessarDocumentoAction::class)->handle($documento, new ReprocessarDocumentoDto(ModoReprocessamento::Modelo)))
        ->toThrow(AuthorizationException::class);
});

describe('Reset de extracoes_documento (ripple #94)', function (): void {
    it('reseta a linha extracoes_documento existente para Pendente/zero/null', function (): void {
        $documento = Documento::factory()->erro()->create();
        ExtracaoDocumento::factory()->falhado()->for($documento, 'documento')->create([
            'extracao_tentativas' => 2,
            'texto_extraido' => 'texto anterior',
            'dados_json' => ['nif' => '123456789'],
            'extracao_reclamada_em' => now(),
        ]);
        Storage::disk('erro')->put($documento->nome_ficheiro_storage, 'conteudo');

        app(ReprocessarDocumentoAction::class)->handle($documento, new ReprocessarDocumentoDto(ModoReprocessamento::Modelo));

        $extracao = ExtracaoDocumento::query()->where('id_documento', $documento->id)->sole();

        expect($extracao->etapa_extracao)->toBe(EtapaExtracao::Pendente)
            ->and($extracao->extracao_tentativas)->toBe(0)
            ->and($extracao->texto_extraido)->toBeNull()
            ->and($extracao->dados_json)->toBeNull()
            ->and($extracao->extracao_reclamada_em)->toBeNull();
    });

    it('não cria extracoes_documento quando o documento nunca entrou na dimensão de extracção', function (): void {
        $documento = Documento::factory()->erro()->create();
        Storage::disk('erro')->put($documento->nome_ficheiro_storage, 'conteudo');

        app(ReprocessarDocumentoAction::class)->handle($documento, new ReprocessarDocumentoDto(ModoReprocessamento::Modelo));

        $this->assertDatabaseCount('extracoes_documento', 0);
        expect($documento->fresh()->extracao)->toBeNull();
    });

    it('faz rollback da transição de estado quando o reset da extracoes_documento falha', function (): void {
        $documento = Documento::factory()->erro()->create();
        ExtracaoDocumento::factory()->falhado()->for($documento, 'documento')->create();
        Storage::disk('erro')->put($documento->nome_ficheiro_storage, 'conteudo');

        DB::listen(function (QueryExecuted $query): void {
            if (str_contains($query->sql, 'update `extracoes_documento`')) {
                throw new RuntimeException('falha simulada no reset da extracoes_documento');
            }
        });

        expect(fn (): Documento => app(ReprocessarDocumentoAction::class)->handle($documento, new ReprocessarDocumentoDto(ModoReprocessamento::Modelo)))
            ->toThrow(RuntimeException::class, 'falha simulada no reset da extracoes_documento');

        expect($documento->fresh()->status)->toBe(EstadoDocumento::Erro);
        $this->assertDatabaseCount('etapas_documento', 0);
    });
});
