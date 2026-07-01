<?php

declare(strict_types=1);

use App\Features\Entidade\Listar\CampoOrdenacaoEntidades;
use App\Features\Entidade\Listar\ListarEntidadesAction;
use App\Models\Entidade;
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

beforeEach(fn () => Cache::tags(['entidades'])->flush());

describe('como admin', function (): void {
    beforeEach(fn () => $this->actingAs(criarAdmin()));

    it('devolve lista vazia quando não existem entidades', function (): void {
        $resultado = app(ListarEntidadesAction::class)->handle(15, CampoOrdenacaoEntidades::Nome, DirecaoOrdenacao::Asc, FiltroEstadoRegisto::SomenteAtivos);

        expect($resultado->count())->toBe(0);
    });

    it('devolve entidades ordenadas por nome ascendente', function (): void {
        Entidade::factory()->create(['nome' => 'Zeta']);
        Entidade::factory()->create(['nome' => 'Alfa']);
        Entidade::factory()->create(['nome' => 'Meia']);

        $resultado = app(ListarEntidadesAction::class)->handle(15, CampoOrdenacaoEntidades::Nome, DirecaoOrdenacao::Asc, FiltroEstadoRegisto::SomenteAtivos);
        $nomes = $resultado->pluck('nome')->all();

        expect($nomes)->toBe(['Alfa', 'Meia', 'Zeta']);
    });

    it('respeita o per_page na paginação por cursor', function (): void {
        Entidade::factory()->count(5)->create();

        $resultado = app(ListarEntidadesAction::class)->handle(2, CampoOrdenacaoEntidades::Nome, DirecaoOrdenacao::Asc, FiltroEstadoRegisto::SomenteAtivos);

        expect($resultado->count())->toBe(2)
            ->and($resultado->nextCursor())->not->toBeNull();
    });

    it('cacheia resultados após primeira chamada', function (): void {
        Entidade::factory()->count(3)->create();

        app(ListarEntidadesAction::class)->handle(15, CampoOrdenacaoEntidades::Nome, DirecaoOrdenacao::Asc, FiltroEstadoRegisto::SomenteAtivos);

        $chave = app(CacheServico::class)->criarChave(
            TagCache::Entidades,
            TagOperacao::Listar,
            ['campo' => CampoOrdenacaoEntidades::Nome->value, 'cursor' => null, 'direcao' => DirecaoOrdenacao::Asc->value, 'estado' => FiltroEstadoRegisto::SomenteAtivos->value, 'por_pagina' => 15],
        );

        expect(Cache::tags(['entidades'])->has($chave))->toBeTrue();
    });

    it('lista apenas activas por omissão (SomenteAtivos)', function (): void {
        Entidade::factory()->count(2)->create();
        Entidade::factory()->inativa()->create();

        $resultado = app(ListarEntidadesAction::class)->handle(15, CampoOrdenacaoEntidades::Nome, DirecaoOrdenacao::Asc, FiltroEstadoRegisto::SomenteAtivos);

        expect($resultado->count())->toBe(2);
    });

    it('lista activas e inativas com Todos', function (): void {
        Entidade::factory()->count(2)->create();
        Entidade::factory()->inativa()->create();

        $resultado = app(ListarEntidadesAction::class)->handle(15, CampoOrdenacaoEntidades::Nome, DirecaoOrdenacao::Asc, FiltroEstadoRegisto::Todos);

        expect($resultado->count())->toBe(3);
    });

    it('lista apenas inativas com SomenteInativos', function (): void {
        Entidade::factory()->count(2)->create();
        Entidade::factory()->inativa()->create();

        $resultado = app(ListarEntidadesAction::class)->handle(15, CampoOrdenacaoEntidades::Nome, DirecaoOrdenacao::Asc, FiltroEstadoRegisto::SomenteInativos);

        expect($resultado->count())->toBe(1);
    });
});

describe('sem permissão de leitura', function (): void {
    it('lança AuthorizationException quando utilizador não tem permissão de leitura', function (): void {
        $this->actingAs(User::factory()->create()); // sem role — sem entidades.ver

        expect(fn (): CursorPaginator => app(ListarEntidadesAction::class)->handle(15, CampoOrdenacaoEntidades::Nome, DirecaoOrdenacao::Asc, FiltroEstadoRegisto::SomenteAtivos))
            ->toThrow(AuthorizationException::class);
    });
});

it('exige utilizador autenticado (guest é rejeitado)', function (): void {
    auth()->logout();

    expect(fn (): CursorPaginator => app(ListarEntidadesAction::class)->handle(15, CampoOrdenacaoEntidades::Nome, DirecaoOrdenacao::Asc, FiltroEstadoRegisto::SomenteAtivos))
        ->toThrow(AuthorizationException::class);
});
