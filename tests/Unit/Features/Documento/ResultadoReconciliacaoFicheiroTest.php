<?php

declare(strict_types=1);

use App\Features\Documento\Operacoes\Transicao\ResultadoReconciliacaoFicheiro;

it('constrói um resultado coerente com disco e nome preenchidos', function (): void {
    $resultado = new ResultadoReconciliacaoFicheiro(coerente: true, encontrado: true, disco: 'entrada', nome: 'ficheiro.pdf');

    expect($resultado->coerente)->toBeTrue()
        ->and($resultado->encontrado)->toBeTrue()
        ->and($resultado->disco)->toBe('entrada')
        ->and($resultado->nome)->toBe('ficheiro.pdf');
});

it('constrói um resultado não encontrado sem disco nem nome', function (): void {
    $resultado = new ResultadoReconciliacaoFicheiro(coerente: false, encontrado: false);

    expect($resultado->encontrado)->toBeFalse()
        ->and($resultado->disco)->toBeNull()
        ->and($resultado->nome)->toBeNull();
});

it('lança InvalidArgumentException quando encontrado é true sem disco', function (): void {
    expect(fn (): ResultadoReconciliacaoFicheiro => new ResultadoReconciliacaoFicheiro(coerente: false, encontrado: true, nome: 'ficheiro.pdf'))
        ->toThrow(InvalidArgumentException::class, 'disco e nome são obrigatórios quando encontrado é true.');
});

it('lança InvalidArgumentException quando encontrado é true sem nome', function (): void {
    expect(fn (): ResultadoReconciliacaoFicheiro => new ResultadoReconciliacaoFicheiro(coerente: false, encontrado: true, disco: 'entrada'))
        ->toThrow(InvalidArgumentException::class, 'disco e nome são obrigatórios quando encontrado é true.');
});
