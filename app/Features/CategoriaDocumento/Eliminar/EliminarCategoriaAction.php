<?php

declare(strict_types=1);

namespace App\Features\CategoriaDocumento\Eliminar;

use App\Models\CategoriaDocumento;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Gate;

final class EliminarCategoriaAction
{
    /**
     * @throws ModelNotFoundException<CategoriaDocumento>
     * @throws AuthorizationException
     */
    public function handle(CategoriaDocumento|string $idCategoria): void
    {
        /** @var CategoriaDocumento $categoria */
        $categoria = is_string($idCategoria)
            ? CategoriaDocumento::findOrFail($idCategoria)
            : $idCategoria;

        Gate::authorize('delete', $categoria);

        $categoria->delete();
    }
}
