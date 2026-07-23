<?php

declare(strict_types=1);

use App\Infrastructure\Extracao\HocrSimplificador;

/**
 * Constrói uma palavra hOCR (`ocrx_word`) com a sua caixa `bbox`.
 */
function palavraHocr(string $texto, int $x0, int $y0, int $x1, int $y1): string
{
    return "<span class='ocrx_word' title='bbox {$x0} {$y0} {$x1} {$y1}; x_wconf 95'>{$texto}</span>";
}

/**
 * Envolve palavras numa página hOCR mínima (o simplificador só olha para `ocrx_word`).
 *
 * @param  list<string>  $palavras
 */
function paginaHocr(array $palavras): string
{
    $corpo = implode("\n", $palavras);

    return <<<HTML
        <!DOCTYPE html>
        <html><body>
        <div class='ocr_page' title='bbox 0 0 794 1123'>
        <div class='ocr_carea'><p class='ocr_par'><span class='ocr_line'>
        {$corpo}
        </span></p></div>
        </div>
        </body></html>
        HTML;
}

it('devolve string vazia para hOCR vazio', function (): void {
    expect((new HocrSimplificador)->simplificar(''))->toBe('')
        ->and((new HocrSimplificador)->simplificar('   '))->toBe('');
});

it('devolve string vazia quando não há palavras (ocrx_word)', function (): void {
    expect((new HocrSimplificador)->simplificar('<html><body><p>sem palavras</p></body></html>'))->toBe('');
});

it('separa duas colunas do cabeçalho: o NIF do emissor não fica no bloco da data', function (): void {
    // Coluna esquerda (emissor) e coluna direita (metadados) à mesma altura — o bloco
    // de área do Tesseract cruzá-las-ia; o agrupamento 2D reconstrói-as separadas.
    $hocr = paginaHocr([
        palavraHocr('FinDoc', 60, 80, 140, 100),
        palavraHocr('Lda', 145, 80, 190, 100),
        palavraHocr('Contribuinte', 60, 110, 200, 130),
        palavraHocr('501234567', 205, 110, 300, 130),
        palavraHocr('FATURA', 500, 80, 600, 100),
        palavraHocr('Data:', 500, 110, 560, 130),
        palavraHocr('2026-07-18', 565, 110, 700, 130),
    ]);

    $saida = (new HocrSimplificador)->simplificar($hocr);
    $blocos = explode("\n", $saida);

    $blocoNif = collect($blocos)->first(fn (string $b): bool => str_contains($b, '501234567'));
    $blocoData = collect($blocos)->first(fn (string $b): bool => str_contains($b, '2026-07-18'));

    expect($blocos)->toHaveCount(2)
        ->and($blocoNif)->not->toContain('2026-07-18')
        ->and($blocoData)->toContain('FATURA')
        ->and($blocoData)->not->toContain('501234567');
});

it('ignora palavras sem bbox no título ou com texto vazio', function (): void {
    $hocr = paginaHocr([
        "<span class='ocrx_word' title='x_wconf 90'>SemBbox</span>",
        "<span class='ocrx_word' title='bbox 60 80 140 100; x_wconf 95'>   </span>",
        palavraHocr('Valida', 60, 110, 140, 130),
    ]);

    $saida = (new HocrSimplificador)->simplificar($hocr);

    expect($saida)->toContain('Valida')
        ->and($saida)->not->toContain('SemBbox');
});

it('agrupa verticalmente a mesma coluna e mantém a ordem de leitura', function (): void {
    $hocr = paginaHocr([
        palavraHocr('FinDoc', 60, 80, 140, 100),
        palavraHocr('Lda', 145, 80, 190, 100),
        palavraHocr('Contribuinte', 60, 110, 200, 130),
        palavraHocr('501234567', 205, 110, 300, 130),
    ]);

    $saida = (new HocrSimplificador)->simplificar($hocr);

    expect($saida)->toContain('<block bbox=')
        ->and($saida)->toContain('FinDoc Lda Contribuinte 501234567');
});
