<?php

declare(strict_types=1);

use App\Features\Documento\Criar\RegistarDocumentoManualDto;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;

function novoDtoManual(array $sobrepor = []): RegistarDocumentoManualDto
{
    return new RegistarDocumentoManualDto(
        idFornecedor: $sobrepor['idFornecedor'] ?? 'fornecedor-uuid',
        idCliente: $sobrepor['idCliente'] ?? 'cliente-uuid',
        idCategoria: $sobrepor['idCategoria'] ?? 'categoria-uuid',
        valor: $sobrepor['valor'] ?? 10.0,
        dataDocumento: Carbon::parse('2026-06-25'),
        ficheiro: UploadedFile::fake()->create('fatura.pdf', 10),
    );
}

it('constrói com dados de domínio e ficheiro válidos', function (): void {
    expect(novoDtoManual()->valor)->toBe(10.0);
});

it('rejeita ids vazios e valor negativo', function (array $sobrepor): void {
    expect(fn (): RegistarDocumentoManualDto => novoDtoManual($sobrepor))->toThrow(InvalidArgumentException::class);
})->with([
    'fornecedor vazio' => [['idFornecedor' => ' ']],
    'cliente vazio' => [['idCliente' => '']],
    'categoria vazia' => [['idCategoria' => '']],
    'valor negativo' => [['valor' => -1.0]],
]);
