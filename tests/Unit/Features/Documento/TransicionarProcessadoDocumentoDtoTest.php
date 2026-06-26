<?php

declare(strict_types=1);

use App\Features\Documento\TransicionarProcessado\TransicionarProcessadoDocumentoDto;
use Illuminate\Support\Carbon;

function dtoProcessado(array $sobrepor = []): TransicionarProcessadoDocumentoDto
{
    return new TransicionarProcessadoDocumentoDto(
        idFornecedor: $sobrepor['idFornecedor'] ?? 'fornecedor-uuid',
        idCliente: $sobrepor['idCliente'] ?? 'cliente-uuid',
        idCategoria: $sobrepor['idCategoria'] ?? 'categoria-uuid',
        valor: $sobrepor['valor'] ?? 100.0,
        dataDocumento: $sobrepor['dataDocumento'] ?? Carbon::parse('2026-06-25'),
    );
}

it('constrói com dados válidos', function (): void {
    $dto = dtoProcessado();

    expect($dto->idFornecedor)->toBe('fornecedor-uuid')
        ->and($dto->valor)->toBe(100.0)
        ->and($dto->dataDocumento->format('Y-m-d'))->toBe('2026-06-25');
});

it('rejeita ids vazios e valor negativo', function (array $sobrepor): void {
    expect(fn (): \App\Features\Documento\TransicionarProcessado\TransicionarProcessadoDocumentoDto => dtoProcessado($sobrepor))->toThrow(InvalidArgumentException::class);
})->with([
    'fornecedor vazio' => [['idFornecedor' => '  ']],
    'cliente vazio' => [['idCliente' => '']],
    'categoria vazia' => [['idCategoria' => '']],
    'valor negativo' => [['valor' => -1.0]],
]);
