<?php

declare(strict_types=1);

use App\Infrastructure\Extracao\ExtractorTextoNativo;
use App\Infrastructure\Extracao\FalhaExtracaoTextoException;
use App\Infrastructure\Extracao\ResultadoExtracao;

it('extrai o texto de um pdf digital e ultrapassa o threshold', function (): void {
    $resultado = (new ExtractorTextoNativo)->extrair(base_path('tests/Fixtures/Extracao/pdf-digital.pdf'));

    expect($resultado->texto)->toContain('FinDocProcessor')
        ->and($resultado->ultrapassaThreshold)->toBeTrue();
});

it('não ultrapassa o threshold quando o texto é curto', function (): void {
    $resultado = (new ExtractorTextoNativo)->extrair(base_path('tests/Fixtures/Extracao/pdf-digital-curto.pdf'));

    expect($resultado->texto)->toContain('Texto curto')
        ->and($resultado->ultrapassaThreshold)->toBeFalse();
});

it('lança FalhaExtracaoTextoException quando o ficheiro está corrompido', function (): void {
    expect(fn (): ResultadoExtracao => (new ExtractorTextoNativo)->extrair(base_path('tests/Fixtures/Extracao/pdf-corrompido.pdf')))
        ->toThrow(FalhaExtracaoTextoException::class);
});
