<?php

declare(strict_types=1);

use App\Features\Documento\Operacoes\TransicionarProcessado\TransicionarProcessadoDocumentoDto;
use Illuminate\Support\Carbon;

function dtoProcessado(array $sobrepor = []): TransicionarProcessadoDocumentoDto
{
    return new TransicionarProcessadoDocumentoDto(
        idFornecedor: array_key_exists('idFornecedor', $sobrepor) ? $sobrepor['idFornecedor'] : 'fornecedor-uuid',
        idCliente: array_key_exists('idCliente', $sobrepor) ? $sobrepor['idCliente'] : 'cliente-uuid',
        idCategoria: array_key_exists('idCategoria', $sobrepor) ? $sobrepor['idCategoria'] : 'categoria-uuid',
        valor: array_key_exists('valor', $sobrepor) ? $sobrepor['valor'] : 100.0,
        dataDocumento: array_key_exists('dataDocumento', $sobrepor) ? $sobrepor['dataDocumento'] : Carbon::parse('2026-06-25'),
    );
}

it('constrói com dados válidos', function (): void {
    $dto = dtoProcessado();

    expect($dto->idFornecedor)->toBe('fornecedor-uuid')
        ->and($dto->valor)->toBe(100.0)
        ->and($dto->dataDocumento->format('Y-m-d'))->toBe('2026-06-25');
});

it('constrói um documento parcial: só o lado da empresa mãe preenchido', function (): void {
    $dto = new TransicionarProcessadoDocumentoDto(
        idFornecedor: null,
        idCliente: 'cliente-uuid',
        idCategoria: 'categoria-uuid',
        valor: null,
        dataDocumento: null,
        nomeFornecedorExtraido: 'Banco XYZ',
    );

    expect($dto->idFornecedor)->toBeNull()
        ->and($dto->idCliente)->toBe('cliente-uuid')
        ->and($dto->valor)->toBeNull()
        ->and($dto->dataDocumento)->toBeNull()
        ->and($dto->nomeFornecedorExtraido)->toBe('Banco XYZ');
});

it('rejeita quando ambos os lados de entidade são nulos', function (): void {
    expect(fn (): TransicionarProcessadoDocumentoDto => dtoProcessado(['idFornecedor' => null, 'idCliente' => null]))
        ->toThrow(InvalidArgumentException::class);
});

it('rejeita ids vazios e valor negativo', function (array $sobrepor): void {
    expect(fn (): TransicionarProcessadoDocumentoDto => dtoProcessado($sobrepor))->toThrow(InvalidArgumentException::class);
})->with([
    'fornecedor vazio' => [['idFornecedor' => '  ']],
    'cliente vazio' => [['idCliente' => '']],
    'categoria vazia' => [['idCategoria' => '']],
    'valor negativo' => [['valor' => -1.0]],
]);
