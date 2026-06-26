<?php

declare(strict_types=1);

use App\Features\Documento\Corrigir\CorrigirDocumentoDto;
use Illuminate\Support\Carbon;

function dtoCorrigir(array $sobrepor = []): CorrigirDocumentoDto
{
    return new CorrigirDocumentoDto(
        idFornecedor: $sobrepor['idFornecedor'] ?? 'fornecedor-uuid',
        idCliente: $sobrepor['idCliente'] ?? 'cliente-uuid',
        idCategoria: $sobrepor['idCategoria'] ?? 'categoria-uuid',
        valor: $sobrepor['valor'] ?? 50.0,
        dataDocumento: $sobrepor['dataDocumento'] ?? Carbon::parse('2026-06-25'),
    );
}

it('constrói com dados de domínio válidos', function (): void {
    expect(dtoCorrigir()->valor)->toBe(50.0);
});

it('rejeita ids vazios e valor negativo', function (array $sobrepor): void {
    expect(fn (): CorrigirDocumentoDto => dtoCorrigir($sobrepor))->toThrow(InvalidArgumentException::class);
})->with([
    'fornecedor vazio' => [['idFornecedor' => ' ']],
    'cliente vazio' => [['idCliente' => '']],
    'categoria vazia' => [['idCategoria' => '']],
    'valor negativo' => [['valor' => -5.0]],
]);
