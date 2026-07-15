<?php

declare(strict_types=1);

namespace App\Features\Entidade\Listar;

use App\Models\Entidade;
use App\Shared\Cache\CacheServico;
use App\Shared\Cache\TagCache;
use App\Shared\Cache\TagOperacao;
use App\Shared\Cache\TtlCache;
use App\Shared\Enums\DirecaoOrdenacao;
use App\Shared\Enums\FiltroEstadoRegisto;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Support\Facades\Gate;

final readonly class ListarEntidadesAction
{
    public function __construct(private CacheServico $cache) {}

    /**
     * @return CursorPaginator<int, Entidade>
     *
     * @throws AuthorizationException
     */
    public function handle(int $porPagina, CampoOrdenacaoEntidades $campoOrdenacao, DirecaoOrdenacao $direcaoOrdenacao, FiltroEstadoRegisto $filtroEstado): CursorPaginator
    {
        Gate::authorize('viewAny', Entidade::class);

        $cursor = request()->string('cursor')->value() ?: null;

        $chave = $this->cache->criarChave(
            TagCache::Entidades,
            TagOperacao::Listar,
            ['campo' => $campoOrdenacao->value, 'cursor' => $cursor, 'direcao' => $direcaoOrdenacao->value, 'estado' => $filtroEstado->value, 'por_pagina' => $porPagina],
        );

        /** @var CursorPaginator<int, Entidade> $entidadesPaginadas */
        $entidadesPaginadas = $this->cache->lembrar(
            TagCache::Entidades,
            $chave,
            TtlCache::Curta,
            fn (): CursorPaginator => Entidade::filtrarPorEstadoRegisto($filtroEstado)
                ->orderBy($campoOrdenacao->value, $direcaoOrdenacao->value)
                ->cursorPaginate($porPagina),
        );

        return $entidadesPaginadas;
    }
}
