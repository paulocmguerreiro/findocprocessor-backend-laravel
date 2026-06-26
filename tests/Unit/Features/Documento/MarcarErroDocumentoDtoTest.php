<?php

declare(strict_types=1);

use App\Features\Documento\MarcarErro\MarcarErroDocumentoDto;

it('constrói com uma mensagem de erro', function (): void {
    expect((new MarcarErroDocumentoDto('timeout do serviço'))->mensagemErro)
        ->toBe('timeout do serviço');
});

it('rejeita mensagem de erro vazia', function (): void {
    expect(fn (): \App\Features\Documento\MarcarErro\MarcarErroDocumentoDto => new MarcarErroDocumentoDto('   '))
        ->toThrow(InvalidArgumentException::class, 'mensagemErro não pode ser vazia.');
});
