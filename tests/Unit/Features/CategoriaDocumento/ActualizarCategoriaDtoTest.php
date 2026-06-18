<?php

declare(strict_types=1);

use App\Features\CategoriaDocumento\Actualizar\ActualizarCategoriaDto;
use App\Features\CategoriaDocumento\Actualizar\ActualizarCategoriaRequest;
use App\Shared\Enums\TipoMovimento;

describe('Construtor', function (): void {
    it('lança InvalidArgumentException se nome for string vazia', function (): void {
        expect(fn (): ActualizarCategoriaDto => new ActualizarCategoriaDto(
            nome: '',
            slug: null,
            tipoMovimento: null,
        ))->toThrow(InvalidArgumentException::class);
    });

    it('lança InvalidArgumentException se slug for string vazia', function (): void {
        expect(fn (): ActualizarCategoriaDto => new ActualizarCategoriaDto(
            nome: null,
            slug: '',
            tipoMovimento: null,
        ))->toThrow(InvalidArgumentException::class);
    });

    it('aceita todos os campos nulos (actualização parcial sem campos)', function (): void {
        $dto = new ActualizarCategoriaDto(nome: null, slug: null, tipoMovimento: null);

        expect($dto->nome)->toBeNull()
            ->and($dto->slug)->toBeNull()
            ->and($dto->tipoMovimento)->toBeNull();
    });

    it('cria DTO com dados parciais válidos', function (): void {
        $dto = new ActualizarCategoriaDto(
            nome: 'Novo Nome',
            slug: null,
            tipoMovimento: TipoMovimento::Neutro,
        );

        expect($dto->nome)->toBe('Novo Nome')
            ->and($dto->slug)->toBeNull()
            ->and($dto->tipoMovimento)->toBe(TipoMovimento::Neutro);
    });
});

describe('fromRequest()', function (): void {
    it('cria DTO a partir de request com campos parciais', function (): void {
        $request = Mockery::mock(ActualizarCategoriaRequest::class);
        $request->shouldReceive('validated')->andReturn(['nome' => 'Nome Actualizado']);

        $dto = ActualizarCategoriaDto::fromRequest($request);

        expect($dto->nome)->toBe('Nome Actualizado')
            ->and($dto->slug)->toBeNull()
            ->and($dto->tipoMovimento)->toBeNull();
    });
});
