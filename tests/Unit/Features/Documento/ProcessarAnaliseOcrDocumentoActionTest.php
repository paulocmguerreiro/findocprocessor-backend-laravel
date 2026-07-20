<?php

declare(strict_types=1);

use App\Features\Documento\Processamento\ProcessarAnaliseOcrDocumentoAction;
use App\Models\Documento;
use App\Models\ExtracaoDocumento;
use App\Shared\Enums\EstadoDocumento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

require_once __DIR__.'/../../../Support/gera_pdf_imagem.php';

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Storage::fake('entrada');
    Storage::fake('erro');
});

// Usa o ExtractorOcr real (final, não-mockável) sobre um pdf-imagem gerado com
// Ghostscript — corre Tesseract de facto, como o ExtractorOcrTest de #96.
function documentoAnaliseOcrComPdfCorrompido(): Documento
{
    $documento = Documento::factory()->analiseOcr()->create(['nome_ficheiro_storage' => 'scan.pdf']);
    Storage::disk('entrada')->put('scan.pdf', (string) file_get_contents(base_path('tests/Fixtures/Extracao/pdf-corrompido.pdf')));

    return $documento;
}

it('OCR com sucesso → AnaliseIaLocal (texto registado)', function (): void {
    $temp = storage_path('app/temp/'.Str::uuid()->toString().'.pdf');
    gera_pdf_imagem($temp, ['PALAVRACHAVEUM documento digitalizado']);
    $documento = Documento::factory()->analiseOcr()->create(['nome_ficheiro_storage' => 'scan.pdf']);
    Storage::disk('entrada')->put('scan.pdf', (string) file_get_contents($temp));
    unlink($temp);

    $resultado = app(ProcessarAnaliseOcrDocumentoAction::class)->handle();

    expect($resultado?->id)->toBe($documento->id)
        ->and($resultado?->estado)->toBe(EstadoDocumento::AnaliseIaLocal)
        ->and(ExtracaoDocumento::query()->where('id_documento', $documento->id)->value('texto_extraido'))
        ->not->toBeNull();
});

it('falha técnica do OCR incrementa a tentativa e mantém o documento em AnaliseOcr', function (): void {
    $documento = documentoAnaliseOcrComPdfCorrompido();

    $resultado = app(ProcessarAnaliseOcrDocumentoAction::class)->handle();

    expect($resultado?->estado)->toBe(EstadoDocumento::AnaliseOcr);
    $this->assertDatabaseHas('extracoes_documento', [
        'id_documento' => $documento->id,
        'extracao_tentativas' => 1,
    ]);
});

it('à max_tentativas de falha técnica do OCR o documento vai a Erro', function (): void {
    $documento = documentoAnaliseOcrComPdfCorrompido();
    ExtracaoDocumento::factory()->comTentativas(config()->integer('extracao.max_tentativas') - 1)
        ->for($documento, 'documento')->create();

    $resultado = app(ProcessarAnaliseOcrDocumentoAction::class)->handle();

    expect($resultado?->estado)->toBe(EstadoDocumento::Erro);
});

it('devolve null quando não há documento em AnaliseOcr para reclamar', function (): void {
    Documento::factory()->analiseTexto()->create();

    expect(app(ProcessarAnaliseOcrDocumentoAction::class)->handle())->toBeNull();
});
