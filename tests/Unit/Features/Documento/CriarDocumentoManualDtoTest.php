<?php

declare(strict_types=1);

use App\Features\Documento\Criar\CriarDocumentoManualDto;

/**
 * @param  array<string, mixed>  $overrides
 */
function criarDocumentoManualDto(array $overrides = []): CriarDocumentoManualDto
{
    /** @var array{idFornecedor: string, idCliente: string, idCategoria: string, valor: float, dataDocumento: DateTimeInterface, nomeFicheiroOriginal: string, discoStorage: string, nomeFicheiroStorage: string, hashSha256: string} $args */
    $args = array_merge([
        'idFornecedor' => '018f1a2b-3c4d-7e5f-8a9b-0c1d2e3f4a5b',
        'idCliente' => '018f1a2b-3c4d-7e5f-8a9b-0c1d2e3f4a6c',
        'idCategoria' => '018f1a2b-3c4d-7e5f-8a9b-0c1d2e3f4a7d',
        'valor' => 100.0,
        'dataDocumento' => new DateTimeImmutable('2026-01-15'),
        'nomeFicheiroOriginal' => 'fatura.pdf',
        'discoStorage' => 'processado',
        'nomeFicheiroStorage' => 'abc.pdf',
        'hashSha256' => str_repeat('a', 64),
    ], $overrides);

    return new CriarDocumentoManualDto(...$args);
}

describe('Construtor — happy path', function (): void {
    it('cria DTO com dados válidos', function (): void {
        $dto = criarDocumentoManualDto();

        expect($dto->idFornecedor)->toBe('018f1a2b-3c4d-7e5f-8a9b-0c1d2e3f4a5b')
            ->and($dto->valor)->toBe(100.0)
            ->and($dto->hashSha256)->toHaveLength(64);
    });

    it('aceita valor zero', function (): void {
        expect(criarDocumentoManualDto(['valor' => 0.0])->valor)->toBe(0.0);
    });
});

describe('Construtor — invariantes', function (): void {
    it('rejeita idFornecedor vazio', function (): void {
        expect(fn (): CriarDocumentoManualDto => criarDocumentoManualDto(['idFornecedor' => '  ']))
            ->toThrow(InvalidArgumentException::class);
    });

    it('rejeita idCliente vazio', function (): void {
        expect(fn (): CriarDocumentoManualDto => criarDocumentoManualDto(['idCliente' => '']))
            ->toThrow(InvalidArgumentException::class);
    });

    it('rejeita idCategoria vazio', function (): void {
        expect(fn (): CriarDocumentoManualDto => criarDocumentoManualDto(['idCategoria' => '']))
            ->toThrow(InvalidArgumentException::class);
    });

    it('rejeita valor negativo', function (): void {
        expect(fn (): CriarDocumentoManualDto => criarDocumentoManualDto(['valor' => -0.01]))
            ->toThrow(InvalidArgumentException::class);
    });

    it('rejeita nomeFicheiroOriginal vazio', function (): void {
        expect(fn (): CriarDocumentoManualDto => criarDocumentoManualDto(['nomeFicheiroOriginal' => '']))
            ->toThrow(InvalidArgumentException::class);
    });

    it('rejeita discoStorage vazio', function (): void {
        expect(fn (): CriarDocumentoManualDto => criarDocumentoManualDto(['discoStorage' => '']))
            ->toThrow(InvalidArgumentException::class);
    });

    it('rejeita nomeFicheiroStorage vazio', function (): void {
        expect(fn (): CriarDocumentoManualDto => criarDocumentoManualDto(['nomeFicheiroStorage' => '']))
            ->toThrow(InvalidArgumentException::class);
    });

    it('rejeita hashSha256 com comprimento diferente de 64', function (): void {
        expect(fn (): CriarDocumentoManualDto => criarDocumentoManualDto(['hashSha256' => str_repeat('a', 63)]))
            ->toThrow(InvalidArgumentException::class);
    });
});
