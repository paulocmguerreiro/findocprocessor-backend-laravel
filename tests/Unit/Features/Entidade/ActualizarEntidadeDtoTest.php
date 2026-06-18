<?php

declare(strict_types=1);

use App\Features\Entidade\Actualizar\ActualizarEntidadeDto;

describe('Construtor', function (): void {
    it('lança InvalidArgumentException se nome for vazio', function (): void {
        expect(fn (): ActualizarEntidadeDto => new ActualizarEntidadeDto(
            nome: '',
            nif: '123456789',
            eCliente: true,
            eFornecedor: false,
            eEmpresaAplicacao: false,
        ))->toThrow(InvalidArgumentException::class);
    });

    it('lança InvalidArgumentException se nome for só espaços', function (): void {
        expect(fn (): ActualizarEntidadeDto => new ActualizarEntidadeDto(
            nome: '   ',
            nif: '123456789',
            eCliente: true,
            eFornecedor: false,
            eEmpresaAplicacao: false,
        ))->toThrow(InvalidArgumentException::class);
    });

    it('lança InvalidArgumentException se nif for vazio', function (): void {
        expect(fn (): ActualizarEntidadeDto => new ActualizarEntidadeDto(
            nome: 'Empresa Teste',
            nif: '',
            eCliente: false,
            eFornecedor: true,
            eEmpresaAplicacao: false,
        ))->toThrow(InvalidArgumentException::class);
    });

    it('lança InvalidArgumentException se nif for só espaços', function (): void {
        expect(fn (): ActualizarEntidadeDto => new ActualizarEntidadeDto(
            nome: 'Empresa Teste',
            nif: '   ',
            eCliente: false,
            eFornecedor: true,
            eEmpresaAplicacao: false,
        ))->toThrow(InvalidArgumentException::class);
    });

    it('cria DTO com dados válidos', function (): void {
        $dto = new ActualizarEntidadeDto(
            nome: 'Empresa Actualizada',
            nif: '987654321',
            eCliente: false,
            eFornecedor: true,
            eEmpresaAplicacao: false,
        );

        expect($dto->nome)->toBe('Empresa Actualizada')
            ->and($dto->nif)->toBe('987654321')
            ->and($dto->eCliente)->toBeFalse()
            ->and($dto->eFornecedor)->toBeTrue()
            ->and($dto->eEmpresaAplicacao)->toBeFalse();
    });
});
