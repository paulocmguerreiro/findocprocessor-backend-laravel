<?php

declare(strict_types=1);

use App\Features\CategoriaDocumento\Listar\CampoOrdenacaoCategorias;
use App\Features\CategoriaDocumento\Listar\ListarCategoriasAction;
use App\Models\CategoriaDocumento;
use App\Models\User;
use App\Shared\Cache\CacheServico;
use App\Shared\Cache\TagCache;
use App\Shared\Cache\TagOperacao;
use App\Shared\Enums\DirecaoOrdenacao;
use App\Shared\Enums\FiltroEstadoRegisto;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(fn () => Cache::tags(['categorias_documento'])->flush());

describe('como admin', function (): void {
    beforeEach(fn () => $this->actingAs(criarAdmin()));

    it('devolve lista vazia quando não existem categorias', function (): void {
        $resultado = app(ListarCategoriasAction::class)->handle(15, CampoOrdenacaoCategorias::Nome, DirecaoOrdenacao::Asc, FiltroEstadoRegisto::SomenteAtivos);

        expect($resultado->count())->toBe(0);
    });

    it('devolve categorias ordenadas por nome ascendente', function (): void {
        CategoriaDocumento::factory()->create(['nome' => 'Zeta']);
        CategoriaDocumento::factory()->create(['nome' => 'Alfa']);
        CategoriaDocumento::factory()->create(['nome' => 'Beta']);

        $resultado = app(ListarCategoriasAction::class)->handle(15, CampoOrdenacaoCategorias::Nome, DirecaoOrdenacao::Asc, FiltroEstadoRegisto::SomenteAtivos);
        $nomes = $resultado->pluck('nome')->all();

        expect($nomes)->toBe(['Alfa', 'Beta', 'Zeta']);
    });

    it('respeita o per_page na paginação por cursor', function (): void {
        CategoriaDocumento::factory()->count(5)->create();

        $resultado = app(ListarCategoriasAction::class)->handle(2, CampoOrdenacaoCategorias::Nome, DirecaoOrdenacao::Asc, FiltroEstadoRegisto::SomenteAtivos);

        expect($resultado->count())->toBe(2)
            ->and($resultado->nextCursor())->not->toBeNull();
    });

    it('cacheia resultados após primeira chamada', function (): void {
        CategoriaDocumento::factory()->count(3)->create();

        app(ListarCategoriasAction::class)->handle(15, CampoOrdenacaoCategorias::Nome, DirecaoOrdenacao::Asc, FiltroEstadoRegisto::SomenteAtivos);

        $chave = app(CacheServico::class)->criarChave(
            TagCache::CategoriasDocumento,
            TagOperacao::Listar,
            ['campo' => CampoOrdenacaoCategorias::Nome->value, 'cursor' => null, 'direcao' => DirecaoOrdenacao::Asc->value, 'estado' => FiltroEstadoRegisto::SomenteAtivos->value, 'por_pagina' => 15],
        );

        expect(Cache::tags(['categorias_documento'])->has($chave))->toBeTrue();
    });

    it('lista só categorias activas com FiltroEstadoRegisto::SomenteAtivos', function (): void {
        CategoriaDocumento::factory()->create(['nome' => 'Ativa']);
        CategoriaDocumento::factory()->inativa()->create(['nome' => 'Inativa']);

        $resultado = app(ListarCategoriasAction::class)->handle(15, CampoOrdenacaoCategorias::Nome, DirecaoOrdenacao::Asc, FiltroEstadoRegisto::SomenteAtivos);

        expect($resultado->pluck('nome')->all())->toBe(['Ativa']);
    });

    it('lista só categorias inactivas com FiltroEstadoRegisto::SomenteInativos', function (): void {
        CategoriaDocumento::factory()->create(['nome' => 'Ativa']);
        CategoriaDocumento::factory()->inativa()->create(['nome' => 'Inativa']);

        $resultado = app(ListarCategoriasAction::class)->handle(15, CampoOrdenacaoCategorias::Nome, DirecaoOrdenacao::Asc, FiltroEstadoRegisto::SomenteInativos);

        expect($resultado->pluck('nome')->all())->toBe(['Inativa']);
    });

    it('lista todas as categorias com FiltroEstadoRegisto::Todos', function (): void {
        CategoriaDocumento::factory()->create(['nome' => 'Ativa']);
        CategoriaDocumento::factory()->inativa()->create(['nome' => 'Inativa']);

        $resultado = app(ListarCategoriasAction::class)->handle(15, CampoOrdenacaoCategorias::Nome, DirecaoOrdenacao::Asc, FiltroEstadoRegisto::Todos);

        expect($resultado->pluck('nome')->all())->toBe(['Ativa', 'Inativa']);
    });
});

describe('sem permissão de leitura', function (): void {
    it('lança AuthorizationException quando utilizador não tem permissão de leitura', function (): void {
        $this->actingAs(User::factory()->create()); // sem role — sem categorias-documento.ver

        expect(fn (): CursorPaginator => app(ListarCategoriasAction::class)->handle(15, CampoOrdenacaoCategorias::Nome, DirecaoOrdenacao::Asc, FiltroEstadoRegisto::SomenteAtivos))
            ->toThrow(AuthorizationException::class);
    });
});

it('exige utilizador autenticado (guest é rejeitado)', function (): void {
    auth()->logout();

    expect(fn (): CursorPaginator => app(ListarCategoriasAction::class)->handle(15, CampoOrdenacaoCategorias::Nome, DirecaoOrdenacao::Asc, FiltroEstadoRegisto::SomenteAtivos))
        ->toThrow(AuthorizationException::class);
});
