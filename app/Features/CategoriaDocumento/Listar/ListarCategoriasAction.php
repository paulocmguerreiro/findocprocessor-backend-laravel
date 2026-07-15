<?php

declare(strict_types=1);

namespace App\Features\CategoriaDocumento\Listar;

use App\Models\CategoriaDocumento;
use App\Shared\Cache\CacheServico;
use App\Shared\Cache\TagCache;
use App\Shared\Cache\TagOperacao;
use App\Shared\Cache\TtlCache;
use App\Shared\Enums\DirecaoOrdenacao;
use App\Shared\Enums\FiltroEstadoRegisto;
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
    public function handle(int $porPagina, CampoOrdenacaoCategorias $campoOrdenacao, DirecaoOrdenacao $direcaoOrdenacao, FiltroEstadoRegisto $filtroEstado): CursorPaginator
    {
        Gate::authorize('viewAny', CategoriaDocumento::class);

        $cursor = request()->string('cursor')->value() ?: null;

        $chave = $this->cache->criarChave(
            TagCache::CategoriasDocumento,
            TagOperacao::Listar,
            ['campo' => $campoOrdenacao->value, 'cursor' => $cursor, 'direcao' => $direcaoOrdenacao->value, 'estado' => $filtroEstado->value, 'por_pagina' => $porPagina],
        );

        /** @var CursorPaginator<int, CategoriaDocumento> $categoriasPaginadas */
        $categoriasPaginadas = $this->cache->lembrar(
            TagCache::CategoriasDocumento,
            $chave,
            TtlCache::Curta,
            fn (): CursorPaginator => CategoriaDocumento::filtrarPorEstadoRegisto($filtroEstado)->orderBy($campoOrdenacao->value, $direcaoOrdenacao->value)->cursorPaginate($porPagina),
        );

        return $categoriasPaginadas;
    }
}
