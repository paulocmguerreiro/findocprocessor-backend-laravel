<?php

declare(strict_types=1);

namespace App\Features\CategoriaDocumento\Listar;

use App\Models\CategoriaDocumento;
use App\Shared\Enums\DirecaoOrdenacao;
use Illuminate\Pagination\CursorPaginator;

final class ListarCategoriasAction
{
    /** @return CursorPaginator<int, CategoriaDocumento> */
    public function handle(int $perPage, CampoOrdenacaoCategorias $campoOrdenacao, DirecaoOrdenacao $direcaoOrdenacao): CursorPaginator
    {
        return CategoriaDocumento::orderBy($campoOrdenacao->value, $direcaoOrdenacao->value)
            ->cursorPaginate($perPage);
    }
}
