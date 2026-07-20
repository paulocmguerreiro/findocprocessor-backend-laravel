<?php

declare(strict_types=1);

use App\Features\Documento\Processamento\ProcessarAnaliseTextoDocumentoAction;
use App\Models\Documento;
use App\Models\ExtracaoDocumento;
use App\Shared\Enums\EstadoDocumento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Storage::fake('entrada');
    Storage::fake('erro');
});

// Usa o ExtractorTextoNativo real (é final, não-mockável) sobre fixtures PDF de
// #96 — testa a integração parser↔orquestrador de facto, não um duplo.
function documentoAnaliseTextoCom(string $nomeStorage, string $conteudo): Documento
{
    $documento = Documento::factory()->analiseTexto()->create(['nome_ficheiro_storage' => $nomeStorage]);
    Storage::disk('entrada')->put($nomeStorage, $conteudo);

    return $documento;
}

function fixturaExtracao(string $nome): string
{
    return (string) file_get_contents(base_path("tests/Fixtures/Extracao/{$nome}"));
}

it('PDF acima do threshold → AnaliseIaLocal (texto registado)', function (): void {
    $documento = documentoAnaliseTextoCom('scan.pdf', fixturaExtracao('pdf-digital.pdf'));

    $resultado = app(ProcessarAnaliseTextoDocumentoAction::class)->handle();

    expect($resultado?->id)->toBe($documento->id)
        ->and($resultado?->estado)->toBe(EstadoDocumento::AnaliseIaLocal)
        ->and(ExtracaoDocumento::query()->where('id_documento', $documento->id)->value('texto_extraido'))
        ->toContain('FinDocProcessor');
});

it('PDF abaixo do threshold → AnaliseOcr', function (): void {
    documentoAnaliseTextoCom('scan.pdf', fixturaExtracao('pdf-digital-curto.pdf'));

    $resultado = app(ProcessarAnaliseTextoDocumentoAction::class)->handle();

    expect($resultado?->estado)->toBe(EstadoDocumento::AnaliseOcr);
});

it('ficheiro imagem (não-PDF) salta o parser e vai directo a AnaliseOcr', function (string $nomeImagem): void {
    documentoAnaliseTextoCom($nomeImagem, 'bytes-de-imagem');

    $resultado = app(ProcessarAnaliseTextoDocumentoAction::class)->handle();

    expect($resultado?->estado)->toBe(EstadoDocumento::AnaliseOcr);
})->with(['jpg' => ['recibo.jpg'], 'png' => ['recibo.png'], 'tiff' => ['recibo.tiff']]);

it('falha técnica incrementa a tentativa e mantém o documento em AnaliseTexto (antes do tecto)', function (): void {
    $documento = documentoAnaliseTextoCom('scan.pdf', fixturaExtracao('pdf-corrompido.pdf'));

    $resultado = app(ProcessarAnaliseTextoDocumentoAction::class)->handle();

    expect($resultado?->estado)->toBe(EstadoDocumento::AnaliseTexto);
    $this->assertDatabaseHas('extracoes_documento', [
        'id_documento' => $documento->id,
        'extracao_tentativas' => 1,
    ]);
});

it('à max_tentativas de falha técnica o documento vai a Erro', function (): void {
    $documento = documentoAnaliseTextoCom('scan.pdf', fixturaExtracao('pdf-corrompido.pdf'));
    ExtracaoDocumento::factory()->comTentativas(config()->integer('extracao.max_tentativas') - 1)
        ->for($documento, 'documento')->create();

    $resultado = app(ProcessarAnaliseTextoDocumentoAction::class)->handle();

    expect($resultado?->estado)->toBe(EstadoDocumento::Erro);
    $this->assertDatabaseHas('etapas_documento', [
        'id_documento' => $documento->id,
        'estado' => EstadoDocumento::Erro->value,
    ]);
});

it('devolve null quando não há documento em AnaliseTexto para reclamar', function (): void {
    Documento::factory()->analiseOcr()->create();

    expect(app(ProcessarAnaliseTextoDocumentoAction::class)->handle())->toBeNull();
});
