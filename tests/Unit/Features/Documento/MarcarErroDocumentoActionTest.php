<?php

declare(strict_types=1);

use App\Events\DocumentoMarcadoErroEvent;
use App\Features\Documento\Operacoes\TransicoesEstado\MarcarErroDocumentoAction;
use App\Features\Documento\Operacoes\TransicoesEstado\MarcarErroDocumentoDto;
use App\Models\Documento;
use App\Models\ExtracaoDocumento;
use App\Shared\Enums\EstadoDocumento;
use App\Shared\Exceptions\TransicaoInvalidaException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

// Transição de sistema (pipeline): corre sem utilizador autenticado, sem Gate.
beforeEach(function (): void {
    Storage::fake('enviado');
    Storage::fake('erro');
});

it('transiciona AnaliseIaLocal → Erro: move enviado → erro, regista o motivo e emite o evento (passo de sistema)', function (): void {
    $documento = Documento::factory()->analiseIaLocal()->create();
    Storage::disk('enviado')->put($documento->nome_ficheiro_storage, 'conteudo');

    Event::fake([DocumentoMarcadoErroEvent::class]);

    $resultado = app(MarcarErroDocumentoAction::class)->handle($documento, new MarcarErroDocumentoDto('timeout do serviço'));

    expect($resultado->estado)->toBe(EstadoDocumento::Erro)
        ->and($resultado->disco_storage)->toBe('erro');

    Storage::disk('erro')->assertExists($documento->nome_ficheiro_storage);
    Storage::disk('enviado')->assertMissing($documento->nome_ficheiro_storage);
    $this->assertDatabaseHas('etapas_documento', [
        'id_documento' => $documento->id,
        'estado' => EstadoDocumento::Erro->value,
        'motivo' => 'timeout do serviço',
        'id_utilizador' => null,
    ]);

    Event::assertDispatched(
        DocumentoMarcadoErroEvent::class,
        fn (DocumentoMarcadoErroEvent $evento): bool => $evento->mensagemErro === 'timeout do serviço',
    );
});

// Cobertura exaustiva das origens documentadas → Erro (RF-03): qualquer passo de
// análise pode falhar. AnaliseIaLocal já está coberto pelo teste principal acima.
it('aceita cada origem de análise → Erro e move para o disco erro', function (string $estadoOrigem, string $discoOrigem): void {
    Storage::fake($discoOrigem);
    $documento = Documento::factory()->{$estadoOrigem}()->create();
    Storage::disk($discoOrigem)->put($documento->nome_ficheiro_storage, 'conteudo');

    $resultado = app(MarcarErroDocumentoAction::class)->handle($documento, new MarcarErroDocumentoDto('falha do passo'));

    expect($resultado->estado)->toBe(EstadoDocumento::Erro)
        ->and($resultado->disco_storage)->toBe('erro');
    Storage::disk('erro')->assertExists($documento->nome_ficheiro_storage);
    Storage::disk($discoOrigem)->assertMissing($documento->nome_ficheiro_storage);
})->with([
    'de AnaliseMalware' => ['analiseMalware', 'entrada'],
    'de AnaliseTexto' => ['analiseTexto', 'entrada'],
    'de AnaliseOcr' => ['analiseOcr', 'entrada'],
    'de AnaliseCloud' => ['analiseCloud', 'enviado'],
]);

it('rejeita a transição a partir de um estado inválido', function (): void {
    $documento = Documento::factory()->processado()->create();

    expect(fn (): Documento => app(MarcarErroDocumentoAction::class)->handle($documento, new MarcarErroDocumentoDto('erro')))
        ->toThrow(TransicaoInvalidaException::class);

    $this->assertDatabaseCount('etapas_documento', 0);
});

// Integração: o Executor invoca RegraEliminarExtracaoTerminal dentro da transacção
// (a lógica exaustiva por estado está em RegraEliminarExtracaoTerminalTest).
it('elimina a ExtracaoDocumento existente ao transicionar para Erro (RGPD, #110)', function (): void {
    $documento = Documento::factory()->analiseIaLocal()->create();
    Storage::disk('enviado')->put($documento->nome_ficheiro_storage, 'conteudo');
    ExtracaoDocumento::factory()->comDadosExtraidos()->for($documento, 'documento')->create();

    app(MarcarErroDocumentoAction::class)->handle($documento, new MarcarErroDocumentoDto('timeout do serviço'));

    $this->assertDatabaseCount('extracoes_documento', 0);
});
