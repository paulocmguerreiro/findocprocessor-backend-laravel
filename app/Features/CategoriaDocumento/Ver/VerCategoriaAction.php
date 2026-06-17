<?php

declare(strict_types=1);

namespace App\Features\CategoriaDocumento\Ver;

use App\Models\CategoriaDocumento;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Gate;

final class VerCategoriaAction
{
    /**
     * @throws ModelNotFoundException<CategoriaDocumento>
     * @throws AuthorizationException
     */
    public function handle(CategoriaDocumento|string $idCategoria): CategoriaDocumento
    {
        $categoria = is_string($idCategoria)
            ? CategoriaDocumento::findOrFail($idCategoria)
            : $idCategoria;

        Gate::authorize('view', $categoria);

        return $categoria;
    }
}
