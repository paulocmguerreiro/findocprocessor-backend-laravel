<?php

declare(strict_types=1);

namespace App\Features\CategoriaDocumento\Listar;

use App\Models\CategoriaDocumento;
use App\Shared\Enums\DirecaoOrdenacao;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Support\Facades\Gate;

final class ListarCategoriasAction
{
    /**
     * @return CursorPaginator<int, CategoriaDocumento>
     *
     * @throws AuthorizationException
     */
    public function handle(int $perPage, CampoOrdenacaoCategorias $campoOrdenacao, DirecaoOrdenacao $direcaoOrdenacao): CursorPaginator
    {
        Gate::authorize('viewAny', CategoriaDocumento::class);

        return CategoriaDocumento::orderBy($campoOrdenacao->value, $direcaoOrdenacao->value)
            ->cursorPaginate($perPage);
    }
}
