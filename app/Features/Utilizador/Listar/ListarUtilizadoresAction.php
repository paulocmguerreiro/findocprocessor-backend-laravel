<?php

declare(strict_types=1);

namespace App\Features\Utilizador\Listar;

use App\Models\User;
use App\Shared\Cache\CacheServico;
use App\Shared\Cache\TagCache;
use App\Shared\Cache\TagOperacao;
use App\Shared\Cache\TtlCache;
use App\Shared\Enums\DirecaoOrdenacao;
use App\Shared\Enums\FiltroEstadoRegisto;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Support\Facades\Gate;

final readonly class ListarUtilizadoresAction
{
    public function __construct(private CacheServico $cache) {}

    /**
     * @return CursorPaginator<int, User>
     *
     * @throws AuthorizationException
     */
    public function handle(int $porPagina, CampoOrdenacaoUtilizadores $campoOrdenacao, DirecaoOrdenacao $direcaoOrdenacao, FiltroEstadoRegisto $filtroEstado): CursorPaginator
    {
        Gate::authorize('viewAny', User::class);

        $cursor = request()->string('cursor')->value() ?: null;

        $chave = $this->cache->criarChave(
            TagCache::Utilizadores,
            TagOperacao::Listar,
            ['campo' => $campoOrdenacao->value, 'cursor' => $cursor, 'direcao' => $direcaoOrdenacao->value, 'estado' => $filtroEstado->value, 'por_pagina' => $porPagina],
        );

        /** @var CursorPaginator<int, User> $resultado */
        $resultado = $this->cache->lembrar(
            TagCache::Utilizadores,
            $chave,
            TtlCache::Curta,
            fn (): CursorPaginator => User::with('roles')
                ->filtrarPorEstadoRegisto($filtroEstado)
                ->orderBy($campoOrdenacao->value, $direcaoOrdenacao->value)
                ->cursorPaginate($porPagina),
        );

        return $resultado;
    }
}
