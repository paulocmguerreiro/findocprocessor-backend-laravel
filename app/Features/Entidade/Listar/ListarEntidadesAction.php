<?php

declare(strict_types=1);

namespace App\Features\Entidade\Listar;

use App\Models\Entidade;
use App\Shared\Enums\DirecaoOrdenacao;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Support\Facades\Gate;

final class ListarEntidadesAction
{
    /**
     * @return CursorPaginator<int, Entidade>
     *
     * @throws AuthorizationException
     */
    public function handle(int $perPage, CampoOrdenacaoEntidades $campoOrdenacao, DirecaoOrdenacao $direcaoOrdenacao): CursorPaginator
    {
        Gate::authorize('viewAny', Entidade::class);

        return Entidade::orderBy($campoOrdenacao->value, $direcaoOrdenacao->value)
            ->cursorPaginate($perPage);
    }
}
