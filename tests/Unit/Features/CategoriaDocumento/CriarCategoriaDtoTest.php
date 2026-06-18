<?php

declare(strict_types=1);

use App\Features\CategoriaDocumento\Criar\CriarCategoriaDto;
use App\Features\CategoriaDocumento\Criar\CriarCategoriaRequest;
use App\Shared\Enums\TipoMovimento;

describe('Construtor', function (): void {
    it('lança InvalidArgumentException se nome for vazio', function (): void {
        expect(fn (): CriarCategoriaDto => new CriarCategoriaDto(
            nome: '',
            slug: 'slug-valido',
            tipoMovimento: TipoMovimento::Debito,
        ))->toThrow(InvalidArgumentException::class);
    });

    it('lança InvalidArgumentException se slug for vazio', function (): void {
        expect(fn (): CriarCategoriaDto => new CriarCategoriaDto(
            nome: 'Nome Válido',
            slug: '',
            tipoMovimento: TipoMovimento::Debito,
        ))->toThrow(InvalidArgumentException::class);
    });

    it('cria DTO com dados válidos', function (): void {
        $dto = new CriarCategoriaDto(
            nome: 'Categoria Teste',
            slug: 'categoria-teste',
            tipoMovimento: TipoMovimento::Credito,
        );

        expect($dto->nome)->toBe('Categoria Teste')
            ->and($dto->slug)->toBe('categoria-teste')
            ->and($dto->tipoMovimento)->toBe(TipoMovimento::Credito);
    });
});

describe('fromRequest()', function (): void {
    it('cria DTO a partir de request válido', function (): void {
        $request = Mockery::mock(CriarCategoriaRequest::class);
        $request->shouldReceive('validated')->andReturn([
            'nome' => 'Categoria Teste',
            'slug' => 'categoria-teste',
            'tipo_movimento' => TipoMovimento::Debito->value,
        ]);

        $dto = CriarCategoriaDto::fromRequest($request);

        expect($dto->nome)->toBe('Categoria Teste')
            ->and($dto->slug)->toBe('categoria-teste')
            ->and($dto->tipoMovimento)->toBe(TipoMovimento::Debito);
    });
});
