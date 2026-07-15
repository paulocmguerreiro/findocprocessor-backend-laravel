<?php

declare(strict_types=1);

namespace App\Features\Role\Listar;

use App\Shared\Enums\DirecaoOrdenacao;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Role;

final class ListarRolesAction
{
    /**
     * @return CursorPaginator<int, Role>
     *
     * @throws AuthorizationException
     */
    public function handle(int $porPagina, CampoOrdenacaoRoles $campoOrdenacao, DirecaoOrdenacao $direcaoOrdenacao): CursorPaginator
    {
        Gate::authorize('viewAny', Role::class);

        return Role::with('permissions')
            ->orderBy($campoOrdenacao->value, $direcaoOrdenacao->value)
            ->cursorPaginate($porPagina);
    }
}
