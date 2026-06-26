<?php

declare(strict_types=1);

namespace App\Features\Documento\Listar;

use App\Models\Documento;
use App\Shared\Cache\CacheServico;
use App\Shared\Cache\TagCache;
use App\Shared\Cache\TagOperacao;
use App\Shared\Cache\TtlCache;
use App\Shared\Enums\DirecaoOrdenacao;
use App\Shared\Enums\EstadoDocumento;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Support\Facades\Gate;

final readonly class ListarDocumentosAction
{
    public function __construct(private CacheServico $cache) {}

    /**
     * Listagem directa no Eloquent (sem Repository) — cursorPaginate + scope de estado + cache.
     *
     * @return CursorPaginator<int, Documento>
     *
     * @throws AuthorizationException
     */
    public function handle(
        int $porPagina,
        CampoOrdenacaoDocumentos $campoOrdenacao,
        DirecaoOrdenacao $direcaoOrdenacao,
        ?EstadoDocumento $estado = null,
    ): CursorPaginator {
        Gate::authorize('viewAny', Documento::class);

        $cursor = request()->string('cursor')->value() ?: null;

        $chave = $this->cache->criarChave(
            TagCache::Documentos,
            TagOperacao::Listar,
            [
                'campo' => $campoOrdenacao->value,
                'cursor' => $cursor,
                'direcao' => $direcaoOrdenacao->value,
                'estado' => $estado?->value,
                'por_pagina' => $porPagina,
            ],
        );

        /** @var CursorPaginator<int, Documento> $resultado */
        $resultado = $this->cache->lembrar(
            TagCache::Documentos,
            $chave,
            TtlCache::Curta,
            fn (): CursorPaginator => Documento::query()
                ->when($estado, fn (Builder $consulta, EstadoDocumento $estado) => $consulta->whereEstado($estado))
                ->orderBy($campoOrdenacao->value, $direcaoOrdenacao->value)
                ->cursorPaginate($porPagina),
        );

        return $resultado;
    }
}
