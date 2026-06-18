<?php

declare(strict_types=1);

use App\Features\Entidade\Criar\CriarEntidadeDto;

describe('Construtor', function (): void {
    it('lança InvalidArgumentException se nome for vazio', function (): void {
        expect(fn (): CriarEntidadeDto => new CriarEntidadeDto(
            nome: '',
            nif: '123456789',
            eCliente: true,
            eFornecedor: false,
            eEmpresaAplicacao: false,
        ))->toThrow(InvalidArgumentException::class);
    });

    it('lança InvalidArgumentException se nome for só espaços', function (): void {
        expect(fn (): CriarEntidadeDto => new CriarEntidadeDto(
            nome: '   ',
            nif: '123456789',
            eCliente: true,
            eFornecedor: false,
            eEmpresaAplicacao: false,
        ))->toThrow(InvalidArgumentException::class);
    });

    it('lança InvalidArgumentException se nif for vazio', function (): void {
        expect(fn (): CriarEntidadeDto => new CriarEntidadeDto(
            nome: 'Empresa Teste',
            nif: '',
            eCliente: false,
            eFornecedor: true,
            eEmpresaAplicacao: false,
        ))->toThrow(InvalidArgumentException::class);
    });

    it('lança InvalidArgumentException se nif for só espaços', function (): void {
        expect(fn (): CriarEntidadeDto => new CriarEntidadeDto(
            nome: 'Empresa Teste',
            nif: '   ',
            eCliente: false,
            eFornecedor: true,
            eEmpresaAplicacao: false,
        ))->toThrow(InvalidArgumentException::class);
    });

    it('cria DTO com dados válidos', function (): void {
        $dto = new CriarEntidadeDto(
            nome: 'Empresa Teste',
            nif: '123456789',
            eCliente: true,
            eFornecedor: true,
            eEmpresaAplicacao: false,
        );

        expect($dto->nome)->toBe('Empresa Teste')
            ->and($dto->nif)->toBe('123456789')
            ->and($dto->eCliente)->toBeTrue()
            ->and($dto->eFornecedor)->toBeTrue()
            ->and($dto->eEmpresaAplicacao)->toBeFalse();
    });
});
