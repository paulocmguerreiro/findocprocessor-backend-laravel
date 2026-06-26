<?php

declare(strict_types=1);

use App\Features\Documento\MarcarPerigoso\MarcarPerigosoDocumentoDto;

it('constrói com um motivo', function (): void {
    expect((new MarcarPerigosoDocumentoDto('injecção detectada'))->motivo)
        ->toBe('injecção detectada');
});

it('rejeita motivo vazio', function (): void {
    expect(fn (): \App\Features\Documento\MarcarPerigoso\MarcarPerigosoDocumentoDto => new MarcarPerigosoDocumentoDto(''))
        ->toThrow(InvalidArgumentException::class, 'motivo não pode ser vazio.');
});
