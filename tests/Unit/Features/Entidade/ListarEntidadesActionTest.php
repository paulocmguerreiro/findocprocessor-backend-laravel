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

uses(RefreshDatabase::class);

describe('como admin', function (): void {
    beforeEach(fn () => $this->actingAs(criarAdmin()));

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
});

describe('sem permissão de leitura', function (): void {
    it('lança AuthorizationException quando utilizador não tem permissão de leitura', function (): void {
        $this->actingAs(User::factory()->create()); // sem role — sem entidades.ver

        expect(fn (): CursorPaginator => (new ListarEntidadesAction)->handle(15, CampoOrdenacaoEntidades::Nome, DirecaoOrdenacao::Asc))
            ->toThrow(AuthorizationException::class);
    });
});
