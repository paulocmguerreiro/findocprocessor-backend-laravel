<?php

declare(strict_types=1);

namespace App\Features\TipoDocumento\Listar;

use App\Models\CategoriaDocumento;
use App\Models\TipoDocumento;
use App\Shared\Cache\CacheServico;
use App\Shared\Cache\TagCache;
use App\Shared\Cache\TagOperacao;
use App\Shared\Cache\TtlCache;
use App\Shared\Enums\DirecaoOrdenacao;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Support\Facades\Gate;

final readonly class ListarTiposDocumentoAction
{
    public function __construct(private CacheServico $cache) {}

    /**
     * @return CursorPaginator<int, TipoDocumento>
     *
     * @throws AuthorizationException
     */
    public function handle(int $perPage, CampoOrdenacaoTiposDocumento $campoOrdenacao, DirecaoOrdenacao $direcaoOrdenacao, CategoriaDocumento|string|null $idCategoria = null): CursorPaginator
    {
        Gate::authorize('viewAny', TipoDocumento::class);

        $idCategoria = $idCategoria instanceof CategoriaDocumento ? $idCategoria->id : $idCategoria;

        $cursor = request()->string('cursor')->value() ?: null;

        $chave = $this->cache->criarChave(
            TagCache::TiposDocumento,
            TagOperacao::Listar,
            ['campo' => $campoOrdenacao->value, 'cursor' => $cursor, 'direcao' => $direcaoOrdenacao->value, 'id_categoria' => $idCategoria, 'por_pagina' => $perPage],
        );

        /** @var CursorPaginator<int, TipoDocumento> $resultado */
        $resultado = $this->cache->lembrar(
            TagCache::TiposDocumento,
            $chave,
            TtlCache::Curta,
            fn (): CursorPaginator => TipoDocumento::query()
                ->with('categoria')
                ->when($idCategoria, fn (Builder $consulta, string $id): Builder => $consulta->where('id_categoria', $id))
                ->orderBy($campoOrdenacao->value, $direcaoOrdenacao->value)
                ->cursorPaginate($perPage),
        );

        return $resultado;
    }
}
