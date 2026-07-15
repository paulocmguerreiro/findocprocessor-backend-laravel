<?php

declare(strict_types=1);

use App\Infrastructure\Extracao\ResultadoExtracao;

it('produz um resultado com veredicto de threshold ultrapassado', function (): void {
    $resultado = ResultadoExtracao::comVeredictoThreshold('texto extraído com mais de cinquenta caracteres para o teste', true);

    expect($resultado->texto)->toBe('texto extraído com mais de cinquenta caracteres para o teste')
        ->and($resultado->ultrapassaThreshold)->toBeTrue();
});

it('produz um resultado com veredicto de threshold não ultrapassado', function (): void {
    $resultado = ResultadoExtracao::comVeredictoThreshold('texto curto', false);

    expect($resultado->texto)->toBe('texto curto')
        ->and($resultado->ultrapassaThreshold)->toBeFalse();
});

it('produz um resultado sem veredicto de threshold', function (): void {
    $resultado = ResultadoExtracao::semVeredicto('texto reconhecido por ocr');

    expect($resultado->texto)->toBe('texto reconhecido por ocr')
        ->and($resultado->ultrapassaThreshold)->toBeNull();
});

it('aceita texto vazio como resultado válido', function (): void {
    $comVeredicto = ResultadoExtracao::comVeredictoThreshold('', false);
    $semVeredicto = ResultadoExtracao::semVeredicto('');

    expect($comVeredicto->texto)->toBe('')
        ->and($semVeredicto->texto)->toBe('');
});
