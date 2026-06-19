<?php

declare(strict_types=1);

use App\Features\Entidade\Listar\CampoOrdenacaoEntidades;
use App\Features\Entidade\Listar\ListarEntidadesAction;
use App\Models\Entidade;
use App\Shared\Enums\DirecaoOrdenacao;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('devolve lista vazia quando não existem entidades', function (): void {
    $resultado = (new ListarEntidadesAction)->handle(15, CampoOrdenacaoEntidades::Nome, DirecaoOrdenacao::Asc);

    expect($resultado->count())->toBe(0);
});

it('devolve entidades ordenadas por nome ascendente', function (): void {
    Entidade::factory()->create(['nome' => 'Zeta']);
    Entidade::factory()->create(['nome' => 'Alfa']);
    Entidade::factory()->create(['nome' => 'Meia']);

    $resultado = (new ListarEntidadesAction)->handle(15, CampoOrdenacaoEntidades::Nome, DirecaoOrdenacao::Asc);
    $nomes = $resultado->pluck('nome')->all();

    expect($nomes)->toBe(['Alfa', 'Meia', 'Zeta']);
});

it('respeita o per_page na paginação por cursor', function (): void {
    Entidade::factory()->count(5)->create();

    $resultado = (new ListarEntidadesAction)->handle(2, CampoOrdenacaoEntidades::Nome, DirecaoOrdenacao::Asc);

    expect($resultado->count())->toBe(2)
        ->and($resultado->nextCursor())->not->toBeNull();
});
