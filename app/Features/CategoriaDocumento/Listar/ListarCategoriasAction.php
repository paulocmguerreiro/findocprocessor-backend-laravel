<?php

declare(strict_types=1);

namespace App\Features\CategoriaDocumento\Listar;

use App\Models\CategoriaDocumento;
use App\Shared\Cache\CacheServico;
use App\Shared\Cache\TagCache;
use App\Shared\Cache\TagOperacao;
use App\Shared\Cache\TtlCache;
use App\Shared\Enums\DirecaoOrdenacao;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Support\Facades\Gate;

final readonly class ListarCategoriasAction
{
    public function __construct(private CacheServico $cache) {}

    /**
     * @return CursorPaginator<int, CategoriaDocumento>
     *
     * @throws AuthorizationException
     */
    public function handle(int $perPage, CampoOrdenacaoCategorias $campoOrdenacao, DirecaoOrdenacao $direcaoOrdenacao): CursorPaginator
    {
        Gate::authorize('viewAny', CategoriaDocumento::class);

        $cursor = request()->string('cursor')->value() ?: null;

        $chave = $this->cache->criarChave(
            TagCache::CategoriasDocumento,
            TagOperacao::Listar,
            ['campo' => $campoOrdenacao->value, 'cursor' => $cursor, 'direcao' => $direcaoOrdenacao->value, 'por_pagina' => $perPage],
        );

        /** @var CursorPaginator<int, CategoriaDocumento> $resultado */
        $resultado = $this->cache->lembrar(
            TagCache::CategoriasDocumento,
            $chave,
            TtlCache::Curta,
            fn (): CursorPaginator => CategoriaDocumento::orderBy($campoOrdenacao->value, $direcaoOrdenacao->value)->cursorPaginate($perPage),
        );

        return $resultado;
    }
}
