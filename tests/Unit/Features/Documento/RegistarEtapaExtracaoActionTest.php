<?php

declare(strict_types=1);

use App\Features\Documento\Processamento\RegistarEtapaExtracaoAction;
use App\Features\Documento\Processamento\RegistarEtapaExtracaoDto;
use App\Models\Documento;
use App\Models\EtapaDocumento;
use App\Models\ExtracaoDocumento;
use App\Shared\Enums\ResultadoEtapa;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// Acção de sistema (pipeline): corre sem utilizador autenticado, sem Gate.
it('regista o primeiro passo: cria extracoes_documento + EtapaDocumento com resultado e estado igual ao actual', function (): void {
    $documento = Documento::factory()->analiseIaLocal()->create();

    $dados = new RegistarEtapaExtracaoDto(ResultadoEtapa::Sucesso, textoExtraido: 'conteúdo ocr');

    $resultado = app(RegistarEtapaExtracaoAction::class)->handle($documento, $dados);

    expect($resultado)->toBeInstanceOf(ExtracaoDocumento::class)
        ->and($resultado->texto_extraido)->toBe('conteúdo ocr');

    $this->assertDatabaseCount('extracoes_documento', 1);
    $this->assertDatabaseHas('etapas_documento', [
        'id_documento' => $documento->id,
        'estado' => $documento->estado->value,
        'resultado' => ResultadoEtapa::Sucesso->value,
        'id_utilizador' => null,
    ]);
});

it('reinvocação faz upsert da extracoes_documento (sem duplicar) e acrescenta uma segunda EtapaDocumento', function (): void {
    $documento = Documento::factory()->analiseIaLocal()->create();
    $action = app(RegistarEtapaExtracaoAction::class);

    $action->handle($documento, new RegistarEtapaExtracaoDto(ResultadoEtapa::EmCurso));
    $action->handle($documento, new RegistarEtapaExtracaoDto(ResultadoEtapa::Sucesso));

    $this->assertDatabaseCount('extracoes_documento', 1);
    $this->assertDatabaseCount('etapas_documento', 2);
});

it('substitui totalmente texto_extraido/dados_json em cada chamada (sem merge)', function (): void {
    $documento = Documento::factory()->analiseIaLocal()->create();
    $action = app(RegistarEtapaExtracaoAction::class);

    $action->handle($documento, new RegistarEtapaExtracaoDto(
        ResultadoEtapa::Sucesso,
        textoExtraido: 'primeiro texto',
        dadosJson: ['nif' => '123456789'],
    ));

    $resultado = $action->handle($documento, new RegistarEtapaExtracaoDto(ResultadoEtapa::Sucesso));

    expect($resultado->texto_extraido)->toBeNull()
        ->and($resultado->dados_json)->toBeNull();
});

it('incrementarTentativas: true em duas chamadas sucessivas leva extracao_tentativas a 2', function (): void {
    $documento = Documento::factory()->analiseIaLocal()->create();
    $action = app(RegistarEtapaExtracaoAction::class);

    $dados = new RegistarEtapaExtracaoDto(ResultadoEtapa::Falha, motivo: 'timeout', incrementarTentativas: true);

    $action->handle($documento, $dados);
    $resultado = $action->handle($documento, $dados);

    expect($resultado->extracao_tentativas)->toBe(2);
});

it('reclamar: true preenche extracao_reclamada_em; false (default) mantém null', function (): void {
    $documento = Documento::factory()->analiseIaLocal()->create();
    $action = app(RegistarEtapaExtracaoAction::class);

    $reclamado = $action->handle($documento, new RegistarEtapaExtracaoDto(ResultadoEtapa::EmCurso, reclamar: true));
    expect($reclamado->extracao_reclamada_em)->not->toBeNull();

    $libertado = $action->handle($documento, new RegistarEtapaExtracaoDto(ResultadoEtapa::Sucesso));
    expect($libertado->extracao_reclamada_em)->toBeNull();
});

it('faz rollback quando ocorre excepção entre o upsert e a EtapaDocumento: nenhuma alteração fica persistida', function (): void {
    $documento = Documento::factory()->analiseIaLocal()->create();

    EtapaDocumento::creating(function (): void {
        throw new RuntimeException('falha simulada antes do insert da EtapaDocumento');
    });

    $dados = new RegistarEtapaExtracaoDto(ResultadoEtapa::Sucesso);

    expect(fn (): ExtracaoDocumento => app(RegistarEtapaExtracaoAction::class)->handle($documento, $dados))
        ->toThrow(RuntimeException::class, 'falha simulada antes do insert da EtapaDocumento');

    $this->assertDatabaseCount('extracoes_documento', 0);
    $this->assertDatabaseCount('etapas_documento', 0);
});

it('não exige utilizador autenticado (acção de sistema, sem Gate)', function (): void {
    auth()->logout();

    $documento = Documento::factory()->analiseIaLocal()->create();

    $resultado = app(RegistarEtapaExtracaoAction::class)->handle(
        $documento,
        new RegistarEtapaExtracaoDto(ResultadoEtapa::EmCurso),
    );

    expect($resultado)->toBeInstanceOf(ExtracaoDocumento::class);
});
