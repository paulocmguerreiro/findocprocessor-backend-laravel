<?php

declare(strict_types=1);

use App\Features\CategoriaDocumento\Actualizar\ActualizarCategoriaDto;
use App\Features\CategoriaDocumento\Actualizar\ActualizarCategoriaRequest;
use App\Shared\Enums\TipoMovimento;

describe('Construtor', function (): void {
    it('lança InvalidArgumentException se nome for string vazia', function (): void {
        expect(fn (): ActualizarCategoriaDto => new ActualizarCategoriaDto(
            nome: '',
            slug: 'slug-valido',
            tipoMovimento: TipoMovimento::Neutro,
        ))->toThrow(InvalidArgumentException::class, 'nome não pode ser vazio.');
    });

    it('lança InvalidArgumentException se slug for string vazia', function (): void {
        expect(fn (): ActualizarCategoriaDto => new ActualizarCategoriaDto(
            nome: 'Nome Válido',
            slug: '',
            tipoMovimento: TipoMovimento::Neutro,
        ))->toThrow(InvalidArgumentException::class, 'slug não pode ser vazio.');
    });

    it('lança InvalidArgumentException se nome for só whitespace', function (): void {
        expect(fn (): ActualizarCategoriaDto => new ActualizarCategoriaDto(
            nome: '   ',
            slug: 'slug-valido',
            tipoMovimento: TipoMovimento::Neutro,
        ))->toThrow(InvalidArgumentException::class, 'nome não pode ser vazio.');
    });

    it('cria DTO com todos os campos válidos', function (): void {
        $dto = new ActualizarCategoriaDto(
            nome: 'Nome Actualizado',
            slug: 'nome-actualizado',
            tipoMovimento: TipoMovimento::Credito,
        );

        expect($dto->nome)->toBe('Nome Actualizado')
            ->and($dto->slug)->toBe('nome-actualizado')
            ->and($dto->tipoMovimento)->toBe(TipoMovimento::Credito);
    });
});

describe('fromRequest()', function (): void {
    it('cria DTO a partir de request com todos os campos', function (): void {
        $request = Mockery::mock(ActualizarCategoriaRequest::class);
        $request->shouldReceive('validated')->andReturn([
            'nome' => 'Nome Actualizado',
            'slug' => 'nome-actualizado',
            'tipo_movimento' => TipoMovimento::Debito->value,
        ]);

        $dto = ActualizarCategoriaDto::fromRequest($request);

        expect($dto->nome)->toBe('Nome Actualizado')
            ->and($dto->slug)->toBe('nome-actualizado')
            ->and($dto->tipoMovimento)->toBe(TipoMovimento::Debito);
    });
});
