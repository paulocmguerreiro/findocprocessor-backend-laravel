<?php

declare(strict_types=1);

use App\Features\CategoriaDocumento\Listar\CampoOrdenacaoCategorias;
use App\Features\CategoriaDocumento\Listar\ListarCategoriasAction;
use App\Models\CategoriaDocumento;
use App\Shared\Enums\DirecaoOrdenacao;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('devolve lista vazia quando não existem categorias', function (): void {
    $resultado = (new ListarCategoriasAction)->handle(15, CampoOrdenacaoCategorias::Nome, DirecaoOrdenacao::Asc);

    expect($resultado->count())->toBe(0);
});

it('devolve categorias ordenadas por nome ascendente', function (): void {
    CategoriaDocumento::factory()->create(['nome' => 'Zeta']);
    CategoriaDocumento::factory()->create(['nome' => 'Alfa']);
    CategoriaDocumento::factory()->create(['nome' => 'Beta']);

    $resultado = (new ListarCategoriasAction)->handle(15, CampoOrdenacaoCategorias::Nome, DirecaoOrdenacao::Asc);
    $nomes = $resultado->pluck('nome')->all();

    expect($nomes)->toBe(['Alfa', 'Beta', 'Zeta']);
});

it('respeita o per_page na paginação por cursor', function (): void {
    CategoriaDocumento::factory()->count(5)->create();

    $resultado = (new ListarCategoriasAction)->handle(2, CampoOrdenacaoCategorias::Nome, DirecaoOrdenacao::Asc);

    expect($resultado->count())->toBe(2)
        ->and($resultado->nextCursor())->not->toBeNull();
});
