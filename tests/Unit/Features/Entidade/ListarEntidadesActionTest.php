<?php

declare(strict_types=1);

use App\Features\Entidade\Listar\CampoOrdenacaoEntidades;
use App\Features\Entidade\Listar\ListarEntidadesAction;
use App\Models\Entidade;
use App\Models\User;
use App\Shared\Enums\DirecaoOrdenacao;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\CursorPaginator;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $utilizador = User::factory()->create();
    $utilizador->assignRole('admin');
    $this->actingAs($utilizador);
});

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

it('lança AuthorizationException quando utilizador não tem permissão de leitura', function (): void {
    $utilizador = User::factory()->create(); // sem role — sem entidades.ver
    $this->actingAs($utilizador);

    expect(fn (): CursorPaginator => (new ListarEntidadesAction)->handle(15, CampoOrdenacaoEntidades::Nome, DirecaoOrdenacao::Asc))
        ->toThrow(AuthorizationException::class);
});
