<?php

declare(strict_types=1);

use App\Features\TipoDocumento\Actualizar\ActualizarTipoDocumentoDto;
use App\Features\TipoDocumento\Actualizar\ActualizarTipoDocumentoRequest;
use App\Shared\Enums\PosicaoEmpresaMae;

describe('Construtor', function (): void {
    it('cria DTO com dados válidos', function (): void {
        $dto = new ActualizarTipoDocumentoDto(
            nome: 'Fatura Fornecedor',
            descricao: 'Fatura emitida por um fornecedor',
            idCategoria: '018f1a2b-3c4d-7e5f-8a9b-0c1d2e3f4a5b',
            posicaoEmpresaMae: PosicaoEmpresaMae::Cliente,
            esperaDataDocumento: true,
            esperaFornecedor: true,
            esperaCliente: false,
            esperaValor: true,
        );

        expect($dto->nome)->toBe('Fatura Fornecedor')
            ->and($dto->descricao)->toBe('Fatura emitida por um fornecedor')
            ->and($dto->idCategoria)->toBe('018f1a2b-3c4d-7e5f-8a9b-0c1d2e3f4a5b')
            ->and($dto->posicaoEmpresaMae)->toBe(PosicaoEmpresaMae::Cliente)
            ->and($dto->esperaDataDocumento)->toBeTrue()
            ->and($dto->esperaFornecedor)->toBeTrue()
            ->and($dto->esperaCliente)->toBeFalse()
            ->and($dto->esperaValor)->toBeTrue();
    });

    it('lança InvalidArgumentException se nome for vazio', function (): void {
        expect(fn (): ActualizarTipoDocumentoDto => new ActualizarTipoDocumentoDto(
            nome: '   ',
            descricao: 'Descrição válida',
            idCategoria: '018f1a2b-3c4d-7e5f-8a9b-0c1d2e3f4a5b',
            posicaoEmpresaMae: PosicaoEmpresaMae::Fornecedor,
            esperaDataDocumento: true,
            esperaFornecedor: false,
            esperaCliente: false,
            esperaValor: false,
        ))->toThrow(InvalidArgumentException::class);
    });

    it('lança InvalidArgumentException se descricao for vazia', function (): void {
        expect(fn (): ActualizarTipoDocumentoDto => new ActualizarTipoDocumentoDto(
            nome: 'Nome válido',
            descricao: '   ',
            idCategoria: '018f1a2b-3c4d-7e5f-8a9b-0c1d2e3f4a5b',
            posicaoEmpresaMae: PosicaoEmpresaMae::Fornecedor,
            esperaDataDocumento: true,
            esperaFornecedor: false,
            esperaCliente: false,
            esperaValor: false,
        ))->toThrow(InvalidArgumentException::class);
    });

    it('lança InvalidArgumentException se idCategoria for vazio', function (): void {
        expect(fn (): ActualizarTipoDocumentoDto => new ActualizarTipoDocumentoDto(
            nome: 'Nome válido',
            descricao: 'Descrição válida',
            idCategoria: '   ',
            posicaoEmpresaMae: PosicaoEmpresaMae::Fornecedor,
            esperaDataDocumento: true,
            esperaFornecedor: false,
            esperaCliente: false,
            esperaValor: false,
        ))->toThrow(InvalidArgumentException::class);
    });

    it('lança InvalidArgumentException se os 4 espera_* forem todos false', function (): void {
        expect(fn (): ActualizarTipoDocumentoDto => new ActualizarTipoDocumentoDto(
            nome: 'Nome válido',
            descricao: 'Descrição válida',
            idCategoria: '018f1a2b-3c4d-7e5f-8a9b-0c1d2e3f4a5b',
            posicaoEmpresaMae: PosicaoEmpresaMae::Fornecedor,
            esperaDataDocumento: false,
            esperaFornecedor: false,
            esperaCliente: false,
            esperaValor: false,
        ))->toThrow(InvalidArgumentException::class);
    });

    it('aceita com apenas 1 espera_* a true', function (): void {
        $dto = new ActualizarTipoDocumentoDto(
            nome: 'Nome válido',
            descricao: 'Descrição válida',
            idCategoria: '018f1a2b-3c4d-7e5f-8a9b-0c1d2e3f4a5b',
            posicaoEmpresaMae: PosicaoEmpresaMae::Fornecedor,
            esperaDataDocumento: false,
            esperaFornecedor: false,
            esperaCliente: false,
            esperaValor: true,
        );

        expect($dto->esperaValor)->toBeTrue();
    });
});

describe('fromRequest()', function (): void {
    it('cria DTO a partir de request válido', function (): void {
        $request = Mockery::mock(ActualizarTipoDocumentoRequest::class);
        $request->shouldReceive('validated')->andReturn([
            'nome' => 'Fatura Fornecedor',
            'descricao' => 'Fatura emitida por um fornecedor',
            'id_categoria' => '018f1a2b-3c4d-7e5f-8a9b-0c1d2e3f4a5b',
            'posicao_empresa_mae' => PosicaoEmpresaMae::Cliente->value,
            'espera_data_documento' => true,
            'espera_fornecedor' => true,
            'espera_cliente' => false,
            'espera_valor' => true,
        ]);

        $dto = ActualizarTipoDocumentoDto::fromRequest($request);

        expect($dto->nome)->toBe('Fatura Fornecedor')
            ->and($dto->descricao)->toBe('Fatura emitida por um fornecedor')
            ->and($dto->idCategoria)->toBe('018f1a2b-3c4d-7e5f-8a9b-0c1d2e3f4a5b')
            ->and($dto->posicaoEmpresaMae)->toBe(PosicaoEmpresaMae::Cliente)
            ->and($dto->esperaDataDocumento)->toBeTrue()
            ->and($dto->esperaFornecedor)->toBeTrue()
            ->and($dto->esperaCliente)->toBeFalse()
            ->and($dto->esperaValor)->toBeTrue();
    });
});
