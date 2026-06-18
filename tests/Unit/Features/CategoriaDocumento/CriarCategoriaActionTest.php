<?php

declare(strict_types=1);

use App\Features\CategoriaDocumento\Criar\CriarCategoriaAction;
use App\Features\CategoriaDocumento\Criar\CriarCategoriaDto;
use App\Models\CategoriaDocumento;
use App\Shared\Enums\TipoMovimento;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('cria categoria com dados válidos', function (): void {
    $dto = new CriarCategoriaDto(
        nome: 'Fornecedores',
        slug: 'fornecedores',
        tipoMovimento: TipoMovimento::Debito,
    );

    $resultado = (new CriarCategoriaAction)->handle($dto);

    expect($resultado->nome)->toBe('Fornecedores')
        ->and($resultado->slug)->toBe('fornecedores')
        ->and($resultado->tipo_movimento)->toBe(TipoMovimento::Debito);

    $this->assertDatabaseHas('categorias_documento', ['slug' => 'fornecedores']);
});

it('faz rollback quando ocorre excepção após insert', function (): void {
    CategoriaDocumento::created(function (): void {
        throw new RuntimeException('falha simulada após insert');
    });

    $dto = new CriarCategoriaDto(
        nome: 'Fornecedores',
        slug: 'fornecedores',
        tipoMovimento: TipoMovimento::Debito,
    );

    expect(fn (): CategoriaDocumento => (new CriarCategoriaAction)->handle($dto))
        ->toThrow(RuntimeException::class, 'falha simulada após insert');

    $this->assertDatabaseCount('categorias_documento', 0);
});
